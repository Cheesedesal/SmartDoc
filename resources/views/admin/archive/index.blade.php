@extends('layouts.portal')

@section('title', 'Archive')
@section('heading', 'Archive')
@section('subtitle', 'Browse archived records by category and restore records when they are needed again.')

@section('content')
    <section class="panel module-panel">
        <nav class="segmented-tabs archive-tabs" aria-label="Archived record category">
            @foreach ($types as $key => [$model, $name])
                <a class="{{ $type === $key ? 'active' : '' }}" href="{{ route('admin.archive.index', ['type' => $key]) }}" @if($type === $key) aria-current="page" @endif>{{ $name }}</a>
            @endforeach
        </nav>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Record</th><th>Details</th><th>Archived</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        @php($hoverDetails = implode("\n", array_filter([
                            $record->email,
                            $record->school_id,
                            $record->department?->name,
                            $record->position,
                            $record->course ? $record->course.' · Year '.$record->year.' '.$record->section : null,
                            $record->code,
                            $record->description,
                            $record->documentType?->name,
                            $record->fee !== null ? 'Fee at pickup: ₱'.number_format((float) $record->fee, 2) : null,
                            $record->message,
                            $record->answer,
                            $record->canonical_question,
                            $record->intent,
                            $record->audience?->value,
                            $record->status instanceof \BackedEnum ? $record->status->value : $record->status,
                        ], fn ($value) => filled($value))))
                        <tr class="hover-row" tabindex="0">
                            <td><strong>{{ $record->full_name ?? $record->name ?? $record->title ?? $record->canonical_question ?? 'Record #'.$record->id }}</strong><span class="hover-card">{{ $hoverDetails ?: 'No additional details available.' }}</span></td>
                            <td>{{ $record->email ?? $record->code ?? $record->documentType?->name ?? $record->role?->label() ?? $record->description ?? $record->intent ?? '—' }}</td>
                            <td>{{ $record->archived_at?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="action-cell">
                                <form method="POST" action="{{ route('admin.archive.restore', [$type, $record->id]) }}" data-confirm="Restore this archived record?">@csrf<button class="text-action" type="submit">Restore</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state compact"><strong>No archived {{ strtolower($label) }} found.</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $records->links() }}</div>
    </section>
@endsection
