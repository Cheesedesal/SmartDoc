@extends('layouts.portal')

@section('title', 'Document types')
@section('heading', 'Document types & requirements')
@section('subtitle', 'Configure requestable documents, processing-time estimates, pickup fees, and required uploads.')

@section('content')
    <section class="panel module-panel">
        <form method="GET" class="filter-bar"><input type="search" name="search" value="{{ request('search') }}" placeholder="Search document type"><select name="active"><option value="">All document types</option><option value="1" @selected(request()->query('active') === '1')>Active</option><option value="0" @selected(request()->query('active') === '0')>Inactive</option></select><select name="archived"><option value="0" @selected(! request()->boolean('archived'))>Current document types</option><option value="1" @selected(request()->boolean('archived'))>Archived document types</option></select><select name="archived_requirements"><option value="0" @selected(! request()->boolean('archived_requirements'))>Current requirements</option><option value="1" @selected(request()->boolean('archived_requirements'))>Archived requirements</option></select><button class="secondary-button" type="submit">Filter</button><a class="text-action" href="{{ route('admin.document-types.index') }}">Clear</a>@unless(request()->boolean('archived') || request()->boolean('archived_requirements'))<a class="secondary-button" href="{{ route('admin.imports.show', 'document-types') }}">Import document types</a><a class="secondary-button" href="{{ route('admin.imports.show', 'requirements') }}">Import requirements</a>@endunless</form>
        <form id="document-types-bulk-form" method="POST" action="{{ route(request()->boolean('archived') ? 'admin.document-types.bulk-restore' : 'admin.document-types.bulk-archive') }}">@csrf
            <div class="bulk-actions"><strong>{{ request()->boolean('archived') ? 'Restore selected document types' : 'Archive selected document types' }}</strong><button class="secondary-button" type="submit" data-confirm="Apply this action to all selected document types?">{{ request()->boolean('archived') ? 'Restore selected document types' : 'Archive selected document types' }}</button></div>
        </form>
        <form id="requirements-bulk-form" method="POST" action="{{ route(request()->boolean('archived_requirements') ? 'admin.document-types.requirements.bulk-restore' : 'admin.document-types.requirements.bulk-archive') }}">@csrf
            <div class="bulk-actions"><strong>{{ request()->boolean('archived_requirements') ? 'Restore selected requirements' : 'Archive selected requirements' }}</strong><button class="secondary-button" type="submit" data-confirm="Apply this action to all selected requirements?">{{ request()->boolean('archived_requirements') ? 'Restore selected requirements' : 'Archive selected requirements' }}</button></div>
        </form>
        @unless(request()->boolean('archived'))
            <div class="table-toolbar"><button class="primary-button" type="button" data-modal-open="document-modal" data-action="{{ route('admin.document-types.store') }}" data-values='{"is_active":true}'>+ Add document type</button></div>
        @endunless
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th><input type="checkbox" data-select-all="document-types-bulk-form" aria-label="Select all document types on this page"></th><th>Document type</th><th>Processing</th><th>Fee at pickup</th><th>Requirements</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($documentTypes as $documentType)
                        <tr class="hover-row" tabindex="0">
                            <td>@if(($documentType->archived_at !== null) === request()->boolean('archived'))<input type="checkbox" name="ids[]" value="{{ $documentType->id }}" form="document-types-bulk-form" aria-label="Select {{ $documentType->name }}">@endif</td>
                            <td><strong>{{ $documentType->name }}</strong><span class="hover-card">{{ $documentType->description }}</span></td>
                            <td>{{ $documentType->processing_days }} business days
                                @if ($processingEstimates[$documentType->id]['days'])
                                    <small class="cell-subtitle">Historical median: {{ $processingEstimates[$documentType->id]['days'] }} days from {{ $processingEstimates[$documentType->id]['sample_size'] }} completed requests</small>
                                @else
                                    <small class="cell-subtitle">Need 3 completed requests for a history estimate</small>
                                @endif
                            </td><td>₱{{ number_format((float) $documentType->fee, 2) }}<small class="cell-subtitle">Pay at pickup; no system payment</small></td><td>{{ $documentType->requirements_count }}</td>
                            <td><span class="status-badge {{ $documentType->is_active ? 'status-completed' : 'status-rejected' }}">{{ $documentType->archived_at ? 'Archived' : ($documentType->is_active ? 'Active' : 'Inactive') }}</span></td>
                            <td class="action-cell">@if($documentType->archived_at)
                                <form method="POST" action="{{ route('admin.document-types.restore', $documentType) }}">@csrf<button class="text-action" type="submit">Restore</button></form>
                            @else
                                <button class="text-action" type="button" data-modal-open="document-modal" data-action="{{ route('admin.document-types.update', $documentType) }}" data-method="PUT" data-estimate="{{ $processingEstimates[$documentType->id]['days'] ?? '' }}" data-estimate-count="{{ $processingEstimates[$documentType->id]['sample_size'] }}" data-values="{{ json_encode($documentType->only(['name','description','processing_days','fee','is_active'])) }}">Edit</button>
                                <form method="POST" action="{{ route('admin.document-types.archive', $documentType) }}" data-confirm="Archive this document type and make it unavailable to students? Existing request history will remain.">@csrf<button class="danger-text" type="submit">Archive</button></form>
                            @endif</td>
                        </tr>
                        <tr class="requirements-row"><td colspan="7">
                            <div class="requirements-table-heading"><strong>Requirements for {{ $documentType->name }}</strong>@unless($documentType->archived_at || request()->boolean('archived_requirements'))<button class="small-button" type="button" data-modal-open="requirement-modal" data-action="{{ route('admin.document-types.requirements.store', $documentType) }}" data-values='{"is_required":true}'>+ Add requirement</button>@endunless</div>
                            @if ($documentType->requirements->isEmpty())
                                <p class="empty-inline">No requirements configured.</p>
                            @else
                                <table class="nested-table"><thead><tr><th><input type="checkbox" data-select-all="requirements-bulk-form" aria-label="Select all displayed requirements"></th><th>Requirement</th><th>Description</th><th>Required?</th><th>Action</th></tr></thead><tbody>
                                    @foreach ($documentType->requirements as $requirement)
                                        <tr class="hover-row" tabindex="0"><td>@if(($requirement->archived_at !== null) === request()->boolean('archived_requirements'))<input type="checkbox" name="ids[]" value="{{ $requirement->id }}" form="requirements-bulk-form" aria-label="Select {{ $requirement->name }}">@endif</td><td><strong>{{ $requirement->name }}</strong>@if($requirement->archived_at)<small class="cell-subtitle">Archived</small>@endif<span class="hover-card">{{ $requirement->description."\n".($requirement->is_required ? 'Must be submitted with every request.' : 'Optional supporting file.') }}</span></td><td>{{ $requirement->description }}</td><td>{{ $requirement->is_required ? 'Required' : 'Optional' }}</td><td class="action-cell">@if($requirement->archived_at)<form method="POST" action="{{ route('admin.document-types.requirements.restore', [$documentType, $requirement]) }}">@csrf<button class="text-action" type="submit">Restore</button></form>@else<button class="text-action" type="button" data-modal-open="requirement-modal" data-action="{{ route('admin.document-types.requirements.update', [$documentType, $requirement]) }}" data-method="PUT" data-values="{{ json_encode($requirement->only(['name','description','is_required'])) }}">Edit</button><form method="POST" action="{{ route('admin.document-types.requirements.archive', [$documentType, $requirement]) }}" data-confirm="Archive this requirement? Existing uploads and request history will be preserved.">@csrf<button class="danger-text" type="submit">Archive</button></form>@endif</td></tr>
                                    @endforeach
                                </tbody></table>
                            @endif
                        </td></tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state compact"><strong>No document types found</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $documentTypes->links() }}</div>
    </section>
    <dialog class="form-modal wide-modal" id="document-modal">
        <form method="POST" action="{{ route('admin.document-types.store') }}" class="modal-form" data-modal-form>
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_form_action" value="{{ old('_form_action') }}"><input type="hidden" name="_form_method" value="{{ old('_form_method') }}"><input type="hidden" name="_form_target" value="{{ old('_form_target') }}">
            <div class="modal-heading"><div><p class="eyebrow">DOCUMENT TYPE</p><h2>Add or edit document type</h2></div><button class="icon-button" type="button" data-modal-close aria-label="Close">×</button></div>
            <label>Name<input name="name" value="{{ old('name') }}" maxlength="100" required></label><label>Description<textarea name="description" rows="3" required>{{ old('description') }}</textarea></label>
            <div class="form-grid"><label>Processing days (admin-confirmed)<input type="number" name="processing_days" min="1" max="365" value="{{ old('processing_days', 5) }}" required><small data-estimate-hint>Historical estimate will appear here after at least 3 completed requests. This is a transparent median estimate, not an AI decision.</small><button class="text-action" type="button" data-use-estimate hidden>Use historical estimate</button></label><label>Fee (₱), paid at pickup<input type="number" name="fee" min="0" step="0.01" value="{{ old('fee', '0.00') }}" required><small>Payment is handled at the Registrar office during pickup; SmartDoc does not process payments.</small></label></div>
            <label class="checkbox-line"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Available for student requests</label>
            <div class="modal-actions"><button class="secondary-button" type="button" data-modal-close>Cancel</button><button class="primary-button" type="submit">Save document type</button></div>
        </form>
    </dialog>
    <dialog class="form-modal" id="requirement-modal">
        <form method="POST" action="#" class="modal-form" data-modal-form>
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_form_action" value="{{ old('_form_action') }}"><input type="hidden" name="_form_method" value="{{ old('_form_method') }}"><input type="hidden" name="_form_target" value="{{ old('_form_target') }}">
            <div class="modal-heading"><div><p class="eyebrow">REQUIREMENT</p><h2>Add or edit requirement</h2></div><button class="icon-button" type="button" data-modal-close aria-label="Close">×</button></div>
            <label>Name<input name="name" value="{{ old('name') }}" maxlength="150" required></label><label>Description<input name="description" value="{{ old('description') }}" maxlength="150" required></label>
            <label class="checkbox-line"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', true))> Required for the request</label>
            <div class="modal-actions"><button class="secondary-button" type="button" data-modal-close>Cancel</button><button class="primary-button" type="submit">Save requirement</button></div>
        </form>
    </dialog>
@endsection
