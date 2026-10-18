<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only request record history for staff and admins (route: `auth` + `role:staff,admin`).
 * Port of the reference requestrecords.php: finished requests only (Completed, plus legacy
 * Rejected rows), with search, document, status and date filters. No actions, no student data
 * beyond what the request queue already shows.
 */
class RequestRecordController extends Controller
{
    public function index(Request $request): View
    {
        // Invalid filter values are ignored (and reported) instead of redirecting, so a
        // hand-edited URL never produces a broken page or a query on unchecked input.
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'document_type_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(RequestStatus::recordValues())],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $valid = $validator->valid();

        $filters = [
            'search' => trim((string) ($valid['search'] ?? '')),
            'document_type_id' => (string) ($valid['document_type_id'] ?? ''),
            'status' => (string) ($valid['status'] ?? ''),
            'date_from' => (string) ($valid['date_from'] ?? ''),
            'date_to' => (string) ($valid['date_to'] ?? ''),
        ];

        $query = DocumentRequest::query()
            ->records()
            ->with(['student', 'documentType'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($w) use ($search) {
                // Every word must match the student's first name, surname or school ID.
                $w->whereHas('student', function ($s) use ($search) {
                    foreach (preg_split('/\s+/', $search) ?: [] as $term) {
                        $like = '%'.addcslashes($term, '%_\\').'%';

                        $s->where(fn ($x) => $x
                            ->where('firstname', 'like', $like)
                            ->orWhere('surname', 'like', $like)
                            ->orWhere('school_id', 'like', $like));
                    }
                });

                // "REQ-007", "req007" or "7" match request #7 (same rule as the request queue).
                if (preg_match('/^(?:REQ-?)?0*(\d+)$/i', $search, $m)) {
                    $w->orWhere('id', (int) $m[1]);
                }
            });
        }

        if ($filters['document_type_id'] !== '') {
            $query->where('document_type_id', (int) $filters['document_type_id']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        // The reference filters on the date the request was made (requested_at == created_at).
        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Summary cards always describe the whole history, not the filtered rows.
        $byStatus = DocumentRequest::query()
            ->records()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('records.requests.index', [
            'counts' => [
                'total' => (int) $byStatus->sum(),
                'completed' => (int) ($byStatus[RequestStatus::Completed->value] ?? 0),
                'rejected' => (int) ($byStatus[RequestStatus::Rejected->value] ?? 0),
            ],
            'requests' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'filterErrors' => $validator->errors(),
            'statuses' => [RequestStatus::Completed, RequestStatus::Rejected],
            'types' => DocumentType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
