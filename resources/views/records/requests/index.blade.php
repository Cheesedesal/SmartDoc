@extends('layouts.app')

@section('title', 'Request record history')

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">HISTORY</p>
            <h1>Request Record History</h1>
            <p class="page-subtitle">Completed requests, and legacy rejected requests kept from the old system. This view is read-only.</p>
        </div>
    </header>

    <div class="stats-grid stats-grid-3">
        <div class="stat-card"><span class="stat-label">Total requests</span><span class="stat-value">{{ $counts['total'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Completed requests</span><span class="stat-value">{{ $counts['completed'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Rejected requests (legacy)</span><span class="stat-value">{{ $counts['rejected'] }}</span></div>
    </div>

    <section class="panel report-box" aria-labelledby="report-heading">
        <div class="report-info">
            <h2 id="report-heading">Generate Request Report</h2>
            <p>Create downloadable reports based on request history. Reports use the filters below.</p>
        </div>
        <div class="report-actions">
            <a class="button" href="{{ route('request-reports.show', $reportQuery) }}" target="_blank" rel="noopener">Generate PDF Report</a>
            <a class="button button-ghost" href="{{ route('request-reports.export', $reportQuery) }}">Export CSV</a>
        </div>
    </section>

    <section class="panel queue-panel">
        <form class="queue-filters records-filters" method="GET" action="{{ route('request-records.index') }}" role="search">
            <div class="field">
                <label for="search">Search</label>
                <input id="search" name="search" type="search" maxlength="100" placeholder="Search Student or Request ID" value="{{ $filters['search'] }}">
                @if ($filterErrors->has('search'))<p class="field-error">The search text may not be longer than 100 characters.</p>@endif
            </div>
            <div class="field">
                <label for="document_type_id">Document</label>
                <select id="document_type_id" name="document_type_id">
                    <option value="">All Documents</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected($filters['document_type_id'] === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                @if ($filterErrors->has('document_type_id'))<p class="field-error">{{ $filterErrors->first('document_type_id') }}</p>@endif
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
                    @endforeach
                </select>
                @if ($filterErrors->has('status'))<p class="field-error">Choose Completed or Rejected.</p>@endif
            </div>
            <div class="field">
                <label for="date_from">Requested from</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}">
                @if ($filterErrors->has('date_from'))<p class="field-error">Enter a valid date.</p>@endif
            </div>
            <div class="field">
                <label for="date_to">Requested to</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}">
                @if ($filterErrors->has('date_to'))<p class="field-error">The end date must be a valid date on or after the start date.</p>@endif
            </div>
            <div class="queue-filter-actions">
                <button class="button" type="submit">Search</button>
                <a class="button button-ghost" href="{{ route('request-records.index') }}">Clear</a>
            </div>
        </form>

        @if ($requests->isEmpty())
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true">◷</span>
                <strong>No request records found</strong>
                <p>Try a different search or filter.</p>
            </div>
        @else
            <p class="queue-count" role="status">Showing {{ number_format($requests->firstItem()) }}–{{ number_format($requests->lastItem()) }} of {{ number_format($requests->total()) }} {{ \Illuminate\Support\Str::plural('record', $requests->total()) }}</p>
            <div class="queue-table-wrap">
                <table class="queue-table records-table">
                    <thead>
                        <tr>
                            <th scope="col">Student Name</th>
                            <th scope="col">Document Type</th>
                            <th scope="col">Requested At</th>
                            <th scope="col">Status</th>
                            <th scope="col">Rejected / Completed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $documentRequest)
                            @php
                                $finishedAt = $documentRequest->status === \App\Enums\RequestStatus::Completed
                                    ? $documentRequest->completed_at
                                    : $documentRequest->rejected_at;
                            @endphp
                            <tr>
                                <td class="queue-wide" data-label="Student Name">{{ $documentRequest->student?->full_name ?? '—' }}</td>
                                <td class="queue-wide" data-label="Document Type">{{ $documentRequest->documentType?->name ?? '—' }}</td>
                                <td data-label="Requested At">{{ $documentRequest->created_at?->format('M. j, Y g:i A') ?? '—' }}</td>
                                <td data-label="Status"><span class="status-badge status-{{ \Illuminate\Support\Str::slug($documentRequest->status->value) }}">{{ $documentRequest->status->value }}</span></td>
                                <td data-label="Rejected / Completed At">{{ $finishedAt?->format('M. j, Y g:i A') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $requests->links('pagination.smartdoc') }}
        @endif
    </section>
@endsection
