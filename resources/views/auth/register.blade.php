<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartDoc Registration</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
        body{background:linear-gradient(135deg,#0F5810,#4E8F42);min-height:100vh;display:flex;justify-content:center;align-items:center;padding:40px}
        .card{width:100%;max-width:560px;background:#F0F0EE;border-radius:18px;padding:36px 32px;box-shadow:0 18px 40px rgba(0,0,0,.18);text-align:center;border-top:8px solid #F3DC16}
        .logo{width:100px;height:100px;object-fit:contain;margin:0 auto 18px;display:block}
        h1{font-size:30px;color:#0F5810;margin-bottom:24px;letter-spacing:1px}
        form{display:flex;flex-direction:column;text-align:left}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        label{font-size:14px;color:#0F5810;font-weight:600;margin:12px 0 6px;display:block}
        input{width:100%;padding:12px 14px;border:2px solid #d9d9d9;border-radius:10px;font-size:15px;background:#fff}
        input:focus{border-color:#0F5810;outline:none;box-shadow:0 0 0 4px rgba(15,88,16,.15)}
        .actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:22px}
        button{padding:14px 18px;border:none;border-radius:10px;background:#F3DC16;color:#0F5810;font-size:16px;font-weight:700;cursor:pointer;flex:1}
        button:hover{background:#A59A29;color:#fff}
        .secondary{display:inline-block;padding:14px 18px;border-radius:10px;background:#dfe8df;color:#0F5810;text-decoration:none;font-weight:600;text-align:center;flex:1}
        .error{margin-top:10px;padding:10px 12px;border-radius:8px;background:#fde8e8;color:#9b1c1c;font-size:14px}
        .status{margin-bottom:16px;padding:12px 14px;border-radius:8px;background:#e6f7ea;color:#14532d;font-size:14px}
        .meta{text-align:center;margin-top:14px;font-size:14px;color:#0F5810}
        .meta a{text-decoration:none;color:#0F5810;font-weight:700}
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ asset('images/logo.png') }}" alt="Bulacan Polytechnic College Logo" class="logo">
        <h1>REGISTER</h1>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('register') }}" autocomplete="off">
            @csrf

            <div class="two-col">
                <div>
                    <label for="firstname">First name</label>
                    <input id="firstname" type="text" name="firstname" value="{{ old('firstname') }}" required>
                    @error('firstname')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="surname">Surname</label>
                    <input id="surname" type="text" name="surname" value="{{ old('surname') }}" required>
                    @error('surname')<div class="error">{{ $message }}</div>@enderror
                </div>
            </div>

            <label for="school_id">School ID</label>
            <input id="school_id" type="text" name="school_id" value="{{ old('school_id') }}" required>
            @error('school_id')<div class="error">{{ $message }}</div>@enderror

            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            @error('email')<div class="error">{{ $message }}</div>@enderror

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>
            @error('password')<div class="error">{{ $message }}</div>@enderror

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>
            @error('password_confirmation')<div class="error">{{ $message }}</div>@enderror

            <div class="actions">
                <button type="submit">Create account</button>
                <a class="secondary" href="{{ route('login') }}">Back to login</a>
            </div>
        </form>
    </div>
</body>
</html>
