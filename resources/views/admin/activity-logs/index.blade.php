@extends('layouts.portal')

@section('title', 'Activity logs')
@section('heading', 'Activity logs')
@section('subtitle', 'Review account and system changes. Credentials and private files are never included.')

@section('content')
    <section class="panel module-panel">
        @php
            $activeRole = request('role', 'all');
            $roleTabs = ['all' => 'All', 'admin' => 'Admin', 'staff' => 'Staff', 'student' => 'Student'];
        @endphp
        <nav class="segmented-tabs log-role-tabs" style="--tab-count: 4; --active-index: {{ array_search($activeRole, array_keys($roleTabs), true) ?: 0 }}" aria-label="Filter activity by account role">
            @foreach($roleTabs as $role => $label)
                @php
                    $query = request()->query();
                    $query['role'] = $role;
                    unset($query['page']);
                @endphp
                <a class="{{ $activeRole === $role ? 'active' : '' }}" href="{{ route('admin.activity-logs.index', $query) }}" @if($activeRole === $role) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" class="filter-bar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Filter by user or record">
            <input type="hidden" name="role" value="{{ $activeRole }}">
            <select name="action"><option value="">All activity</option>@foreach (['login' => 'Login', 'created' => 'Created', 'updated' => 'Updated', 'archived' => 'Archived', 'restored' => 'Restored'] as $action => $label)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $label }}</option>@endforeach</select>
            <button class="secondary-button" type="submit">Filter</button>
            <a class="text-action" href="{{ route('admin.activity-logs.index', ['role' => $activeRole]) }}">Clear</a>
            <a class="secondary-button" href="{{ route('admin.activity-logs.export', request()->query()) }}">Export CSV</a>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>User</th><th>Activity Details</th><th>Date &amp; Time</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="hover-row" tabindex="0">
                            <td>{{ $log->actor?->full_name ?? 'System' }}<span class="cell-subtitle">{{ $log->actor?->role?->label() ?? 'Automated event' }}</span><span class="hover-card">{{ implode("\n", [$log->actor?->email ?? 'Automated system event', 'IP: '.($log->ip_address ?? 'Unavailable')]) }}</span></td>
                            <td>{{ $log->activity_description }}</td>
                            <td>{{ $log->created_at->format('M j, Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state compact"><strong>No activity has been logged yet.</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $logs->links() }}</div>
    </section>
@endsection
