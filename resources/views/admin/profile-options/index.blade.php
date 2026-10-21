@extends('layouts.portal')

@section('title', $definition['title'])
@section('heading', $definition['title'])
@section('subtitle', $definition['subtitle'])

@section('content')
    <section class="panel module-panel">
        <form method="GET" class="filter-bar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search {{ strtolower($definition['title']) }}">
            <select name="archived"><option value="0" @selected(!request()->boolean('archived'))>Current options</option><option value="1" @selected(request()->boolean('archived'))>Archived options</option></select>
            <button class="secondary-button" type="submit">Filter</button>
            <a class="text-action" href="{{ route('admin.profile-options.index', $type) }}">Clear</a>
            @unless(request()->boolean('archived'))<a class="secondary-button" href="{{ route('admin.imports.show', $type) }}">Bulk import</a>@endunless
        </form>
        <div class="table-toolbar">
            @unless(request()->boolean('archived'))
                <button class="primary-button" type="button" data-modal-open="profile-option-modal" data-action="{{ route('admin.profile-options.store', $type) }}" data-values='{"status":"active","role":"both"}'>+ Add {{ str_replace(' management', '', strtolower($definition['title'])) }}</button>
            @endunless
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>@foreach($definition['columns'] as $column => $label)<th>{{ $label }}</th>@endforeach<th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        @php
                            $details = collect($definition['columns'])->keys()->reject(fn ($key) => $key === 'users_count')
                                ->map(fn ($key) => data_get($record, $key))
                                ->filter()
                                ->map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value)
                                ->implode("\n");
                        @endphp
                        <tr class="hover-row" tabindex="0">
                            @foreach($definition['columns'] as $column => $label)
                                <td>
                                    @php($value = data_get($record, $column))
                                    @if($column === 'users_count'){{ $record->users_count }}
                                    @elseif($value instanceof \BackedEnum){{ ucfirst($value->value) }}
                                    @else{{ $value ?? '—' }}
                                    @endif
                                    @if($loop->first)<span class="hover-card">{{ $details }}{{ $record->archived_at ? "\nArchived on ".$record->archived_at->format('M j, Y') : '' }}{{ $record->users_count ? "\nUsed by ".$record->users_count." account(s)" : '' }}</span>@endif
                                </td>
                            @endforeach
                            <td><span class="status-badge">{{ $record->archived_at ? 'Archived' : ucfirst($record->status) }}</span></td>
                            <td class="action-cell">
                                @if($record->archived_at)
                                    <form method="POST" action="{{ route('admin.profile-options.restore', [$type, $record->id]) }}">@csrf<button class="text-action" type="submit">Restore</button></form>
                                @else
                                    <button class="text-action" type="button" data-modal-open="profile-option-modal" data-action="{{ route('admin.profile-options.update', [$type, $record->id]) }}" data-method="PUT" data-values="{{ json_encode($record->only(array_keys($definition['fields']))) }}">Edit</button>
                                    <form method="POST" action="{{ route('admin.profile-options.archive', [$type, $record->id]) }}" data-confirm="Archive this option? Existing account records remain linked.">@csrf<button class="danger-text" type="submit">Archive</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($definition['columns']) + 2 }}"><div class="empty-state compact"><strong>No options found.</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $records->links() }}</div>
    </section>

    <dialog class="form-modal" id="profile-option-modal">
        <form method="POST" action="{{ route('admin.profile-options.store', $type) }}" class="modal-form" data-modal-form>
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_form_action" value="{{ old('_form_action') }}"><input type="hidden" name="_form_method" value="{{ old('_form_method') }}"><input type="hidden" name="_form_target" value="{{ old('_form_target') }}">
            <div class="modal-heading"><div><p class="eyebrow">USER PROFILE OPTION</p><h2>Add or edit</h2></div><button class="icon-button" type="button" data-modal-close aria-label="Close">×</button></div>
            @foreach($definition['fields'] as $field => $config)
                <label>{{ $config['label'] }}
                    @if($config['type'] === 'select')
                        <select name="{{ $field }}" required>@foreach($config['options'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    @elseif($config['type'] === 'relation')
                        <select name="{{ $field }}" required>
                            <option value="">Select {{ strtolower($config['label']) }}</option>
                            @foreach($relations[$field] as $relation)
                                @php($label = $field === 'student_year_id' ? $relation->course->name.' · Year '.$relation->year_level : $relation->name)
                                <option value="{{ $relation->id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="{{ $config['type'] }}" name="{{ $field }}" @isset($config['max']) maxlength="{{ $config['max'] }}" @endisset @isset($config['min']) min="{{ $config['min'] }}" @endisset @isset($config['max']) @if($config['type'] === 'number') max="{{ $config['max'] }}" @endif @endisset required>
                    @endif
                </label>
            @endforeach
            <div class="modal-actions"><button class="secondary-button" type="button" data-modal-close>Cancel</button><button class="primary-button" type="submit">Save option</button></div>
        </form>
    </dialog>
@endsection
