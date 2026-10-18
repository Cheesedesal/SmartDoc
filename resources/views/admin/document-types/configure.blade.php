@extends('layouts.app')

@section('title', 'Configure document')

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">DOCUMENT CONFIGURATION</p>
            <h1>{{ $documentType->name }}</h1>
            <p class="page-subtitle">
                {{ $documentType->processing_days }} day(s) processing ·
                {{ (float) $documentType->fee > 0 ? number_format((float) $documentType->fee, 2) : 'Free' }} ·
                {{ $documentType->is_active ? 'Active' : 'Inactive' }}
            </p>
        </div>
        <a class="button button-ghost" href="{{ route('admin.document-types.index') }}">Back to documents</a>
    </header>

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">{{ $editing ? 'EDIT' : 'NEW' }}</p>
                <h2>{{ $editing ? 'Update requirement' : 'Add requirement' }}</h2>
            </div>
            @if ($editing)
                <a class="button button-ghost button-sm" href="{{ route('admin.document-types.configure', $documentType) }}">Cancel</a>
            @endif
        </div>
        <form class="form-body" method="POST" autocomplete="off"
              action="{{ $editing ? route('admin.requirements.update', $editing) : route('admin.document-types.requirements.store', $documentType) }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="adm-form-row">
                <div class="field">
                    <label for="name">Requirement name</label>
                    <input id="name" name="name" type="text" maxlength="150" required value="{{ old('name', $editing?->name) }}">
                    @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="description">Description</label>
                    <input id="description" name="description" type="text" maxlength="150" required value="{{ old('description', $editing?->description) }}">
                    @error('description') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="is_required">Required?</label>
                    <select id="is_required" name="is_required" required>
                        <option value="1" @selected((string) old('is_required', $editing ? (int) $editing->is_required : 1) === '1')>Required</option>
                        <option value="0" @selected((string) old('is_required', $editing ? (int) $editing->is_required : 1) === '0')>Optional</option>
                    </select>
                    @error('is_required') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="adm-form-submit">
                    <button class="button" type="submit">{{ $editing ? 'Update Requirement' : '+ Add Requirement' }}</button>
                </div>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="table-wrap">
            <table class="data-table adm-table">
                <thead><tr><th>Requirement</th><th>Description</th><th>Required</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($requirements as $requirement)
                        <tr>
                            <td><strong>{{ $requirement->name }}</strong></td>
                            <td>{{ $requirement->description }}</td>
                            <td><span class="status-badge {{ $requirement->is_required ? 'status-active' : 'status-draft' }}">{{ $requirement->is_required ? 'Required' : 'Optional' }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a class="button button-ghost button-sm" href="{{ route('admin.document-types.configure', ['documentType' => $documentType, 'edit' => $requirement->id]) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.requirements.destroy', $requirement) }}" onsubmit="return confirm('Delete this requirement?')">
                                        @csrf @method('DELETE')
                                        <button class="button button-danger button-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state compact"><strong>No requirements yet</strong><p>Students will not be asked to upload anything for this document.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
