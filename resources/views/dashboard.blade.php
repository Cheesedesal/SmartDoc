@extends('layouts.portal')

@section('title', $role->label().' dashboard')
@section('heading', $role->label().' dashboard')
@section('subtitle', 'Welcome back, '.($user->firstname ?: $user->full_name).'. Here is your latest overview.')

@section('content')

        <a class="assistant-shortcut" href="{{ route('chatbot.index') }}">
            <span class="assistant-shortcut-icon" aria-hidden="true">✦</span>
            <span>
                <strong>Need help with a document request?</strong>
                <small>Ask the SmartDoc assistant about fees, requirements, and procedures.</small>
            </span>
            <span class="assistant-shortcut-action">Ask a question&nbsp; →</span>
        </a>

        @if ($role === \App\Enums\Role::Admin)
            <section class="stats-grid admin-stats" aria-label="System overview">
                <article class="stat-card">
                    <span class="stat-label">Total users</span>
                    <strong class="stat-value">{{ number_format($adminStats['total_users']) }}</strong>
                    <span class="stat-note">Registered accounts</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Students</span>
                    <strong class="stat-value">{{ number_format($adminStats['students']) }}</strong>
                    <span class="stat-note">Student accounts</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Staff</span>
                    <strong class="stat-value">{{ number_format($adminStats['staff']) }}</strong>
                    <span class="stat-note">Staff accounts</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Open requests</span>
                    <strong class="stat-value">{{ number_format($requestCounts['pending'] + $requestCounts['in_progress'] + $requestCounts['ready_for_pickup']) }}</strong>
                    <span class="stat-note">Requests needing attention</span>
                </article>
            </section>
            <section class="summary-strip" aria-label="System configuration">
                <div><span>Active users</span><strong>{{ number_format($adminStats['active_users']) }}</strong></div>
                <div><span>Departments</span><strong>{{ number_format($adminStats['departments']) }}</strong></div>
                <div><span>Document types</span><strong>{{ number_format($adminStats['document_types']) }}</strong></div>
                <div><span>All requests</span><strong>{{ number_format($requestCounts['total']) }}</strong></div>
            </section>
        @elseif ($role === \App\Enums\Role::Staff)
            <section class="stats-grid" aria-label="Request queue overview">
                <article class="stat-card">
                    <span class="stat-label">Open requests</span>
                    <strong class="stat-value">{{ number_format($requestCounts['total'] - $requestCounts['completed'] - $requestCounts['rejected']) }}</strong>
                    <span class="stat-note">Active in the queue</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Pending</span>
                    <strong class="stat-value">{{ number_format($requestCounts['pending']) }}</strong>
                    <span class="stat-note">Awaiting review</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">In progress</span>
                    <strong class="stat-value">{{ number_format($requestCounts['in_progress']) }}</strong>
                    <span class="stat-note">Being processed</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Ready for pickup</span>
                    <strong class="stat-value">{{ number_format($requestCounts['ready_for_pickup']) }}</strong>
                    <span class="stat-note">Awaiting student collection</span>
                </article>
            </section>
        @else
            <section class="stats-grid" aria-label="My request overview">
                <article class="stat-card">
                    <span class="stat-label">My requests</span>
                    <strong class="stat-value">{{ number_format($requestCounts['total']) }}</strong>
                    <span class="stat-note">All request statuses</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Pending</span>
                    <strong class="stat-value">{{ number_format($requestCounts['pending']) }}</strong>
                    <span class="stat-note">Awaiting review</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">In progress</span>
                    <strong class="stat-value">{{ number_format($requestCounts['in_progress']) }}</strong>
                    <span class="stat-note">Being processed</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Ready for pickup</span>
                    <strong class="stat-value">{{ number_format($requestCounts['ready_for_pickup']) }}</strong>
                    <span class="stat-note">{{ number_format($availableDocumentTypes) }} document types available</span>
                </article>
            </section>
        @endif

        <div class="dashboard-columns">
            <section class="panel request-panel" id="requests">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">{{ $role === \App\Enums\Role::Student ? 'TRACKING' : 'WORKFLOW' }}</p>
                        <h2>{{ $role === \App\Enums\Role::Student ? 'Recent requests' : 'Recent request activity' }}</h2>
                    </div>
                    <span class="count-pill">{{ $recentRequests->count() }} shown</span>
                </div>

                @if ($recentRequests->isEmpty())
                    <div class="empty-state">
                        <span class="empty-icon" aria-hidden="true">▤</span>
                        <strong>{{ $role === \App\Enums\Role::Student ? 'No requests yet' : 'The request queue is clear' }}</strong>
                        <p>{{ $role === \App\Enums\Role::Student ? 'Your document requests will appear here after submission.' : 'New active document requests will appear here.' }}</p>
                    </div>
                @else
                    <div class="request-list">
                        @foreach ($recentRequests as $documentRequest)
                            <article class="request-row">
                                <div class="request-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($documentRequest->documentType?->name ?? 'D', 0, 1)) }}</div>
                                <div class="request-info">
                                    <strong>{{ $documentRequest->documentType?->name ?? 'Document request' }}</strong>
                                    <span>
                                        {{ $documentRequest->reference }}
                                        @if ($role !== \App\Enums\Role::Student && $documentRequest->student)
                                            <span class="dot-separator">·</span>{{ $documentRequest->student->full_name }}
                                        @endif
                                    </span>
                                </div>
                                <div class="request-meta">
                                    <span class="status-badge status-{{ \Illuminate\Support\Str::slug($documentRequest->status->value) }}">{{ $documentRequest->status->value }}</span>
                                    <small>{{ $documentRequest->created_at?->format('M j, Y') }}</small>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="panel announcement-panel" id="announcements">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">CAMPUS UPDATES</p>
                        <h2>Announcements</h2>
                    </div>
                    <span class="announcement-icon" aria-hidden="true">◉</span>
                </div>

                @forelse ($announcements as $announcement)
                    <article class="announcement-item" id="announcement-{{ $announcement->id }}">
                        <div class="announcement-date">
                            {{ $announcement->published_at?->format('M j') ?? 'Update' }}
                        </div>
                        <div>
                            <h3>{{ $announcement->title }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit(strip_tags($announcement->message), 130) }} <a class="announcement-view-link" href="{{ route('announcements.index') }}#announcement-{{ $announcement->id }}">View announcement</a></p>
                        </div>
                    </article>
                @empty
                    <div class="empty-state compact">
                        <strong>No announcements</strong>
                        <p>New updates for your account will appear here.</p>
                    </div>
                @endforelse
            </section>
        </div>
@endsection
