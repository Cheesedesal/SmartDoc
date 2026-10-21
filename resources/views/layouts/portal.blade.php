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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    @php($role = auth()->user()->role)
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/logo.png') }}" alt="Bulacan Polytechnic College">
            <span class="brand-name">SmartDoc</span>
            <span class="brand-caption">AI Document Request System</span>
        </a>
        <nav class="side-nav" aria-label="Main navigation">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon" aria-hidden="true">⌂</span>Dashboard</a>
            <a class="nav-link {{ request()->routeIs('chatbot.*') ? 'active' : '' }}" href="{{ route('chatbot.index') }}"><span class="nav-icon" aria-hidden="true">✦</span>SmartDoc assistant</a>
            @if ($role === \App\Enums\Role::Student)
                <a class="nav-link {{ request()->routeIs('requests.*') ? 'active' : '' }}" href="{{ route('requests.index') }}"><span class="nav-icon" aria-hidden="true">▤</span>My requests & history</a>
                <a class="nav-link {{ request()->routeIs('announcements.index') ? 'active' : '' }}" href="{{ route('announcements.index') }}"><span class="nav-icon" aria-hidden="true">◉</span>Announcements</a>
            @else
                <a class="nav-link {{ request()->routeIs('request-management.*') ? 'active' : '' }}" href="{{ route('request-management.index') }}"><span class="nav-icon" aria-hidden="true">▤</span>Request management</a>
                <a class="nav-link {{ request()->routeIs('request-records.*') ? 'active' : '' }}" href="{{ route('request-records.index') }}"><span class="nav-icon" aria-hidden="true">◷</span>Request history</a>
                <a class="nav-link {{ request()->routeIs('announcements.*') ? 'active' : '' }}" href="{{ route('announcements.index') }}"><span class="nav-icon" aria-hidden="true">◉</span>Announcements</a>
                @if ($role === \App\Enums\Role::Admin)
                    <a class="nav-link {{ request()->routeIs('admin.chatbot-faqs.*') ? 'active' : '' }}" href="{{ route('admin.chatbot-faqs.index') }}"><span class="nav-icon" aria-hidden="true">☷</span>Manage FAQs</a>
                    <a class="nav-link {{ request()->routeIs('admin.users.*', 'admin.departments.*', 'admin.profile-options.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><span class="nav-icon" aria-hidden="true">♙</span>User management</a>
                    <nav class="nav-subnav" aria-label="User management pages">
                        <a class="nav-sublink {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.imports.show') && request()->route('type') === 'users' ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a>
                        <a class="nav-sublink {{ request()->routeIs('admin.departments.*') || request()->routeIs('admin.imports.show') && request()->route('type') === 'departments' ? 'active' : '' }}" href="{{ route('admin.departments.index') }}">Departments</a>
                        @foreach (['offices' => 'Office', 'positions' => 'Position', 'courses' => 'Course', 'years' => 'Year', 'sections' => 'Section'] as $catalog => $label)
                            <a class="nav-sublink {{ request()->routeIs('admin.profile-options.*') && request()->route('type') === $catalog || request()->routeIs('admin.imports.show') && request()->route('type') === $catalog ? 'active' : '' }}" href="{{ route('admin.profile-options.index', $catalog) }}">{{ $label }}</a>
                        @endforeach
                    </nav>
                    <a class="nav-link {{ request()->routeIs('admin.document-types.*') ? 'active' : '' }}" href="{{ route('admin.document-types.index') }}"><span class="nav-icon" aria-hidden="true">▧</span>Document types</a>
                    <a class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('admin.announcements.index') }}"><span class="nav-icon" aria-hidden="true">▣</span>Manage announcements</a>
                    <a class="nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}"><span class="nav-icon" aria-hidden="true">◴</span>Activity logs</a>
                    <a class="nav-link {{ request()->routeIs('admin.archive.*') ? 'active' : '' }}" href="{{ route('admin.archive.index') }}"><span class="nav-icon" aria-hidden="true">▣</span>Archive</a>
                @endif
            @endif
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-user">
                <a class="sidebar-profile-link" href="{{ route('account.settings') }}" title="Account settings">
                    @if (auth()->user()->profile_photo_path)
                        <img class="avatar-image" src="{{ route('account.avatar') }}" alt="">
                    @else
                        <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->firstname ?: auth()->user()->name, 0, 1)) }}</span>
                    @endif
                    <span class="sidebar-user-copy"><strong>{{ auth()->user()->full_name }}</strong><small>{{ $role->label() }} · Account settings</small></span>
                </a>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit">Log out</button></form>
        </div>
    </aside>
    <main class="main-content module-content">
        <header class="page-header">
            <div>
                <p class="eyebrow">SMARTDOC PORTAL</p>
                <h1>@yield('heading', 'SmartDoc')</h1>
                <p class="page-subtitle">@yield('subtitle')</p>
            </div>
            <div class="header-actions"><x-notification-bell /><div class="header-date">{{ now()->format('l, F j, Y') }}</div></div>
        </header>
        @if (session('status'))
            <div class="notice notice-success" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="notice notice-error" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="notice notice-error" role="alert">
                <strong>Please correct the following:</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
        <footer class="page-footer">SmartDoc <span>•</span> AI Document Request System</footer>
    </main>
    @stack('scripts')
</body>
</html>
