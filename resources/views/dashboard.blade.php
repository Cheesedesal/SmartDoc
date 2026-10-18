@extends('layouts.app')

@section('title', $role->label().' Dashboard')

@section('content')
        <header class="page-header">
            <div>
                <p class="eyebrow">SMARTDOC PORTAL</p>
                <h1>{{ $role->label() }} dashboard</h1>
                <p class="page-subtitle">Welcome back, {{ $user->firstname ?: $user->full_name }}. Here’s your latest overview.</p>
            </div>
            <div class="header-date">{{ now()->format('l, F j, Y') }}</div>
        </header>

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
                    <strong class="stat-value">{{ number_format($requestCounts['open']) }}</strong>
                    <span class="stat-note">Being processed automatically</span>
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
                    <strong class="stat-value">{{ number_format($requestCounts['open']) }}</strong>
                    <span class="stat-note">Active in the queue</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Pending</span>
                    <strong class="stat-value">{{ number_format($requestCounts['pending']) }}</strong>
                    <span class="stat-note">Awaiting automatic check</span>
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
                    <span class="stat-note">Awaiting automatic check</span>
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
                    <a class="count-pill" href="{{ $role === \App\Enums\Role::Student ? route('student.requests.index') : route('staff.requests.index') }}">View all</a>
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
                    <article class="announcement-item">
                        <div class="announcement-date">
                            {{ $announcement->published_at?->format('M j') ?? 'Update' }}
                        </div>
                        <div>
                            <h3>{{ $announcement->title }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit(strip_tags($announcement->message), 130) }}</p>
                        </div>
                    </article>
                @empty
                    <div class="empty-state compact">
                        <strong>No announcements</strong>
                        <p>New updates for your account will appear here.</p>
                    </div>
                @endforelse

                @if ($announcements->isNotEmpty())
                    <div class="announcement-more">
                        <a class="count-pill" href="{{ route('announcements.index') }}">View all announcements</a>
                    </div>
                @endif
            </section>
        </div>
@endsection
