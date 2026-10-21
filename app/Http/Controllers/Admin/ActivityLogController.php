<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = $this->filteredQuery($request)
            ->with(['actor', 'subject'])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request)->with(['actor', 'subject'])->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['User', 'Activity Details', 'Date & Time']);
            foreach ($query->lazyById(500) as $log) {
                fputcsv($output, array_map($this->spreadsheetSafe(...), [
                    $log->actor?->full_name ?? 'System',
                    $log->activity_description,
                    $log->created_at?->format('M j, Y g:i A') ?? '',
                ]));
            }
            fclose($output);
        }, 'smartdoc-activity-logs-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredQuery(Request $request)
    {
        $request->validate([
            'role' => ['nullable', Rule::in(['all', 'admin', 'staff', 'student'])],
            'action' => ['nullable', Rule::in(['created', 'updated', 'archived', 'restored', 'login'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ActivityLog::query()
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where('subject_label', 'like', $search)
                    ->orWhere('subject_type', 'like', $search)
                    ->orWhereHas('actor', fn ($actor) => $actor->where('firstname', 'like', $search)->orWhere('surname', 'like', $search));
            }))
            ->when($request->filled('role'), function ($query) use ($request) {
                $role = $request->string('role')->toString();
                if ($role !== 'all') {
                    $query->whereHas('actor', fn ($actor) => $actor->where('role', $role));
                }
            });
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
