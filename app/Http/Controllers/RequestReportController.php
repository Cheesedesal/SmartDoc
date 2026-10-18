<?php

namespace App\Http\Controllers;

use App\Services\RequestRecordFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports over the request-record history (route group: `auth` + `role:staff,admin`).
 * Port of the reference requestreport.php: a printable page ("Print / Save as PDF") and a CSV
 * export, both driven by the same validated filters as the records page. Read-only; nothing here
 * writes to the database. Unlike the reference, students cannot reach these URLs.
 */
class RequestReportController extends Controller
{
    /** Columns of the reference CSV export, in order. */
    private const CSV_HEADINGS = [
        'Request ID', 'Student Name', 'Email', 'Course', 'Year', 'Section', 'Document Type',
        'Status', 'Requested At', 'Completed At', 'Rejected At', 'Rejection Reason',
    ];

    private const DATE_FORMAT = 'M. j, Y g:i A';

    public function __construct(private readonly RequestRecordFilter $records)
    {
    }

    /** Printable report: filters used, summary counts, and one row per request. */
    public function show(Request $request): View|RedirectResponse
    {
        ['filters' => $filters, 'errors' => $errors] = $this->records->resolve($request->query());

        // A report built from silently-dropped filters would not match what the user asked for.
        if ($errors->isNotEmpty()) {
            return redirect()->route('request-records.index', $request->query());
        }

        $summary = $this->records->summary($filters);

        return view('reports.request-records', [
            'filterDescription' => $this->records->describe($filters),
            'summary' => $summary,
            'requests' => $this->orderedRows($filters)->lazy(500),
            'generatedAt' => now(),
            'generatedBy' => $request->user()->full_name,
        ]);
    }

    /** CSV download of exactly the rows the printable report would list. */
    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        ['filters' => $filters, 'errors' => $errors] = $this->records->resolve($request->query());

        if ($errors->isNotEmpty()) {
            return redirect()->route('request-records.index', $request->query());
        }

        $rows = $this->orderedRows($filters);
        $filename = 'request_report_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // UTF-8 byte-order mark so Excel keeps accented names (ñ, é) intact.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CSV_HEADINGS, ',', '"', '');

            foreach ($rows->lazy(500) as $record) {
                $student = $record->student;

                fputcsv($out, array_map($this->safeCell(...), [
                    $record->reference,
                    $student?->full_name,
                    $student?->email,
                    $student?->course,
                    $student?->year,
                    $student?->section,
                    $record->documentType?->name,
                    $record->status->value,
                    $record->created_at?->format(self::DATE_FORMAT),
                    $record->completed_at?->format(self::DATE_FORMAT),
                    $record->rejected_at?->format(self::DATE_FORMAT),
                    $record->rejection_reason,
                ]), ',', '"', '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param  array<string, string>  $filters */
    private function orderedRows(array $filters)
    {
        return $this->records->query($filters)
            ->with(['student', 'documentType'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Neutralise spreadsheet formula injection: a cell starting with = + - @ (or a tab/CR) would be
     * executed by Excel or Sheets, and student-supplied names and emails end up in this file.
     */
    private function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }
}
