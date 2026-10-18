<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>SmartDoc | Request Report</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    {{-- Standalone, print-oriented page (as in the reference): inline styles, no app layout or Vite asset. --}}
    <style>
        body { margin: 40px; color: #222; font-family: Arial, Helvetica, sans-serif; }
        h1 { margin: 0 0 5px; color: #0f5810; text-align: center; }
        .report-title { margin-bottom: 25px; text-align: center; }
        .report-title p { margin: 0; }
        .report-title .generated { margin-top: 6px; color: #666; font-size: 12px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .toolbar a, .toolbar button { padding: 10px 20px; border: 1px solid #0f5810; border-radius: 6px; color: #fff; background: #0f5810; font: inherit; font-size: 13px; text-decoration: none; cursor: pointer; }
        .toolbar a.ghost { color: #0f5810; background: #fff; }
        .filters { margin-bottom: 20px; font-size: 13px; }
        .summary { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .summary div { min-width: 150px; padding: 10px 16px; border: 1px solid #ccc; border-top: 4px solid #0f5810; border-radius: 6px; font-size: 12px; color: #555; }
        .summary strong { display: block; color: #0f5810; font-size: 20px; }
        h2 { margin: 24px 0 8px; color: #0f5810; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: center; }
        th { color: #fff; background: #0f5810; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .completed { color: #0f5810; font-weight: bold; }
        .rejected { color: #9b0000; font-weight: bold; }
        .no-records { padding: 20px; text-align: center; }
        .table-scroll { overflow-x: auto; }
        @media (max-width: 600px) { body { margin: 16px; } table { min-width: 560px; } }
        @media print {
            .toolbar { display: none; }
            body { margin: 15px; }
            .table-scroll { overflow: visible; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <a href="{{ route('request-reports.export', request()->query()) }}">Export CSV</a>
        <a class="ghost" href="{{ route('request-records.index', request()->query()) }}">Back to records</a>
    </div>

    <div class="report-title">
        <h1>SmartDoc</h1>
        <p>Request Record History Report</p>
        <p class="generated">Generated {{ $generatedAt->format('M. j, Y g:i A') }} by {{ $generatedBy }}</p>
    </div>

    <div class="filters">
        <strong>Filters:</strong>
        @if ($filterDescription === [])
            All Completed and Rejected Requests
        @else
            {{ implode(' | ', $filterDescription) }}
        @endif
    </div>

    <div class="summary">
        <div><strong>{{ $summary['total'] }}</strong>Total requests</div>
        <div><strong>{{ $summary['completed'] }}</strong>Completed</div>
        <div><strong>{{ $summary['rejected'] }}</strong>Rejected (legacy)</div>
    </div>

    @if ($summary['by_type'] !== [])
        <h2>Requests by document type</h2>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Document Type</th>
                        <th>Completed</th>
                        <th>Rejected</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['by_type'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['completed'] }}</td>
                            <td>{{ $row['rejected'] }}</td>
                            <td>{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2>Request records</h2>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Student Name</th>
                    <th>Document Type</th>
                    <th>Status</th>
                    <th>Requested At</th>
                </tr>
            </thead>
            <tbody>
                @php($rowCount = 0)
                @foreach ($requests as $documentRequest)
                    @php($rowCount++)
                    <tr>
                        <td>{{ $documentRequest->reference }}</td>
                        <td>{{ $documentRequest->student?->full_name ?? '—' }}</td>
                        <td>{{ $documentRequest->documentType?->name ?? '—' }}</td>
                        <td><span class="{{ $documentRequest->status === \App\Enums\RequestStatus::Completed ? 'completed' : 'rejected' }}">{{ $documentRequest->status->value }}</span></td>
                        <td>{{ $documentRequest->created_at?->format('M. j, Y g:i A') ?? '—' }}</td>
                    </tr>
                @endforeach
                @if ($rowCount === 0)
                    <tr>
                        <td colspan="5" class="no-records">No request records found.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</body>
</html>
