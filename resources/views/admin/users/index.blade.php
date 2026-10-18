@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">ADMINISTRATION</p>
            <h1>User Management</h1>
            <p class="page-subtitle">Create and manage student, staff and administrator accounts.</p>
        </div>
    </header>

    <div class="stats-grid">
        <div class="stat-card"><span class="stat-label">Total users</span><span class="stat-value">{{ $counts['total'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Students</span><span class="stat-value">{{ $counts['students'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Staff</span><span class="stat-value">{{ $counts['staff'] }}</span></div>
        <div class="stat-card"><span class="stat-label">Administrators</span><span class="stat-value">{{ $counts['admins'] }}</span></div>
    </div>

    <section class="panel form-panel">
        <div class="panel-heading"><div><p class="eyebrow">SEARCH</p><h2>User search</h2></div></div>
        <form class="form-body" method="GET" action="{{ route('admin.users.index') }}">
            <div class="adm-form-row">
                <div class="field">
                    <label for="q">ID, name or email</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" autocomplete="off">
                </div>
                <div class="field">
                    <label for="f-role">Role</label>
                    <select id="f-role" name="role">
                        <option value="">All roles</option>
                        @foreach (\App\Enums\Role::cases() as $role)
                            <option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="f-status">Status</label>
                    <select id="f-status" name="status">
                        <option value="">All statuses</option>
                        <option value="activated" @selected($filters['status'] === 'activated')>Active</option>
                        <option value="deactivated" @selected($filters['status'] === 'deactivated')>Inactive</option>
                    </select>
                </div>
                <div class="field">
                    <label for="f-dept">Department</label>
                    <select id="f-dept" name="department_id">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="adm-form-submit">
                    <button class="button" type="submit">Search</button>
                    <a class="button button-ghost" href="{{ route('admin.users.index') }}">Clear</a>
                </div>
            </div>
        </form>
    </section>

    <section class="panel form-panel" id="user-form">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">{{ $editing ? 'EDIT' : 'NEW' }}</p>
                <h2>{{ $editing ? 'Update user' : 'Add user' }}</h2>
            </div>
            @if ($editing)
                <a class="button button-ghost button-sm" href="{{ route('admin.users.index') }}">Cancel</a>
            @endif
        </div>

        <form class="form-body" method="POST" autocomplete="off"
              action="{{ $editing ? route('admin.users.update', $editing) : route('admin.users.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="adm-form-row">
                <div class="field">
                    <label for="school_id">School / login ID</label>
                    <input id="school_id" name="school_id" type="text" maxlength="30" required value="{{ old('school_id', $editing?->school_id) }}">
                    @error('school_id') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="firstname">First name</label>
                    <input id="firstname" name="firstname" type="text" maxlength="100" required value="{{ old('firstname', $editing?->firstname) }}">
                    @error('firstname') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="surname">Surname</label>
                    <input id="surname" name="surname" type="text" maxlength="100" required value="{{ old('surname', $editing?->surname) }}">
                    @error('surname') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    @if ($editing)
                        <input id="email" type="email" value="{{ $editing->email }}" readonly>
                    @else
                        <input id="email" name="email" type="email" maxlength="255" required value="{{ old('email') }}">
                    @endif
                    @error('email') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="adm-form-row">
                <div class="field">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id">
                        <option value="">No department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) old('department_id', $editing?->department_id) === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="role">User role</label>
                    <select id="role" name="role" required>
                        @foreach (\App\Enums\Role::cases() as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $editing?->role?->value ?? 'student') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="activated" @selected(old('status', $editing?->status?->value ?? 'activated') === 'activated')>Active</option>
                        <option value="deactivated" @selected(old('status', $editing?->status?->value) === 'deactivated')>Inactive</option>
                    </select>
                    @error('status') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="password">{{ $editing ? 'New password (leave blank to keep)' : 'Initial password' }}</label>
                    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" @required(! $editing)>
                    @error('password') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            <div><button class="button" type="submit">{{ $editing ? 'Update User' : '+ Add User' }}</button></div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-heading"><div><p class="eyebrow">ACCOUNTS</p><h2>User account list</h2></div><span class="count-pill">{{ $users->total() }} found</span></div>
        <div class="table-wrap">
            <table class="data-table adm-table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->school_id }}</td>
                            <td>{{ $user->full_name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->department?->name ?? 'No Department' }}</td>
                            <td>{{ $user->role->label() }}</td>
                            <td><span class="status-badge status-{{ $user->status->value }}">{{ $user->status === \App\Enums\UserStatus::Activated ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a class="button button-ghost button-sm" href="{{ route('admin.users.index', array_merge(request()->query(), ['edit' => $user->id])) }}#user-form">Edit</a>
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?')">
                                        @csrf @method('DELETE')
                                        <button class="button button-danger button-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state compact"><strong>No users found</strong><p>Try clearing the search filters.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links('pagination.smartdoc') }}
    </section>
@endsection
