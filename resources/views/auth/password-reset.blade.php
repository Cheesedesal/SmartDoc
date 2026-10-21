<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose new password | SmartDoc</title>
    @vite(['resources/css/app.css'])
</head>
<body class="dashboard-page">
    <main class="auth-card">
        <h1>Choose a new password</h1>
        @if (session('error'))<div class="notice notice-error">{{ session('error') }}</div>@endif
        <form method="POST" action="{{ route('password.update') }}" class="module-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>Email address<input type="email" name="email" value="{{ old('email', $email) }}" required></label>
            <label>New password<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
            <label>Confirm new password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
            @foreach ($errors->all() as $error)<div class="notice notice-error">{{ $error }}</div>@endforeach
            <button class="primary-button" type="submit">Update password</button>
        </form>
    </main>
</body>
</html>
