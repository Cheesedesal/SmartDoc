<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password | SmartDoc</title>
    @vite(['resources/css/app.css'])
</head>
<body class="dashboard-page">
    <main class="auth-card">
        <h1>Reset your password</h1>
        <p>Enter the email address linked to your SmartDoc account. If it matches, we will send a reset link.</p>
        @if (session('status'))<div class="notice notice-success">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="notice notice-error">{{ session('error') }}</div>@endif
        @error('email')<div class="notice notice-error">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route('password.email') }}" class="module-form">
            @csrf
            <label>Email address<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
            <button class="primary-button" type="submit">Send reset link</button>
        </form>
        <a class="text-action" href="{{ route('login') }}">Back to login</a>
    </main>
</body>
</html>
