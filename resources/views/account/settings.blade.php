@extends('layouts.app')

@section('title', 'Account settings')

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">ACCOUNT</p>
            <h1>Account Settings</h1>
            <p class="page-subtitle">Review your account details, update your profile, and change your password.</p>
        </div>
    </header>

    <section class="panel form-panel" aria-labelledby="account-details-heading">
        <div class="panel-heading">
            <div><p class="eyebrow">ACCOUNT DETAILS</p><h2 id="account-details-heading">Your account</h2></div>
        </div>
        <div class="form-body">
            <div class="field-row">
                <div class="field">
                    <label for="school_id_display">School ID</label>
                    <input id="school_id_display" type="text" value="{{ $user->school_id }}" readonly>
                </div>
                <div class="field">
                    <label for="email_display">Email</label>
                    <input id="email_display" type="text" value="{{ $user->email }}" readonly>
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="role_display">Role</label>
                    <input id="role_display" type="text" value="{{ $user->role->label() }}" readonly>
                </div>
                <div class="field">
                    <label for="department_display">Department</label>
                    <input id="department_display" type="text" value="{{ $user->department?->name ?? '—' }}" readonly>
                </div>
            </div>
            <p class="muted">School ID, email, role and department can only be changed by an administrator.</p>
        </div>
    </section>

    <section class="panel form-panel" aria-labelledby="profile-heading">
        <div class="panel-heading">
            <div><p class="eyebrow">PROFILE</p><h2 id="profile-heading">Edit profile</h2></div>
        </div>
        <form class="form-body" method="POST" action="{{ route('account.profile') }}" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="field-row">
                <div class="field">
                    <label for="firstname">First name</label>
                    <input id="firstname" name="firstname" type="text" maxlength="100" required value="{{ old('firstname', $user->firstname) }}">
                    @error('firstname') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="surname">Surname</label>
                    <input id="surname" name="surname" type="text" maxlength="100" {{ filled($user->surname) ? 'required' : '' }} value="{{ old('surname', $user->surname) }}">
                    @error('surname') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($user->role === \App\Enums\Role::Student)
                <div class="adm-form-row">
                    <div class="field">
                        <label for="course">Course <span class="muted">(optional)</span></label>
                        <input id="course" name="course" type="text" maxlength="100" value="{{ old('course', $user->course) }}">
                        @error('course') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label for="year">Year <span class="muted">(optional)</span></label>
                        <input id="year" name="year" type="number" min="1" max="10" step="1" value="{{ old('year', $user->year) }}">
                        @error('year') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label for="section">Section <span class="muted">(optional)</span></label>
                        <input id="section" name="section" type="text" maxlength="50" value="{{ old('section', $user->section) }}">
                        @error('section') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif

            <div class="adm-form-submit">
                <button class="button" type="submit">Save changes</button>
                <a class="button button-ghost" href="{{ route('account.edit') }}">Cancel</a>
            </div>
        </form>
    </section>

    <section class="panel form-panel" aria-labelledby="password-heading">
        <div class="panel-heading">
            <div><p class="eyebrow">SECURITY</p><h2 id="password-heading">Change password</h2></div>
        </div>
        <form class="form-body" method="POST" action="{{ route('account.password') }}" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="current_password">Current password</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
                @error('current_password') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="password">New password</label>
                    <input id="password" name="password" type="password" minlength="12" required autocomplete="new-password">
                    @error('password') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" required autocomplete="new-password">
                </div>
            </div>
            <p class="muted">Use at least 12 characters, and a password you do not use anywhere else.</p>
            <div class="adm-form-submit">
                <button class="button" type="submit">Change password</button>
            </div>
        </form>
    </section>
@endsection
