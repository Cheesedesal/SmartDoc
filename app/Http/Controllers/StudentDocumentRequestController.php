<?php

namespace App\Http\Controllers;

use App\Enums\ClaimingMethod;
use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class StudentDocumentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $documentTypes = DocumentType::active()
            ->with(['requirements' => fn ($query) => $query->unarchived()])
            ->get();
        $requests = DocumentRequest::with('documentType')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('requests.student-index', compact('documentTypes', 'requests'));
    }

    public function store(Request $request): RedirectResponse
    {
        $documentType = DocumentType::active()
            ->with(['requirements' => fn ($query) => $query->unarchived()])
            ->findOrFail($request->input('document_type_id'));
        $requirements = $documentType->requirements;
        $allowedIds = $requirements->modelKeys();
        $submittedFiles = array_map('intval', array_keys($request->file('requirements', [])));

        if (array_diff($submittedFiles, $allowedIds)) {
            return back()->withErrors([
                'requirements' => 'One or more uploaded files do not belong to this document type.',
            ])->withInput();
        }

        $rules = [
            'document_type_id' => ['required', 'integer', Rule::in([$documentType->id])],
            'purpose' => ['required', 'string', 'max:1000'],
            'claiming_method' => ['required', Rule::in([ClaimingMethod::PickUp->value])],
            'requester_remarks' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($requirements as $requirement) {
            $rules["requirements.{$requirement->id}"] = [
                $requirement->is_required ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ];
        }

        $validated = $request->validate($rules);
        $storedFiles = [];

        try {
            $documentRequest = DB::transaction(function () use ($request, $documentType, $requirements, $validated, &$storedFiles) {
                $documentRequest = DocumentRequest::create([
                    'user_id' => $request->user()->id,
                    'document_type_id' => $documentType->id,
                    'purpose' => $validated['purpose'],
                    'claiming_method' => ClaimingMethod::PickUp,
                    'requester_remarks' => $validated['requester_remarks'] ?? null,
                    'status' => RequestStatus::Pending,
                    'current_stage' => RequestStage::RequestReceived,
                ]);

                foreach ($requirements as $requirement) {
                    $file = $request->file("requirements.{$requirement->id}");
                    if (! $file) {
                        continue;
                    }

                    $path = $file->store("document-requests/{$documentRequest->id}", 'local');
                    if (! is_string($path)) {
                        throw new RuntimeException('A requirement upload could not be stored.');
                    }

                    $storedFiles[] = $path;
                    $documentRequest->uploads()->create([
                        'document_requirement_id' => $requirement->id,
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                    ]);
                }

                $documentRequest->history()->create([
                    'changed_by' => $request->user()->id,
                    'event' => 'Request submitted',
                    'to_status' => RequestStatus::Pending->value,
                    'to_stage' => RequestStage::RequestReceived->value,
                    'remarks' => 'Student submitted a document request.',
                ]);

                return $documentRequest;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedFiles);
            throw $exception;
        }

        app(\App\Services\PortalNotifier::class)->newRequest($documentRequest);

        return redirect()
            ->route('requests.show', $documentRequest)
            ->with('status', 'Your document request was submitted.');
    }

    public function replaceRequirement(Request $request, DocumentRequest $documentRequest, \App\Models\UploadedRequirement $upload): RedirectResponse
    {
        abort_unless($documentRequest->user_id === $request->user()->id, 404);
        abort_unless($upload->document_request_id === $documentRequest->id, 404);
        abort_unless($upload->verification_remarks !== null && ! $upload->is_verified, 409);

        $validated = $request->validate([
            'requirement_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $file = $validated['requirement_file'];
        $path = $file->store("document-requests/{$documentRequest->id}", 'local');
        if (! is_string($path)) {
            throw new RuntimeException('The replacement requirement could not be stored.');
        }

        $oldPath = $upload->path;
        DB::transaction(function () use ($upload, $file, $path, $documentRequest, $request) {
            $upload->update([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'is_verified' => false,
                'verification_remarks' => null,
                'ai_review_status' => null,
                'ai_review_remarks' => null,
            ]);
            $documentRequest->history()->create([
                'changed_by' => $request->user()->id,
                'event' => 'Requirement replacement uploaded',
                'remarks' => $upload->requirement->name,
            ]);
        });
        Storage::disk('local')->delete($oldPath);

        return back()->with('status', 'Replacement file submitted for review.');
    }

    public function show(Request $request, DocumentRequest $documentRequest): View
    {
        abort_unless($documentRequest->user_id === $request->user()->id, 404);

        $documentRequest->load([
            'student',
            'documentType.requirements',
            'uploads.requirement',
            'history.actor',
        ]);

        return view('requests.show', compact('documentRequest'));
    }
}
