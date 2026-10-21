@extends('layouts.portal')

@section('title', 'Department management')
@section('heading', 'Department management')
@section('subtitle', 'Maintain departments used by staff accounts and school workflows.')

@section('content')
    <section class="panel module-panel">
        <form method="GET" class="filter-bar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search code or department">
            <select name="status"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select>
            <select name="archived"><option value="0" @selected(! request()->boolean('archived'))>Current departments</option><option value="1" @selected(request()->boolean('archived'))>Archived departments</option></select>
            <button class="secondary-button" type="submit">Filter</button><a class="text-action" href="{{ route('admin.departments.index') }}">Clear</a>
            @unless(request()->boolean('archived'))<a class="secondary-button" href="{{ route('admin.imports.show', 'departments') }}">Bulk import</a>@endunless
        </form>
        <form id="departments-bulk-form" method="POST" action="{{ route(request()->boolean('archived') ? 'admin.departments.bulk-restore' : 'admin.departments.bulk-archive') }}">@csrf
            <div class="bulk-actions"><strong>{{ request()->boolean('archived') ? 'Restore selected departments' : 'Archive selected departments' }}</strong><button class="secondary-button" type="submit" data-confirm="Apply this action to all selected departments?">{{ request()->boolean('archived') ? 'Restore selected' : 'Archive selected' }}</button></div>
        </form>
        @unless(request()->boolean('archived'))
            <div class="table-toolbar"><button class="primary-button" type="button" data-modal-open="department-modal" data-action="{{ route('admin.departments.store') }}" data-values="{}">+ Add department</button></div>
        @endunless
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th><input type="checkbox" data-select-all="departments-bulk-form" aria-label="Select all departments on this page"></th><th>Code</th><th>Department</th><th>Users</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr class="hover-row" tabindex="0">
                            <td><input type="checkbox" name="ids[]" value="{{ $department->id }}" form="departments-bulk-form" aria-label="Select {{ $department->name }}"></td>
                            <td><strong>{{ $department->code }}</strong><span class="hover-card">{{ $department->name."\n".$department->users_count." linked account(s)" }}</span></td>
                            <td>{{ $department->name }}</td><td>{{ $department->users_count }}</td><td><span class="status-badge">{{ $department->archived_at ? 'Archived' : ucfirst($department->status) }}</span></td>
                            <td class="action-cell">@if(request()->boolean('archived'))
                                <form method="POST" action="{{ route('admin.departments.restore', $department) }}">@csrf<button class="text-action" type="submit">Restore</button></form>
                            @else
                                <button class="text-action" type="button" data-modal-open="department-modal" data-action="{{ route('admin.departments.update', $department) }}" data-method="PUT" data-values="{{ json_encode($department->only(['code', 'name', 'status'])) }}">Edit</button>
                                <form method="POST" action="{{ route('admin.departments.archive', $department) }}" data-confirm="Archive this department? Existing user records will remain linked.">@csrf<button class="danger-text" type="submit">Archive</button></form>
                            @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state compact"><strong>No departments found</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $departments->links() }}</div>
    </section>

    <dialog class="form-modal" id="department-modal">
        <form method="POST" action="{{ route('admin.departments.store') }}" class="modal-form" data-modal-form>
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_form_action" value="{{ old('_form_action') }}"><input type="hidden" name="_form_method" value="{{ old('_form_method') }}"><input type="hidden" name="_form_target" value="{{ old('_form_target') }}">
            <div class="modal-heading"><div><p class="eyebrow">DEPARTMENT</p><h2>Add or edit department</h2></div><button class="icon-button" type="button" data-modal-close aria-label="Close">×</button></div>
            <label>Department code<input name="code" value="{{ old('code') }}" maxlength="20" required></label>
            <label>Department name<input name="name" value="{{ old('name') }}" maxlength="100" required></label>
            <label>Status<select name="status" required><option value="active" @selected(old('status', 'active') === 'active')>Active</option><option value="inactive" @selected(old('status') === 'inactive')>Inactive</option></select></label>
            <div class="modal-actions"><button class="secondary-button" type="button" data-modal-close>Cancel</button><button class="primary-button" type="submit">Save department</button></div>
        </form>
    </dialog>
@endsection
