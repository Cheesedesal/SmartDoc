<?php

namespace App\Http\Controllers;

use App\Actions\SubmitDocumentRequest;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\RequestNotAllowedException;
use App\Http\Requests\CancelRequestRequest;
use App\Http\Requests\OwnedRequestActionRequest;
use App\Http\Requests\ResubmitPaymentRequest;
use App\Http\Requests\StoreDocumentRequestRequest;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Services\RequestAutomation;
use App\Services\RequestLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Student-facing request workflow. Route group is protected by `auth` + `role:student`;
 * ownership of an individual request is enforced by DocumentRequestPolicy::manage/view.
 * Business rules live in SubmitDocumentRequest and RequestLifecycle, not here.
 */
class StudentRequestController extends Controller
{
    public function __construct(
        private readonly RequestAutomation $automation,
        private readonly RequestLifecycle $lifecycle,
    ) {
    }

    public function index(Request $request): View
    {
        $this->tickAutomation();

        $user = $request->user();

        // Port of reference getUserRequestTracking() filters; always scoped to the signed-in student.
        $filters = [
            'type' => (string) $request->query('type', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $query = DocumentRequest::query()->where('user_id', $user->id)->with('documentType')->latest('id');

        if (ctype_digit($filters['type'])) {
            $query->where('document_type_id', (int) $filters['type']);
        }

        if (RequestStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        // Port of reference getUserRequestCounts() (all of this student's requests, not just the filtered ones).
        $byStatus = DocumentRequest::query()
            ->where('user_id', $user->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('student.requests.index', [
            'requests' => $query->paginate(10)->withQueryString(),
            'blocking' => $this->blockingRequest($user->id),
            'filters' => $filters,
            'statuses' => RequestStatus::cases(),
            'types' => DocumentType::query()->orderBy('name')->get(['id', 'name']),
            'counts' => [
                'total' => (int) $byStatus->sum(),
                'pending' => (int) ($byStatus[RequestStatus::Pending->value] ?? 0),
                'in_progress' => (int) ($byStatus[RequestStatus::InProgress->value] ?? 0),
                'ready' => (int) ($byStatus[RequestStatus::ReadyForPickup->value] ?? 0),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $blocking = $this->blockingRequest($user->id);
        $types = DocumentType::active()->get();

        $selected = null;

        if ($blocking === null && is_numeric($request->query('type'))) {
            $selected = DocumentType::query()
                ->with('requirements')
                ->where('is_active', true)
                ->find((int) $request->query('type'));
        }

        return view('student.requests.create', [
            'blocking' => $blocking,
            'types' => $types,
            'selected' => $selected,
        ]);
    }

    public function store(StoreDocumentRequestRequest $request, SubmitDocumentRequest $submit): RedirectResponse
    {
        $type = $request->documentType();

        // Validation already guarantees the type exists and is active.
        abort_if($type === null, 422);

        try {
            $documentRequest = $submit->execute(
                $request->user(),
                $type,
                $request->requestData(),
                $request->requirementFiles(),
                $request->file('payment_proof'),
            );
        } catch (RequestNotAllowedException $e) {
            return redirect()
                ->route('student.requests.create', ['type' => $type->id])
                ->withInput()
                ->withErrors(['request' => $e->getMessage()]);
        }

        return redirect()
            ->route('student.requests.show', $documentRequest)
            ->with('status', 'Your request was submitted. It will be checked automatically in a moment.');
    }

    public function show(Request $request, DocumentRequest $documentRequest): View
    {
        $this->authorize('view', $documentRequest);
        $this->tickAutomation();

        $documentRequest->refresh()->load(['documentType.requirements', 'uploads.requirement', 'aiLogs']);

        return view('student.requests.show', [
            'documentRequest' => $documentRequest,
            'canCancel' => $documentRequest->status === RequestStatus::Pending,
            'canResubmit' => $documentRequest->status === RequestStatus::Pending
                && $documentRequest->payment_status === PaymentStatus::Flagged,
            'canReceive' => $documentRequest->status === RequestStatus::ReadyForPickup,
            'canArchive' => $documentRequest->status === RequestStatus::Completed
                && ! $documentRequest->is_archived,
        ]);
    }

    public function cancel(CancelRequestRequest $request, DocumentRequest $documentRequest): RedirectResponse
    {
        return $this->perform($documentRequest, 'Your request was cancelled.', function () use ($request, $documentRequest) {
            $this->lifecycle->cancel($documentRequest, $request->user(), $request->validated('reason'));
        });
    }

    public function resubmitPayment(ResubmitPaymentRequest $request, DocumentRequest $documentRequest): RedirectResponse
    {
        return $this->perform($documentRequest, 'Payment details resubmitted. They will be re-checked automatically.', function () use ($request, $documentRequest) {
            $this->lifecycle->resubmitPayment(
                $documentRequest,
                $request->user(),
                $request->safe()->only(['payment_method', 'payment_reference', 'payment_amount', 'payment_paid_on']),
                $request->file('payment_proof'),
            );
        });
    }

    public function receive(OwnedRequestActionRequest $request, DocumentRequest $documentRequest): RedirectResponse
    {
        return $this->perform($documentRequest, 'Thank you. Your request is now marked as completed.', function () use ($request, $documentRequest) {
            $this->lifecycle->confirmReceived($documentRequest, $request->user());
        });
    }

    public function archive(OwnedRequestActionRequest $request, DocumentRequest $documentRequest): RedirectResponse
    {
        return $this->perform($documentRequest, 'Request archived. You can now submit a new request.', function () use ($request, $documentRequest) {
            $this->lifecycle->archive($documentRequest, $request->user());
        });
    }

    private function perform(DocumentRequest $documentRequest, string $success, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (RequestNotAllowedException $e) {
            return redirect()
                ->route('student.requests.show', $documentRequest)
                ->withInput()
                ->withErrors(['request' => $e->getMessage()]);
        }

        return redirect()
            ->route('student.requests.show', $documentRequest)
            ->with('status', $success);
    }

    private function blockingRequest(int $userId): ?DocumentRequest
    {
        return DocumentRequest::query()
            ->where('user_id', $userId)
            ->blocking()
            ->with('documentType')
            ->first();
    }

    /** Throttled, failure-tolerant nudge so pages stay fresh even without a scheduler. */
    private function tickAutomation(): void
    {
        try {
            $this->automation->runIfDue();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
