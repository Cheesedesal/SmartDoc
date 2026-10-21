@extends('layouts.portal')

@section('title', 'Account settings')
@section('heading', 'Account settings')
@section('subtitle', 'Update only your profile photo, email address, and password.')

@section('content')
    <section class="panel module-panel account-settings-panel">
        <form id="account-settings-form" method="POST" action="{{ route('account.settings.update') }}" enctype="multipart/form-data" class="module-form account-settings-form">
            @csrf
            @method('PUT')
            <div class="profile-photo-preview">
                <label class="profile-photo-picker" for="profile-photo-input" title="Choose a profile photo">
                @if ($user->profile_photo_path)
                    <img class="profile-photo-large" data-profile-preview src="{{ route('account.avatar') }}" alt="Current profile photo">
                @else
                    <span class="avatar profile-avatar-large" data-profile-initial>{{ mb_strtoupper(mb_substr($user->firstname ?: $user->full_name, 0, 1)) }}</span>
                @endif
                    <span class="profile-photo-overlay" aria-hidden="true">Change photo</span>
                    <input class="profile-photo-input" id="profile-photo-input" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp" aria-label="Choose profile photo" data-profile-preview-input>
                </label>
                <div class="profile-settings-details">
                    <strong>{{ $user->full_name }}</strong>
                    <span>{{ $user->role->label() }}</span>
                </div>
            </div>
            <small class="muted-copy">Select your profile icon to upload a JPG, PNG, or WEBP image (maximum 2 MB).</small>
            <section class="account-readonly-details" aria-label="Profile details">
                <h2>Profile details</h2>
                @if ($user->role === \App\Enums\Role::Student)
                    <dl><div><dt>Name</dt><dd>{{ $user->full_name }}</dd></div>
                    <div><dt>Course / Program</dt><dd>{{ $user->courseRecord?->name ?: ($user->course ?: 'Not set') }}</dd></div>
                    <div><dt>Year</dt><dd>{{ $user->yearRecord?->year_level ?: ($user->year ?: 'Not set') }}</dd></div>
                    <div><dt>Section</dt><dd>{{ $user->sectionRecord?->name ?: ($user->section ?: 'Not set') }}</dd></div></dl>
                @else
                    <dl><div><dt>Name</dt><dd>{{ $user->full_name }}</dd></div>
                    <div><dt>Department</dt><dd>{{ $user->department?->name ?: 'Not set' }}</dd></div>
                    <div><dt>Office</dt><dd>{{ $user->office?->name ?: 'Not set' }}</dd></div>
                    <div><dt>Position</dt><dd>{{ $user->positionRecord?->name ?: ($user->position ?: 'Not set') }}</dd></div></dl>
                @endif
                <p>For corrections to these details, contact an administrator.</p>
            </section>
            <label>Email address
                <input type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" required>
                <small>To change your email, enter your current password below and select Save changes.</small>
            </label>
            <hr>
            <h2>Change password</h2>
            <label>Current password<input type="password" name="current_password" autocomplete="current-password"></label>
            <label>New password<input type="password" name="password" minlength="12" autocomplete="new-password"><small>Leave both password fields empty to keep your current password.</small></label>
            <label>Confirm new password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></label>
        </form>
        <div class="account-form-actions">
            <button class="primary-button" type="submit" form="account-settings-form">Save changes</button>
            <form method="POST" action="{{ route('account.password-reset.email') }}" class="account-reset-form">
                @csrf
                <button class="secondary-button" type="submit">Reset password</button>
            </form>
        </div>
    </section>
@endsection
