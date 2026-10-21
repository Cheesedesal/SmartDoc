@extends('layouts.portal')

@section('title', 'Request history')
@section('heading', 'Request history')
@section('subtitle', 'Search closed and historical requests or export filtered records.')

@section('content')
    <section class="stats-grid compact-stats"><article class="stat-card"><span class="stat-label">All requests</span><strong class="stat-value">{{ $counts['total'] }}</strong></article><article class="stat-card"><span class="stat-label">Completed</span><strong class="stat-value">{{ $counts['completed'] }}</strong></article><article class="stat-card"><span class="stat-label">Rejected</span><strong class="stat-value">{{ $counts['rejected'] }}</strong></article><article class="stat-card"><span class="stat-label">Filtered</span><strong class="stat-value">{{ $records->total() }}</strong></article></section>
    <section class="panel module-panel">
        <div class="panel-heading"><div><p class="eyebrow">AUDIT & REPORTS</p><h2>Document request records</h2></div><a class="secondary-button" href="{{ route('request-records.export', request()->query()) }}">Export CSV</a></div>
        <form method="GET" class="filter-bar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search request or student">
            <select name="document_type_id"><option value="">All documents</option>@foreach ($documentTypes as $type)<option value="{{ $type->id }}" @selected(request('document_type_id') == $type->id)>{{ $type->name }}</option>@endforeach</select>
            <select name="status"><option value="">All statuses</option>@foreach (\App\Enums\RequestStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->value }}</option>@endforeach</select>
            <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="From date"><input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="To date">
            <button class="secondary-button" type="submit">Filter</button><a class="text-action" href="{{ route('request-records.index') }}">Clear</a>
        </form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Reference</th><th>Student</th><th>Document</th><th>Requested</th><th>Status</th><th>Closed</th><th>Action</th></tr></thead><tbody>
            @forelse ($records as $item)
                <tr class="hover-row" tabindex="0"><td><strong>{{ $item->reference }}</strong><span class="hover-card">{{ implode("\n", array_filter([
                    'Purpose: '.($item->purpose ?: 'Not provided'),
                    'Stage: '.$item->current_stage->value,
                    'Student: '.$item->student->full_name.' ('.$item->student->school_id.')',
                    'Email: '.$item->student->email,
                    'CYS: '.($item->student->courseRecord?->name ?? $item->student->course ?? '—').' · Year '.($item->student->yearRecord?->year_level ?? $item->student->year ?? '—').' · Section '.($item->student->sectionRecord?->name ?? $item->student->section ?? '—'),
                    'Department: '.($item->student->department?->name ?? '—'),
                    'Office: '.($item->student->office?->name ?? '—'),
                    'Position: '.($item->student->positionRecord?->name ?? $item->student->position ?? '—'),
                    $item->requester_remarks ? 'Student remarks: '.$item->requester_remarks : null,
                    $item->staff_remarks ? 'Staff remarks: '.$item->staff_remarks : null,
                    $item->rejection_reason ? 'Legacy note: '.$item->rejection_reason : null,
                    'Collection: Pick-up',
                ])) }}</span></td><td>{{ $item->student->full_name }}<small class="cell-subtitle">{{ $item->student->school_id }}</small></td><td>{{ $item->documentType->name }}</td><td>{{ $item->created_at->format('M j, Y') }}</td><td><span class="status-badge {{ 'status-'.\Illuminate\Support\Str::slug($item->status->value) }}">{{ $item->status->value }}</span></td><td>{{ ($item->completed_at ?? $item->rejected_at)?->format('M j, Y') ?? '—' }}</td><td><a class="text-action" href="{{ route('request-management.show', $item) }}">View history</a></td></tr>
            @empty
                <tr><td colspan="7"><div class="empty-state compact"><strong>No request records match these filters</strong></div></td></tr>
            @endforelse
        </tbody></table></div>
        <div class="pagination-wrap">{{ $records->links() }}</div>
    </section>
@endsection
