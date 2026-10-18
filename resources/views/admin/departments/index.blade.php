@extends('layouts.app')

@section('title', 'Departments')

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">ADMINISTRATION</p>
            <h1>Department Management</h1>
            <p class="page-subtitle">Departments are assigned to users and cannot be deleted while in use.</p>
        </div>
        <div class="header-date">{{ $departments->count() }} {{ \Illuminate\Support\Str::plural('department', $departments->count()) }}</div>
    </header>

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">{{ $editing ? 'EDIT' : 'NEW' }}</p>
                <h2>{{ $editing ? 'Update department' : 'Add department' }}</h2>
            </div>
            @if ($editing)
                <a class="button button-ghost button-sm" href="{{ route('admin.departments.index') }}">Cancel</a>
            @endif
        </div>

        <form class="form-body" method="POST" autocomplete="off"
              action="{{ $editing ? route('admin.departments.update', $editing) : route('admin.departments.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="adm-form-row">
                <div class="field">
                    <label for="code">Department code</label>
                    <input id="code" name="code" type="text" maxlength="20" required value="{{ old('code', $editing?->code) }}">
                    @error('code') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="name">Department name</label>
                    <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $editing?->name) }}">
                    @error('name') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $editing?->status ?? 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="adm-form-submit">
                    <button class="button" type="submit">{{ $editing ? 'Update Department' : '+ Add Department' }}</button>
                </div>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="table-wrap">
            <table class="data-table adm-table">
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Users</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td>{{ $department->code }}</td>
                            <td>{{ $department->name }}</td>
                            <td>{{ $department->users_count }}</td>
                            <td><span class="status-badge status-{{ $department->status }}">{{ ucfirst($department->status) }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a class="button button-ghost button-sm" href="{{ route('admin.departments.index', ['edit' => $department->id]) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" onsubmit="return confirm('Delete this department?')">
                                        @csrf @method('DELETE')
                                        <button class="button button-danger button-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state compact"><strong>No departments yet</strong><p>Add the first department above.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
