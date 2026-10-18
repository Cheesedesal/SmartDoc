@extends('layouts.app')

@section('title', 'Announcements')

@php
    $audienceLabels = [
        \App\Enums\Audience::All->value => 'Everyone',
        \App\Enums\Audience::Admin->value => 'Administrators',
        \App\Enums\Audience::Staff->value => 'Staff',
        \App\Enums\Audience::Student->value => 'Students',
    ];
@endphp

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">CAMPUS UPDATES</p>
            <h1>Announcements</h1>
            <p class="page-subtitle">Notices and updates published for your account.</p>
        </div>
        <div class="header-date">{{ number_format($announcements->total()) }} {{ \Illuminate\Support\Str::plural('announcement', $announcements->total()) }}</div>
    </header>

    <div class="ann-feed">
        @forelse ($announcements as $announcement)
            <article class="ann-card" aria-labelledby="ann-title-{{ $announcement->id }}">
                <div class="ann-meta">
                    <time class="ann-date" datetime="{{ $announcement->published_at?->toDateString() }}">{{ $announcement->published_at?->format('F j, Y') ?? 'Undated' }}</time>
                    <span class="ann-audience">{{ $audienceLabels[$announcement->audience?->value] ?? $announcement->audience?->value }}</span>
                </div>

                <h2 class="ann-title" id="ann-title-{{ $announcement->id }}">{{ $announcement->title }}</h2>
                <p class="ann-body">{{ strip_tags($announcement->message) }}</p>

                @if ($announcement->author || $announcement->expires_at)
                    <div class="ann-foot">
                        @if ($announcement->author)
                            <span>Posted by <strong>{{ $announcement->author->full_name }}</strong></span>
                        @endif
                        @if ($announcement->expires_at)
                            <span>Visible until <strong>{{ $announcement->expires_at->format('F j, Y') }}</strong></span>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <section class="panel">
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true">◉</span>
                    <strong>No announcements</strong>
                    <p>New updates for your account will appear here.</p>
                </div>
            </section>
        @endforelse

        {{ $announcements->links('pagination.smartdoc') }}
    </div>
@endsection
