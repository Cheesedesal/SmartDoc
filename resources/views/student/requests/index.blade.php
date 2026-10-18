@extends('layouts.app')

@section('title', 'My requests')

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">TRACKING</p>
            <h1>My requests</h1>
            <p class="page-subtitle">Your document requests and where each one stands.</p>
        </div>
        @if ($blocking === null)
            <a class="button" href="{{ route('student.requests.create') }}">New request</a>
        @endif
    </header>

    @if ($blocking !== null)
        <div class="alert alert-info" role="note">
            You can have one request at a time. <strong>{{ $blocking->reference }}</strong>
            ({{ $blocking->status->value }}) is still active
            @if ($blocking->status === \App\Enums\RequestStatus::Completed)
                &mdash; archive it to submit a new request.
            @else
                &mdash; you can submit a new request once it is completed and archived.
            @endif
            <a href="{{ route('student.requests.show', $blocking) }}">Open it</a>
        </div>
    @endif

    <div class="stats-grid">
        <div class="stat-card"><span class="stat-label">Total requests</span><span class="stat-value">{{ $counts['total'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Pending</span><span class="stat-value">{{ $counts['pending'] }}</span></div>
        <div class="stat-card"><span class="stat-label">In progress</span><span class="stat-value">{{ $counts['in_progress'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Ready for pick-up</span><span class="stat-value">{{ $counts['ready'] }}</span></div>
    </div>

    <section class="panel form-panel">
        <form class="form-body adm-form-row" method="GET" action="{{ route('student.requests.index') }}" role="search">
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
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="adm-form-submit">
                <button class="button" type="submit">Search</button>
                <a class="button button-ghost" href="{{ route('student.requests.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">HISTORY</p>
                <h2>All requests</h2>
            </div>
            <span class="count-pill">{{ $requests->total() }} total</span>
        </div>

        @if ($requests->isEmpty())
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true">▤</span>
                <strong>No requests yet</strong>
                <p>Your document requests will appear here after you submit one.</p>
            </div>
        @else
            <div class="request-list">
                @foreach ($requests as $documentRequest)
                    <a class="request-row request-link" href="{{ route('student.requests.show', $documentRequest) }}">
                        <div class="request-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($documentRequest->documentType?->name ?? 'D', 0, 1)) }}</div>
                        <div class="request-info">
                            <strong>{{ $documentRequest->documentType?->name ?? 'Document request' }}</strong>
                            <span>
                                {{ $documentRequest->reference }}
                                <span class="dot-separator">·</span>{{ $documentRequest->current_stage?->value }}
                                @if ($documentRequest->is_archived)
                                    <span class="dot-separator">·</span>Archived
                                @endif
                                @if ($documentRequest->payment_status === \App\Enums\PaymentStatus::Flagged)
                                    <span class="dot-separator">·</span><strong class="text-warn">Payment needs attention</strong>
                                @endif
                            </span>
                        </div>
                        <div class="request-meta">
                            <span class="status-badge status-{{ \Illuminate\Support\Str::slug($documentRequest->status->value) }}">{{ $documentRequest->status->value }}</span>
                            <small>{{ $documentRequest->created_at?->format('M j, Y') }}</small>
                        </div>
                    </a>
                @endforeach
            </div>
            {{ $requests->links('pagination.smartdoc') }}
        @endif
    </section>
@endsection
