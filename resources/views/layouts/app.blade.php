<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SmartDoc | @yield('title', 'Portal')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite('resources/css/app.css')
</head>
<body class="dashboard-page">
@php
    $navUser = auth()->user();
    $navRole = $navUser->role;
    $isStudent = $navRole === \App\Enums\Role::Student;
@endphp
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/logo.png') }}" alt="Bulacan Polytechnic College">
            <span class="brand-name">SmartDoc</span>
            <span class="brand-caption">AI Document Request System</span>
        </a>

        <nav class="side-nav" aria-label="Main navigation">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                <span class="nav-icon" aria-hidden="true">⌂</span>Dashboard
            </a>

            @if ($isStudent)
                <a class="nav-link {{ request()->routeIs('student.requests.index', 'student.requests.show') ? 'active' : '' }}" href="{{ route('student.requests.index') }}">
                    <span class="nav-icon" aria-hidden="true">▤</span>My requests
                </a>
                <a class="nav-link {{ request()->routeIs('student.requests.create') ? 'active' : '' }}" href="{{ route('student.requests.create') }}">
                    <span class="nav-icon" aria-hidden="true">＋</span>New request
                </a>
            @else
                <a class="nav-link {{ request()->routeIs('staff.requests.*') ? 'active' : '' }}" href="{{ route('staff.requests.index') }}">
                    <span class="nav-icon" aria-hidden="true">▤</span>Request queue
                </a>
                <a class="nav-link {{ request()->routeIs('request-records.*') ? 'active' : '' }}" href="{{ route('request-records.index') }}" @if (request()->routeIs('request-records.*')) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">◷</span>Request records
                </a>
            @endif

            @if ($navRole === \App\Enums\Role::Admin)
                <a class="nav-link {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}" href="{{ route('admin.departments.index') }}">
                    <span class="nav-icon" aria-hidden="true">▦</span>Departments
                </a>
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                    <span class="nav-icon" aria-hidden="true">☻</span>Users
                </a>
                <a class="nav-link {{ request()->routeIs('admin.document-types.*', 'admin.requirements.*') ? 'active' : '' }}" href="{{ route('admin.document-types.index') }}">
                    <span class="nav-icon" aria-hidden="true">❏</span>Documents
                </a>
                <a class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('admin.announcements.index') }}">
                    <span class="nav-icon" aria-hidden="true">✎</span>Manage announcements
                </a>
            @endif

            <a class="nav-link {{ request()->routeIs('announcements.index') ? 'active' : '' }}" href="{{ route('announcements.index') }}" @if (request()->routeIs('announcements.index')) aria-current="page" @endif>
                <span class="nav-icon" aria-hidden="true">◉</span>Announcements
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-user">
                <span class="avatar">{{ mb_strtoupper(mb_substr($navUser->firstname ?: $navUser->full_name, 0, 1)) }}</span>
                <span class="sidebar-user-copy">
                    <strong>{{ $navUser->full_name }}</strong>
                    <small>{{ $navRole->label() }}</small>
                </span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-button" type="submit">Log out</button>
            </form>
        </div>
    </aside>

    <main class="main-content">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->has('request'))
            <div class="alert alert-error" role="alert">{{ $errors->first('request') }}</div>
        @endif

        @yield('content')

        <footer class="page-footer">SmartDoc <span>·</span> Bulacan Polytechnic College</footer>
    </main>
</body>
</html>
