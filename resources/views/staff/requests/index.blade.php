@extends('layouts.app')

@section('title', 'Request queue')

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">WORKFLOW</p>
            <h1>Request queue</h1>
            <p class="page-subtitle">Requests are verified and advanced automatically. This view is for monitoring.</p>
        </div>
    </header>

    <div class="stats-grid">
        <div class="stat-card"><span class="stat-label">Open requests</span><span class="stat-value">{{ $counts['open'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Pending</span><span class="stat-value">{{ $counts['pending'] }}</span></div>
        <div class="stat-card"><span class="stat-label">In progress</span><span class="stat-value">{{ $counts['in_progress'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Ready for pick-up</span><span class="stat-value">{{ $counts['ready'] }}</span></div>
    </div>

    <section class="panel queue-panel">
        <form class="queue-filters" method="GET" action="{{ route('staff.requests.index') }}" role="search">
            <div class="field">
                <label for="q">Search</label>
                <input id="q" name="q" type="search" maxlength="100" placeholder="Student, school ID, document or REQ-007" value="{{ $filters['q'] }}">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="type">Document</label>
                <select id="type" name="type">
                    <option value="">All documents</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected($filters['type'] === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="archived">Archive</label>
                <select id="archived" name="archived">
                    <option value="active" @selected($filters['archived'] === 'active')>Not archived</option>
                    <option value="archived" @selected($filters['archived'] === 'archived')>Archived only</option>
                    <option value="all" @selected($filters['archived'] === 'all')>Everything</option>
                </select>
            </div>
            <div class="queue-filter-actions">
                <button class="button" type="submit">Filter</button>
                <a class="button button-ghost" href="{{ route('staff.requests.index') }}">Reset</a>
            </div>
        </form>

        @if ($requests->isEmpty())
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true">▤</span>
                <strong>No requests found</strong>
                <p>Try a different search or filter.</p>
            </div>
        @else
            <p class="queue-count" role="status">Showing {{ number_format($requests->firstItem()) }}–{{ number_format($requests->lastItem()) }} of {{ number_format($requests->total()) }} {{ \Illuminate\Support\Str::plural('request', $requests->total()) }}</p>
            <div class="queue-table-wrap">
                <table class="queue-table">
                    <thead>
                        <tr>
                            <th scope="col">Reference</th>
                            <th scope="col">Student</th>
                            <th scope="col">Document</th>
                            <th scope="col">Status</th>
                            <th scope="col">Stage</th>
                            <th scope="col">Payment</th>
                            <th scope="col">Submitted</th>
                            <th scope="col"><span class="visually-hidden">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $documentRequest)
                            <tr>
                                <td class="queue-wide queue-ref" data-label="Reference">{{ $documentRequest->reference }}@if ($documentRequest->is_archived) <span class="queue-tag">Archived</span>@endif</td>
                                <td class="queue-wide queue-student" data-label="Student">
                                    <strong>{{ $documentRequest->student?->full_name ?? '—' }}</strong>
                                    <span>{{ $documentRequest->student?->school_id }}</span>
                                </td>
                                <td class="queue-wide queue-doc" data-label="Document">{{ $documentRequest->documentType?->name ?? '—' }}</td>
                                <td data-label="Status"><span class="status-badge status-{{ \Illuminate\Support\Str::slug($documentRequest->status->value) }}">{{ $documentRequest->status->value }}</span></td>
                                <td class="queue-stage" data-label="Stage">
                                    <span>{{ $documentRequest->current_stage?->value ?? '—' }}</span>
                                    <span class="queue-progress" aria-hidden="true"><i style="width: {{ (int) $documentRequest->progress }}%"></i></span>
                                </td>
                                <td data-label="Payment"><span class="status-badge queue-badge-payment status-{{ \Illuminate\Support\Str::slug($documentRequest->payment_status->value) }}">{{ $documentRequest->payment_status->value }}</span></td>
                                <td class="queue-date" data-label="Submitted">{{ $documentRequest->created_at?->format('M j, Y') }}</td>
                                <td class="queue-wide queue-action"><a class="queue-view" href="{{ route('staff.requests.show', $documentRequest) }}">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $requests->links('pagination.smartdoc') }}
        @endif
    </section>
@endsection
