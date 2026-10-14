<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartDoc</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
        body{background:linear-gradient(135deg,#0F5810,#4E8F42);min-height:100vh;display:flex;justify-content:center;align-items:center;padding:40px}
        .card{width:100%;max-width:460px;background:#F0F0EE;border-radius:18px;padding:50px 40px;box-shadow:0 18px 40px rgba(0,0,0,.18);text-align:center;border-top:8px solid #F3DC16}
        .logo{width:100px;height:100px;object-fit:contain;margin:0 auto 18px;display:block}
        h1{font-size:30px;color:#0F5810;margin-bottom:30px;letter-spacing:1px}
        form{display:flex;flex-direction:column;text-align:left}
        label{font-size:14px;color:#0F5810;font-weight:600;margin:15px 0 6px}
        input{width:100%;padding:13px 15px;border:2px solid #d9d9d9;border-radius:10px;font-size:15px;background:#fff}
        input:focus{border-color:#0F5810;outline:none;box-shadow:0 0 0 4px rgba(15,88,16,.15)}
        button{margin-top:28px;padding:14px;border:none;border-radius:10px;background:#F3DC16;color:#0F5810;font-size:16px;font-weight:700;cursor:pointer}
        button:hover{background:#A59A29;color:#fff}
        .error{margin-top:14px;padding:10px 14px;border-radius:8px;background:#fde8e8;color:#9b1c1c;font-size:14px}
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ asset('images/logo.png') }}" alt="Bulacan Polytechnic College Logo" class="logo">
        <h1>LOGIN</h1>

        <form method="POST" action="{{ route('login') }}" autocomplete="off">
            @csrf
            <label for="school_id">ID</label>
            <input id="school_id" type="text" name="school_id" value="{{ old('school_id') }}" required autofocus>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            @error('school_id')<div class="error">{{ $message }}</div>@enderror
            @error('password')<div class="error">{{ $message }}</div>@enderror

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
