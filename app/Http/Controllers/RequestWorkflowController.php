<?php

namespace App\Http\Controllers;

use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\UploadedRequirement;
use App\Services\GeminiRequirementReviewer;
use App\Services\PortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class RequestWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $query = DocumentRequest::with([
            'student.department',
            'student.office',
            'student.positionRecord',
            'student.courseRecord',
            'student.yearRecord',
            'student.sectionRecord',
            'documentType',
        ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->toString();
                $query->where(function ($query) use ($search) {
                    $query->where('id', preg_replace('/\D/', '', $search) ?: 0)
                        ->orWhereHas('student', fn ($student) => $student
                            ->where('firstname', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('school_id', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');

        $requests = $query->paginate(12)->withQueryString();

        return view('requests.staff-index', compact('requests'));
    }

    public function show(DocumentRequest $documentRequest): View
    {
        $documentRequest->load([
            'student.department',
            'student.office',
            'student.positionRecord',
            'student.courseRecord',
            'student.yearRecord',
            'student.sectionRecord',
            'documentType.requirements',
            'uploads.requirement',
            'history.actor',
        ]);

        return view('requests.show', compact('documentRequest'));
    }

    public function records(Request $request): View
    {
        $query = $this->filteredRequests($request);
        $records = $query->latest()->paginate(15)->withQueryString();
        $counts = [
            'total' => DocumentRequest::count(),
            'completed' => DocumentRequest::where('status', RequestStatus::Completed)->count(),
            'rejected' => DocumentRequest::where('status', RequestStatus::Rejected)->count(),
        ];
        $documentTypes = \App\Models\DocumentType::orderBy('name')->get(['id', 'name']);

        return view('requests.records', compact('records', 'counts', 'documentTypes'));
    }

    public function exportRecords(Request $request)
    {
        $requests = $this->filteredRequests($request)
            ->with(['student.department', 'student.office', 'student.positionRecord', 'student.courseRecord', 'student.yearRecord', 'student.sectionRecord', 'documentType'])
            ->latest()
            ->get();
        $filename = 'smartdoc-request-history-'.now()->format('Ymd-His').'.csv';

        return Response::streamDownload(function () use ($requests) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Reference', 'Student ID', 'Student', 'Document Type', 'Purpose', 'Status', 'Stage', 'Requested At', 'Completed At', 'Rejected At', 'Rejection Reason']);
            foreach ($requests as $item) {
                fputcsv($output, [
                    $item->reference,
                    $this->spreadsheetSafe($item->student->school_id),
                    $this->spreadsheetSafe($item->student->full_name),
                    $this->spreadsheetSafe($item->documentType->name),
                    $this->spreadsheetSafe($item->purpose),
                    $item->status->value,
                    $item->current_stage->value,
                    $item->created_at?->toDateTimeString(),
                    $item->completed_at?->toDateTimeString(),
                    $item->rejected_at?->toDateTimeString(),
                    $this->spreadsheetSafe($item->rejection_reason),
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function verify(Request $request, DocumentRequest $documentRequest, UploadedRequirement $upload): RedirectResponse
    {
        abort_unless($upload->document_request_id === $documentRequest->id, 404);
        abort_if(in_array($documentRequest->status, [RequestStatus::Completed, RequestStatus::Rejected], true), 409);

        $validated = $request->validate([
            'is_verified' => ['required', 'boolean'],
            'verification_remarks' => [
                (bool) $request->input('is_verified') ? 'nullable' : 'required',
                'string',
                'max:2000',
            ],
        ]);

        $upload->update([
            'is_verified' => (bool) $validated['is_verified'],
            'verification_remarks' => $validated['verification_remarks'] ?? null,
        ]);

        $documentRequest->history()->create([
            'changed_by' => $request->user()->id,
            'event' => $upload->is_verified ? 'Requirement verified' : 'Requirement needs correction',
            'remarks' => $upload->requirement->name.($validated['verification_remarks'] ? ': '.$validated['verification_remarks'] : ''),
        ]);

        if (! $upload->is_verified) {
            app(PortalNotifier::class)->requestUpdated(
                $documentRequest,
                'The '.$upload->requirement->name.' needs correction: '.$upload->verification_remarks
            );
        }

        return back()->with('status', 'Requirement review saved.');
    }

    public function aiReview(
        Request $request,
        DocumentRequest $documentRequest,
        UploadedRequirement $upload,
        GeminiRequirementReviewer $reviewer
    ): RedirectResponse {
        abort_unless($upload->document_request_id === $documentRequest->id, 404);
        abort_if(in_array($documentRequest->status, [RequestStatus::Completed, RequestStatus::Rejected], true), 409);

        try {
            $result = $reviewer->review($upload->load('requirement.documentType'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['ai_review' => $exception->getMessage()]);
        }

        $upload->update([
            'ai_review_status' => $result['recommendation'],
            'ai_review_remarks' => $result['remarks'],
        ]);
        $documentRequest->history()->create([
            'changed_by' => $request->user()->id,
            'event' => 'AI review recommendation generated',
            'remarks' => $upload->requirement->name.': '.$result['recommendation'].' — '.$result['remarks'],
        ]);

        return back()->with('status', 'AI recommendation saved. A staff member must make the final verification decision.');
    }

    public function start(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        DB::transaction(function () use ($request, $documentRequest) {
            $lockedRequest = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRequest->status === RequestStatus::Pending, 409);

            $requiredIds = $lockedRequest->documentType->requirements()
                ->where('is_required', true)
                ->pluck('id');
            $verifiedIds = $lockedRequest->uploads()
                ->where('is_verified', true)
                ->pluck('document_requirement_id');

            if ($requiredIds->diff($verifiedIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'requirements' => 'Verify every required document before starting processing.',
                ]);
            }

            $fromStatus = $lockedRequest->status->value;
            $fromStage = $lockedRequest->current_stage->value;
            $lockedRequest->update([
                'status' => RequestStatus::InProgress,
                'current_stage' => RequestStage::ForVerification,
                'current_processed_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'estimated_completion_date' => now()->addWeekdays($lockedRequest->documentType->processing_days),
            ]);

            $this->record($lockedRequest, $request, 'Processing started', $fromStatus, RequestStatus::InProgress->value, $fromStage, RequestStage::ForVerification->value);
        });
        app(PortalNotifier::class)->requestUpdated($documentRequest->fresh(['student', 'documentType']), 'In Progress');

        return back()->with('status', 'Request processing has started.');
    }

    public function advance(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        DB::transaction(function () use ($request, $documentRequest) {
            $lockedRequest = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRequest->status === RequestStatus::InProgress, 409);

            $next = $lockedRequest->current_stage->next();
            abort_if($next === null, 409);

            $fromStage = $lockedRequest->current_stage->value;
            $fromStatus = $lockedRequest->status->value;
            $newStatus = RequestStatus::InProgress;
            $newStage = $next;
            $attributes = [
                'current_stage' => $next,
                'current_processed_by' => $request->user()->id,
            ];

            if ($next === RequestStage::ReadyForRelease) {
                $attributes['completed_at'] = now();
                $newStatus = RequestStatus::ReadyForPickup;
                $attributes['status'] = $newStatus;
            }

            $lockedRequest->update($attributes);
            $event = $newStatus === RequestStatus::ReadyForPickup
                ? 'Document ready for pickup'
                : 'Processing stage completed';
            $this->record($lockedRequest, $request, $event, $fromStatus, $newStatus->value, $fromStage, $newStage->value);
        });
        $freshRequest = $documentRequest->fresh(['student', 'documentType']);
        app(PortalNotifier::class)->requestUpdated($freshRequest, $freshRequest->status->value);

        return back()->with('status', 'Request status updated.');
    }

    public function release(Request $request, DocumentRequest $documentRequest): RedirectResponse
    {
        abort_unless($documentRequest->user_id === $request->user()->id, 404);
        abort_unless($documentRequest->status === RequestStatus::ReadyForPickup, 409);

        DB::transaction(function () use ($request, $documentRequest) {
            $fromStatus = $documentRequest->status->value;
            $fromStage = $documentRequest->current_stage->value;
            $documentRequest->update([
                'status' => RequestStatus::Completed,
                'current_stage' => RequestStage::Released,
                'released_at' => now(),
                'released_by' => $request->user()->id,
                'current_processed_by' => null,
            ]);
            $this->record($documentRequest, $request, 'Student confirmed document collection', $fromStatus, RequestStatus::Completed->value, $fromStage, RequestStage::Released->value);
        });
        app(PortalNotifier::class)->requestUpdated($documentRequest->fresh(['student', 'documentType']), RequestStatus::Completed->value);

        return back()->with('status', 'Document collection confirmed.');
    }

    public function download(Request $request, DocumentRequest $documentRequest, UploadedRequirement $upload)
    {
        abort_unless($upload->document_request_id === $documentRequest->id, 404);
        abort_unless(
            $request->user()->hasRole('admin', 'staff') || $documentRequest->user_id === $request->user()->id,
            404
        );

        return Storage::disk('local')->download($upload->path, $upload->original_name);
    }

    private function record(
        DocumentRequest $documentRequest,
        Request $request,
        string $event,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $fromStage,
        ?string $toStage,
        ?string $remarks = null
    ): void {
        $documentRequest->history()->create([
            'changed_by' => $request->user()->id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'remarks' => $remarks,
        ]);
    }

    private function filteredRequests(Request $request)
    {
        return DocumentRequest::query()
            ->with(['student', 'documentType'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->toString();
                $query->where(function ($query) use ($search) {
                    $query->where('id', preg_replace('/\D/', '', $search) ?: 0)
                        ->orWhereHas('student', fn ($student) => $student
                            ->where('firstname', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('school_id', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('document_type_id'), fn ($query) => $query->where('document_type_id', $request->integer('document_type_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')));
    }

    private function spreadsheetSafe(?string $value): ?string
    {
        return $value !== null && preg_match('/^[\t\r\n ]*[=+\-@]/', $value)
            ? "'".$value
            : $value;
    }
}
