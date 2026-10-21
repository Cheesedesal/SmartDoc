@extends('layouts.portal')

@section('title', 'User management')
@section('heading', 'User management')
@section('subtitle', 'Create and maintain student, staff, and administrator accounts.')

@section('content')
    <section class="stats-grid">
        @foreach (['total' => 'Total users', 'students' => 'Students', 'staff' => 'Staff', 'admins' => 'Administrators'] as $key => $label)
            <article class="stat-card"><span class="stat-label">{{ $label }}</span><strong class="stat-value">{{ number_format($counts[$key]) }}</strong></article>
        @endforeach
    </section>
    <section class="panel module-panel">
            <nav class="segmented-tabs user-role-tabs" aria-label="User role management" style="--tab-count: 4; --active-index: {{ $roleTabIndex }}; --mobile-active-index: {{ $roleTabMobileIndex }}">
                @foreach (['all' => 'All users', 'admin' => 'Admin', 'staff' => 'Staff', 'student' => 'Student'] as $tabRole => $label)
                    <a class="{{ $role === $tabRole ? 'active' : '' }}" href="{{ route('admin.users.index', ['role' => $tabRole, 'archived' => request()->boolean('archived') ? 1 : 0]) }}" @if($role === $tabRole) aria-current="page" @endif>{{ $tabRole === 'all' ? 'All users' : 'Manage '.$label }}</a>
                @endforeach
            </nav>
            <form method="GET" class="filter-bar">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, school ID, or email">
                <input type="hidden" name="role" value="{{ $role }}">
                <select name="status"><option value="">All statuses</option>@foreach (\App\Enums\UserStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select>
                <select name="archived"><option value="0" @selected(! request()->boolean('archived'))>Current accounts</option><option value="1" @selected(request()->boolean('archived'))>Archived accounts</option></select>
                <button class="secondary-button" type="submit">Filter</button><a class="text-action" href="{{ route('admin.users.index', ['role' => $role]) }}">Clear</a>
                @unless(request()->boolean('archived'))<a class="secondary-button" href="{{ route('admin.imports.show', 'users') }}">Bulk import</a>@endunless
        </form>
        <form id="users-bulk-form" method="POST" action="{{ route(request()->boolean('archived') ? 'admin.users.bulk-restore' : 'admin.users.bulk-archive') }}">
            @csrf
            <div class="bulk-actions"><strong>{{ request()->boolean('archived') ? 'Restore selected accounts' : 'Archive selected accounts' }}</strong><button class="secondary-button" type="submit" data-confirm="{{ request()->boolean('archived') ? 'Restore all selected accounts?' : 'Archive all selected accounts? Their history will be preserved.' }}">{{ request()->boolean('archived') ? 'Restore selected' : 'Archive selected' }}</button></div>
        </form>
        @unless(request()->boolean('archived'))
            <div class="table-toolbar">
                <button class="primary-button" type="button" data-modal-open="user-modal" data-action="{{ route('admin.users.store') }}" data-values='{"role":"student","status":"activated"}'>+ Add student</button>
                <button class="secondary-button" type="button" data-modal-open="user-modal" data-action="{{ route('admin.users.store') }}" data-values='{"role":"staff","status":"activated"}'>+ Add staff/admin</button>
            </div>
        @endunless
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th><input type="checkbox" data-select-all="users-bulk-form" aria-label="Select all accounts on this page"></th><th>Name</th><th>School ID</th><th>Role</th><th>Department / Office</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="hover-row" tabindex="0">
                            <td>@unless($user->is(auth()->user()))<input type="checkbox" name="ids[]" value="{{ $user->id }}" form="users-bulk-form" aria-label="Select {{ $user->full_name }}">@endunless</td>
                            <td><strong>{{ $user->full_name }}</strong><span class="hover-card">{{ implode("\n", array_filter([
                                'Email: '.$user->email,
                                'Role: '.$user->role->label(),
                                $user->hasRole(\App\Enums\Role::Student)
                                    ? 'CYS: '.($user->courseRecord?->name ?? $user->course ?? '—').' · Year '.($user->yearRecord?->year_level ?? $user->year ?? '—').' · Section '.($user->sectionRecord?->name ?? $user->section ?? '—')
                                    : 'Position: '.($user->positionRecord?->name ?? $user->position ?? '—'),
                                'Department: '.($user->department?->name ?? '—'),
                                'Office: '.($user->office?->name ?? '—'),
                                'Status: '.($user->archived_at ? 'Archived' : ucfirst($user->status->value)),
                                $user->inactive_since ? 'Inactive since: '.$user->inactive_since->format('M j, Y') : null,
                            ])) }}</span></td>
                            <td>{{ $user->school_id }}</td><td>{{ $user->role->label() }}</td>
                            <td>{{ $user->department?->name ?? '—' }}<small class="cell-subtitle">{{ $user->office?->name ?? 'No office assigned' }}</small></td>
                            <td><span class="status-badge {{ $user->isActivated() ? 'status-completed' : 'status-rejected' }}">{{ $user->archived_at ? 'Archived' : ucfirst($user->status->value) }}</span>
                                @if ($user->inactive_since)<small class="cell-subtitle">Inactive since {{ $user->inactive_since->format('M j, Y') }}</small>@endif</td>
                            <td class="action-cell">
                                @if (! request()->boolean('archived'))
                                    <button class="text-action" type="button" data-modal-open="user-modal" data-action="{{ route('admin.users.update', $user) }}" data-method="PUT" data-values="{{ json_encode($user->only(['firstname','surname','school_id','email','department_id','office_id','position_id','course_id','student_year_id','section_id','role','status'])) }}">Edit</button>
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.archive', $user) }}" data-confirm="Archive this account? Login will be blocked while archived, and its history will be kept.">@csrf<button class="danger-text" type="submit">Archive</button></form>
                                    @endunless
                                @else
                                    <form method="POST" action="{{ route('admin.users.restore', $user) }}">@csrf<button class="text-action" type="submit">Restore</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state compact"><strong>No users found</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $users->links() }}</div>
    </section>

    <dialog class="form-modal wide-modal" id="user-modal">
        <form method="POST" action="{{ route('admin.users.store') }}" class="modal-form" data-modal-form>
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_form_action" value="{{ old('_form_action') }}"><input type="hidden" name="_form_method" value="{{ old('_form_method') }}"><input type="hidden" name="_form_target" value="{{ old('_form_target') }}">
            <div class="modal-heading"><div><p class="eyebrow">USER ACCOUNT</p><h2>Add or edit account</h2></div><button class="icon-button" type="button" data-modal-close aria-label="Close">×</button></div>
            <div class="form-grid" data-user-fields>
                <label>First name<input name="firstname" value="{{ old('firstname') }}" required></label><label>Surname<input name="surname" value="{{ old('surname') }}" required></label>
                <label>School ID<input name="school_id" value="{{ old('school_id') }}" maxlength="30" required></label><label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
                <label>Role<select name="role" required>@foreach (\App\Enums\Role::cases() as $role)<option value="{{ $role->value }}" @selected(old('role', 'student') === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
                <label>Status<select name="status" required>@foreach (\App\Enums\UserStatus::cases() as $status)<option value="{{ $status->value }}" @selected(old('status', 'activated') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></label>
                <label class="user-worker-fields">Department<select name="department_id"><option value="">Select department</option>@foreach ($departments as $department)<option value="{{ $department->id }}" data-active="{{ $department->status === 'active' && ! $department->archived_at ? '1' : '0' }}" @selected(old('department_id') == $department->id)>{{ $department->name }}{{ $department->status === 'inactive' || $department->archived_at ? ' (inactive)' : '' }}</option>@endforeach</select></label>
                <label class="user-worker-fields">Office<select name="office_id"><option value="">Select office</option>@foreach ($offices as $office)<option value="{{ $office->id }}" data-active="{{ $office->status === 'active' && ! $office->archived_at ? '1' : '0' }}" @selected(old('office_id') == $office->id)>{{ $office->name }}{{ $office->status === 'inactive' || $office->archived_at ? ' (inactive)' : '' }}</option>@endforeach</select></label>
                <label class="user-worker-fields">Position<select name="position_id"><option value="">Select position</option>@foreach ($positions as $position)<option value="{{ $position->id }}" data-role="{{ $position->role }}" data-active="{{ $position->status === 'active' && ! $position->archived_at ? '1' : '0' }}" @selected(old('position_id') == $position->id)>{{ $position->name }} ({{ $position->role === 'both' ? 'Admin and Staff' : ucfirst($position->role) }}){{ $position->status === 'inactive' || $position->archived_at ? ' (inactive)' : '' }}</option>@endforeach</select></label>
                <label class="user-student-fields">Course<select name="course_id"><option value="">Select course</option>@foreach ($courses as $course)<option value="{{ $course->id }}" data-active="{{ $course->status === 'active' && ! $course->archived_at ? '1' : '0' }}" @selected(old('course_id') == $course->id)>{{ $course->name }} ({{ $course->code }}){{ $course->status === 'inactive' || $course->archived_at ? ' (inactive)' : '' }}</option>@endforeach</select></label>
                <label class="user-student-fields">Year<select name="student_year_id" disabled><option value="">Select year</option>@foreach ($years as $year)<option value="{{ $year->id }}" data-course-id="{{ $year->course_id }}" data-active="{{ $year->status === 'active' && ! $year->archived_at ? '1' : '0' }}" @selected(old('student_year_id') == $year->id)>{{ $year->year_level }}{{ $year->status === 'inactive' || $year->archived_at ? ' (inactive)' : '' }}</option>@endforeach</select></label>
                <label class="user-student-fields">Section<select name="section_id" disabled><option value="">Select section</option>@foreach ($sections as $section)<option value="{{ $section->id }}" data-year-id="{{ $section->student_year_id }}" data-active="{{ $section->status === 'active' && ! $section->archived_at ? '1' : '0' }}" @selected(old('section_id') == $section->id)>{{ $section->name }}{{ $section->status === 'inactive' || $section->archived_at ? ' (inactive)' : '' }}</option>@endforeach</select></label>
                <label class="form-span">Temporary password <input type="password" name="password" minlength="12" autocomplete="new-password"><small>Required for new accounts; leave blank to keep the existing password.</small></label>
            </div>
            <div class="modal-actions"><button class="secondary-button" type="button" data-modal-close>Cancel</button><button class="primary-button" type="submit">Save account</button></div>
        </form>
    </dialog>
@endsection
