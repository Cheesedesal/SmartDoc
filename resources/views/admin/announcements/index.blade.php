@extends('layouts.portal')

@section('title', 'Announcement management')
@section('heading', 'Announcement management')
@section('subtitle', 'Publish role-targeted notices and manage drafts and expiry dates.')

@section('content')
    <section class="stats-grid">
        @foreach (['total' => 'Total', 'published' => 'Published', 'draft' => 'Drafts', 'expired' => 'Expired'] as $key => $label)
            <article class="stat-card"><span class="stat-label">{{ $label }}</span><strong class="stat-value">{{ number_format($counts[$key]) }}</strong></article>
        @endforeach
    </section>
    <section class="panel module-panel">
        <form method="GET" class="filter-bar"><input type="search" name="search" value="{{ request('search') }}" placeholder="Search announcement title"><select name="status"><option value="">All statuses</option>@foreach (\App\Enums\AnnouncementStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->value }}</option>@endforeach</select><select name="archived"><option value="0" @selected(! request()->boolean('archived'))>Current announcements</option><option value="1" @selected(request()->boolean('archived'))>Archived announcements</option></select><button class="secondary-button" type="submit">Filter</button><a class="text-action" href="{{ route('admin.announcements.index') }}">Clear</a>@unless(request()->boolean('archived'))<a class="secondary-button" href="{{ route('admin.imports.show', 'announcements') }}">Bulk import</a>@endunless</form>
        <form id="announcements-bulk-form" method="POST" action="{{ route(request()->boolean('archived') ? 'admin.announcements.bulk-restore' : 'admin.announcements.bulk-archive') }}">@csrf
            <div class="bulk-actions"><strong>{{ request()->boolean('archived') ? 'Restore selected announcements' : 'Archive selected announcements' }}</strong><button class="secondary-button" type="submit" data-confirm="Apply this action to all selected announcements?">{{ request()->boolean('archived') ? 'Restore selected' : 'Archive selected' }}</button></div>
        </form>
        @unless(request()->boolean('archived'))
            <div class="table-toolbar"><button class="primary-button" type="button" data-modal-open="announcement-modal" data-action="{{ route('admin.announcements.store') }}" data-values="{}">+ Add announcement</button></div>
        @endunless
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th><input type="checkbox" data-select-all="announcements-bulk-form" aria-label="Select all announcements on this page"></th><th>Title</th><th>Audience</th><th>Published</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($announcements as $announcement)
                        <tr class="hover-row" tabindex="0">
                            <td><input type="checkbox" name="ids[]" value="{{ $announcement->id }}" form="announcements-bulk-form" aria-label="Select {{ $announcement->title }}"></td>
                            <td><strong>{{ $announcement->title }}</strong><span class="hover-card">{{ implode("\n", [$announcement->message, 'Created by '.($announcement->author?->full_name ?? 'System'), $announcement->expires_at ? 'Expires '.$announcement->expires_at->format('M j, Y') : null]) }}</span></td>
                            <td>{{ $announcement->audience->value === 'all' ? 'Everyone' : ucfirst($announcement->audience->value) }}</td>
                            <td>{{ $announcement->published_at?->format('M j, Y') ?? '—' }}</td>
                            <td><span class="status-badge">{{ $announcement->archived_at ? 'Archived' : $announcement->status->value }}</span></td>
                            <td class="action-cell">@if(request()->boolean('archived'))
                                <form method="POST" action="{{ route('admin.announcements.restore', $announcement) }}">@csrf<button class="text-action" type="submit">Restore</button></form>
                            @else
                                <button class="text-action" type="button" data-modal-open="announcement-modal" data-action="{{ route('admin.announcements.update', $announcement) }}" data-method="PUT" data-values="{{ json_encode(['title' => $announcement->title, 'message' => $announcement->message, 'audience' => $announcement->audience->value, 'status' => $announcement->status->value === 'Expired' ? 'Draft' : $announcement->status->value, 'expires_at' => $announcement->expires_at?->format('Y-m-d')]) }}">Edit</button>
                                <form method="POST" action="{{ route('admin.announcements.archive', $announcement) }}" data-confirm="Archive this announcement?">@csrf<button class="danger-text" type="submit">Archive</button></form>
                            @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state compact"><strong>No announcements found</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $announcements->links() }}</div>
    </section>
    <dialog class="form-modal wide-modal" id="announcement-modal">
        <form method="POST" action="{{ route('admin.announcements.store') }}" class="modal-form" data-modal-form>
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_form_action" value="{{ old('_form_action') }}"><input type="hidden" name="_form_method" value="{{ old('_form_method') }}"><input type="hidden" name="_form_target" value="{{ old('_form_target') }}">
            <div class="modal-heading"><div><p class="eyebrow">ANNOUNCEMENT</p><h2>Add or edit announcement</h2></div><button class="icon-button" type="button" data-modal-close aria-label="Close">×</button></div>
            <label>Title<input name="title" value="{{ old('title') }}" maxlength="200" required></label>
            <div class="form-grid"><label>Audience<select name="audience" required>@foreach (\App\Enums\Audience::cases() as $audience)<option value="{{ $audience->value }}" @selected(old('audience', 'all') === $audience->value)>{{ $audience->value === 'all' ? 'Everyone' : ucfirst($audience->value) }}</option>@endforeach</select></label>
            <label>Status<select name="status" required><option value="Draft" @selected(old('status', 'Draft') === 'Draft')>Draft</option><option value="Published" @selected(old('status') === 'Published')>Published</option></select></label>
            <label>Expires on (optional)<input type="date" name="expires_at" value="{{ old('expires_at') }}"></label></div>
            <label>Message<textarea name="message" rows="6" maxlength="20000" required>{{ old('message') }}</textarea></label>
            <div class="modal-actions"><button class="secondary-button" type="button" data-modal-close>Cancel</button><button class="primary-button" type="submit">Save announcement</button></div>
        </form>
    </dialog>
@endsection
