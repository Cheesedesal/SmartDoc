@extends('layouts.portal')

@section('title', 'Request management')
@section('heading', 'Request management')
@section('subtitle', 'Review uploaded requirements and process student document requests.')

@section('content')
    <section class="panel module-panel">
        <form method="GET" class="filter-bar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search reference, student, or ID">
            <select name="status">
                <option value="">All statuses</option>
                @foreach (\App\Enums\RequestStatus::cases() as $status)
                    @if ($status !== \App\Enums\RequestStatus::Rejected)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->value }}</option>
                    @endif
                @endforeach
            </select>
            <button class="secondary-button" type="submit">Filter</button>
            <a class="text-action" href="{{ route('request-management.index') }}">Clear</a>
        </form>
        @if ($requests->isEmpty())
            <div class="empty-state compact"><strong>No requests found</strong><p>New student submissions will appear here.</p></div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Reference</th><th>Student</th><th>Document</th><th>Submitted</th><th>Status</th><th>Stage</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach ($requests as $item)
                            <tr class="hover-row" tabindex="0">
                                <td><strong>{{ $item->reference }}</strong><span class="hover-card">{{ implode("\n", array_filter([
                                    'Purpose: '.($item->purpose ?: 'Not provided'),
                                    'Student remarks: '.($item->requester_remarks ?: 'None'),
                                    'Document: '.$item->documentType->name,
                                    'Fee at pickup: ₱'.number_format((float) $item->documentType->fee, 2),
                                    'Processing time: '.$item->documentType->processing_days.' business days',
                                    'Student: '.$item->student->full_name.' ('.$item->student->school_id.')',
                                    'Email: '.$item->student->email,
                                    'CYS: '.($item->student->courseRecord?->name ?? $item->student->course ?? '—').' · Year '.($item->student->yearRecord?->year_level ?? $item->student->year ?? '—').' · Section '.($item->student->sectionRecord?->name ?? $item->student->section ?? '—'),
                                    'Department: '.($item->student->department?->name ?? '—'),
                                    'Office: '.($item->student->office?->name ?? '—'),
                                    'Position: '.($item->student->positionRecord?->name ?? $item->student->position ?? '—'),
                                    'Collection: Pick-up',
                                ])) }}</span></td>
                                <td>{{ $item->student->full_name }}<small class="cell-subtitle">{{ $item->student->school_id }}</small></td>
                                <td>{{ $item->documentType->name }}<small class="cell-subtitle">{{ \Illuminate\Support\Str::limit($item->documentType->description, 70) }}</small></td>
                                <td>{{ $item->created_at->format('M j, Y') }}</td>
                                <td><span class="status-badge {{ 'status-'.\Illuminate\Support\Str::slug($item->status->value) }}">{{ $item->status->value }}</span></td>
                                <td>{{ $item->current_stage->value }}</td>
                                <td><a class="text-action" href="{{ route('request-management.show', $item) }}">Review</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $requests->links() }}</div>
        @endif
    </section>
@endsection
