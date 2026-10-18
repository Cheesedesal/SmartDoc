@extends('layouts.app')

@section('title', 'Document types')

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">ADMINISTRATION</p>
            <h1>Document Management</h1>
            <p class="page-subtitle">Document types students can request, with their fee, processing time and requirements.</p>
        </div>
    </header>

    <div class="stats-grid">
        <div class="stat-card"><span class="stat-label">Document types</span><span class="stat-value">{{ $counts['types'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Requirements</span><span class="stat-value">{{ $counts['requirements'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Active types</span><span class="stat-value">{{ $counts['active'] }}</span></div>
    </div>

    <section class="panel form-panel">
        <div class="panel-heading"><div><p class="eyebrow">SEARCH</p><h2>Search documents</h2></div></div>
        <form class="form-body" method="GET" action="{{ route('admin.document-types.index') }}">
            <div class="adm-form-row">
                <div class="field">
                    <label for="q">Document name</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" autocomplete="off">
                </div>
                <div class="field">
                    <label for="active">Status</label>
                    <select id="active" name="active">
                        <option value="">All</option>
                        <option value="1" @selected($filters['active'] === '1')>Active</option>
                        <option value="0" @selected($filters['active'] === '0')>Inactive</option>
                    </select>
                </div>
                <div class="adm-form-submit">
                    <button class="button" type="submit">Search</button>
                    <a class="button button-ghost" href="{{ route('admin.document-types.index') }}">Clear</a>
                </div>
            </div>
        </form>
    </section>

    <section class="panel form-panel" id="type-form">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">{{ $editing ? 'EDIT' : 'NEW' }}</p>
                <h2>{{ $editing ? 'Update document type' : 'Add document type' }}</h2>
            </div>
            @if ($editing)
                <a class="button button-ghost button-sm" href="{{ route('admin.document-types.index') }}">Cancel</a>
            @endif
        </div>
        <form class="form-body" method="POST" autocomplete="off"
              action="{{ $editing ? route('admin.document-types.update', $editing) : route('admin.document-types.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="adm-form-row">
                <div class="field">
                    <label for="name">Document name</label>
                    <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $editing?->name) }}">
                    @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="processing_days">Processing days</label>
                    <input id="processing_days" name="processing_days" type="number" min="1" max="365" required value="{{ old('processing_days', $editing?->processing_days) }}">
                    @error('processing_days') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="fee">Fee</label>
                    <input id="fee" name="fee" type="number" min="0" step="0.01" value="{{ old('fee', $editing?->fee ?? 0) }}">
                    @error('fee') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="is_active">Status</label>
                    <select id="is_active" name="is_active" required>
                        <option value="1" @selected((string) old('is_active', $editing ? (int) $editing->is_active : 1) === '1')>Active</option>
                        <option value="0" @selected((string) old('is_active', $editing ? (int) $editing->is_active : 1) === '0')>Inactive</option>
                    </select>
                    @error('is_active') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="2" maxlength="1000" required>{{ old('description', $editing?->description) }}</textarea>
                @error('description') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div><button class="button" type="submit">{{ $editing ? 'Update Document' : '+ Add Document' }}</button></div>
        </form>
    </section>

    <section class="panel">
        <div class="table-wrap">
            <table class="data-table adm-table">
                <thead>
                    <tr><th>Document</th><th>Description</th><th>Processing days</th><th>Fee</th><th>Requirements</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td><strong>{{ $type->name }}</strong></td>
                            <td>{{ \Illuminate\Support\Str::limit($type->description, 80) }}</td>
                            <td>{{ $type->processing_days }}</td>
                            <td>{{ (float) $type->fee > 0 ? number_format((float) $type->fee, 2) : 'Free' }}</td>
                            <td>{{ $type->requirements_count }}</td>
                            <td><span class="status-badge {{ $type->is_active ? 'status-active' : 'status-inactive' }}">{{ $type->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a class="button button-ghost button-sm" href="{{ route('admin.document-types.configure', $type) }}">Configure</a>
                                    <a class="button button-ghost button-sm" href="{{ route('admin.document-types.index', array_merge(request()->query(), ['edit' => $type->id])) }}#type-form">Edit</a>
                                    <form method="POST" action="{{ route('admin.document-types.destroy', $type) }}" onsubmit="return confirm('Delete this document type?')">
                                        @csrf @method('DELETE')
                                        <button class="button button-danger button-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state compact"><strong>No document types found</strong><p>Add a document type above.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
