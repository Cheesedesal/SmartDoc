<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Services\RequestAutomation;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Read-only monitoring for staff and admins (route group: `auth` + `role:staff,admin`).
 * There are intentionally no status-changing actions here: processing is automated.
 */
class StaffRequestController extends Controller
{
    public function __construct(private readonly RequestAutomation $automation)
    {
    }

    public function index(Request $request): View
    {
        $this->tickAutomation();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'type' => (string) $request->query('type', ''),
            'archived' => in_array($request->query('archived'), ['active', 'archived', 'all'], true)
                ? $request->query('archived')
                : 'active',
        ];

        $query = DocumentRequest::query()->with(['student', 'documentType'])->latest('id');

        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';

            $query->where(function ($w) use ($filters, $like) {
                $w->whereHas('student', function ($s) use ($like) {
                    $s->where(fn ($x) => $x
                        ->where('school_id', 'like', $like)
                        ->orWhere('firstname', 'like', $like)
                        ->orWhere('surname', 'like', $like));
                })->orWhereHas('documentType', fn ($t) => $t->where('name', 'like', $like));

                // "REQ-007", "req007" or "7" match request #7.
                if (preg_match('/^(?:REQ-?)?0*(\d+)$/i', $filters['q'], $m)) {
                    $w->orWhere('id', (int) $m[1]);
                }
            });
        }

        if (RequestStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        if (ctype_digit($filters['type'])) {
            $query->where('document_type_id', (int) $filters['type']);
        }

        if ($filters['archived'] === 'active') {
            $query->where('is_archived', false);
        } elseif ($filters['archived'] === 'archived') {
            $query->where('is_archived', true);
        }

        // Port of reference getRequestCounts(): open requests only (the reference excluded Completed/Rejected).
        $open = DocumentRequest::query()->open()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('staff.requests.index', [
            'counts' => [
                'open' => (int) $open->sum(),
                'pending' => (int) ($open[RequestStatus::Pending->value] ?? 0),
                'in_progress' => (int) ($open[RequestStatus::InProgress->value] ?? 0),
                'ready' => (int) ($open[RequestStatus::ReadyForPickup->value] ?? 0),
            ],
            'requests' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'statuses' => RequestStatus::cases(),
            'types' => DocumentType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(DocumentRequest $documentRequest): View
    {
        $this->authorize('view', $documentRequest);
        $this->tickAutomation();

        $documentRequest->refresh()->load([
            'student.department',
            'documentType.requirements',
            'uploads.requirement',
            'aiLogs',
        ]);

        return view('staff.requests.show', ['documentRequest' => $documentRequest]);
    }

    private function tickAutomation(): void
    {
        try {
            $this->automation->runIfDue();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
