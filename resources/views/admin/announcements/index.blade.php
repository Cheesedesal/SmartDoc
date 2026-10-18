@extends('layouts.app')

@section('title', 'Manage announcements')

@php
    $audienceLabels = [
        'all' => 'Everyone', 'admin' => 'Administrators', 'staff' => 'Staff', 'student' => 'Students',
    ];
@endphp

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">ADMINISTRATION</p>
            <h1>Announcement Management</h1>
            <p class="page-subtitle">Published announcements appear on dashboards and the announcement feed for the chosen audience.</p>
        </div>
    </header>

    <div class="stats-grid">
        <div class="stat-card"><span class="stat-label">Total</span><span class="stat-value">{{ $counts['total'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Published</span><span class="stat-value">{{ $counts['published'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Draft</span><span class="stat-value">{{ $counts['draft'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Expired</span><span class="stat-value">{{ $counts['expired'] }}</span></div>
    </div>

    @if ($viewing)
        <section class="panel form-panel">
            <div class="panel-heading">
                <div><p class="eyebrow">PREVIEW</p><h2>{{ $viewing->title }}</h2></div>
                <a class="button button-ghost button-sm" href="{{ route('admin.announcements.index', request()->except('view')) }}">Close</a>
            </div>
            <div class="form-body">
                <p class="ann-body">{{ strip_tags($viewing->message) }}</p>
                <p class="muted">
                    {{ $audienceLabels[$viewing->audience->value] ?? $viewing->audience->value }} ·
                    {{ $viewing->status->value }} ·
                    Posted by {{ $viewing->author?->full_name ?? 'Unknown' }}
                    @if ($viewing->expires_at) · Visible until {{ $viewing->expires_at->format('F j, Y') }} @endif
                </p>
            </div>
        </section>
    @endif

    <section class="panel form-panel">
        <div class="panel-heading"><div><p class="eyebrow">SEARCH</p><h2>Announcement search</h2></div></div>
        <form class="form-body" method="GET" action="{{ route('admin.announcements.index') }}">
            <div class="adm-form-row">
                <div class="field">
                    <label for="q">Title</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" autocomplete="off">
                </div>
                <div class="field">
                    <label for="f-status">Status</label>
                    <select id="f-status" name="status">
                        <option value="">All</option>
                        @foreach (\App\Enums\AnnouncementStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="adm-form-submit">
                    <button class="button" type="submit">Search</button>
                    <a class="button button-ghost" href="{{ route('admin.announcements.index') }}">Clear</a>
                </div>
            </div>
        </form>
    </section>

    <section class="panel form-panel" id="ann-form">
        <div class="panel-heading">
            <div><p class="eyebrow">{{ $editing ? 'EDIT' : 'NEW' }}</p><h2>{{ $editing ? 'Update announcement' : 'Add announcement' }}</h2></div>
            @if ($editing)
                <a class="button button-ghost button-sm" href="{{ route('admin.announcements.index') }}">Cancel</a>
            @endif
        </div>
        <form class="form-body" method="POST" autocomplete="off"
              action="{{ $editing ? route('admin.announcements.update', $editing) : route('admin.announcements.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="field">
                <label for="title">Title</label>
                <input id="title" name="title" type="text" maxlength="200" required value="{{ old('title', $editing?->title) }}">
                @error('title') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="message">Message</label>
                <textarea id="message" name="message" rows="4" maxlength="5000" required>{{ old('message', $editing?->message) }}</textarea>
                @error('message') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="adm-form-row">
                <div class="field">
                    <label for="audience">Audience</label>
                    <select id="audience" name="audience" required>
                        @foreach ($audienceLabels as $value => $label)
                            <option value="{{ $value }}" @selected(old('audience', $editing?->audience?->value ?? 'all') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('audience') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        @foreach (\App\Enums\AnnouncementStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $editing?->status?->value ?? 'Draft') === $status->value)>{{ $status->value }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="expires_at">Expiration date <span class="muted">(optional)</span></label>
                    <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at', $editing?->expires_at?->toDateString()) }}">
                    @error('expires_at') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="adm-form-submit">
                    <button class="button" type="submit">{{ $editing ? 'Update Announcement' : '+ Add Announcement' }}</button>
                </div>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="table-wrap">
            <table class="data-table adm-table">
                <thead><tr><th>Title</th><th>Audience</th><th>Status</th><th>Published</th><th>Expires</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($announcements as $announcement)
                        <tr>
                            <td><strong>{{ $announcement->title }}</strong></td>
                            <td>{{ $audienceLabels[$announcement->audience->value] ?? $announcement->audience->value }}</td>
                            <td><span class="status-badge status-{{ strtolower($announcement->status->value) }}">{{ $announcement->status->value }}</span></td>
                            <td>{{ $announcement->published_at?->format('M j, Y') ?? '—' }}</td>
                            <td>{{ $announcement->expires_at?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                <div class="row-actions">
                                    <a class="button button-ghost button-sm" href="{{ route('admin.announcements.index', array_merge(request()->except('edit'), ['view' => $announcement->id])) }}">View</a>
                                    <a class="button button-ghost button-sm" href="{{ route('admin.announcements.index', array_merge(request()->except('view'), ['edit' => $announcement->id])) }}#ann-form">Edit</a>
                                    <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?')">
                                        @csrf @method('DELETE')
                                        <button class="button button-danger button-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state compact"><strong>No announcements found</strong><p>Add one above.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $announcements->links('pagination.smartdoc') }}
    </section>
@endsection
