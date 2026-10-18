<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Services\RequestRecordFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only request record history for staff and admins (route: `auth` + `role:staff,admin`).
 * Port of the reference requestrecords.php: finished requests only (Completed, plus legacy
 * Rejected rows), with search, document, status and date filters. Filtering lives in
 * RequestRecordFilter so the printable report and CSV export use exactly the same rules.
 */
class RequestRecordController extends Controller
{
    public function __construct(private readonly RequestRecordFilter $records)
    {
    }

    public function index(Request $request): View
    {
        // Invalid filter values are ignored (and reported) instead of redirecting, so a
        // hand-edited URL never produces a broken page or a query on unchecked input.
        ['filters' => $filters, 'errors' => $errors] = $this->records->resolve($request->query());

        $query = $this->records->query($filters)
            ->with(['student', 'documentType'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

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
            // Only the valid, non-empty filters, so the report links never redirect back here.
            'reportQuery' => array_filter($filters, fn ($value) => $value !== ''),
            'filterErrors' => $errors,
            'statuses' => [RequestStatus::Completed, RequestStatus::Rejected],
            'types' => DocumentType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
