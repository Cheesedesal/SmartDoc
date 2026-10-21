@extends('layouts.portal')

@section('title', 'Announcements')
@section('heading', 'Announcements')
@section('subtitle', 'Current notices published for your account role.')

@section('content')
    <section class="announcement-feed">
        @forelse ($announcements as $announcement)
            <article class="panel announcement-card" id="announcement-{{ $announcement->id }}">
                <div class="announcement-card-date">{{ $announcement->published_at?->format('M j, Y') }}</div>
                <div><p class="eyebrow">{{ $announcement->audience->value === 'all' ? 'ALL USERS' : strtoupper($announcement->audience->value) }}</p><h2>{{ $announcement->title }}</h2><p>{{ $announcement->message }}</p><small>Posted by {{ $announcement->author?->full_name ?? 'SmartDoc' }}</small></div>
            </article>
        @empty
            <section class="panel empty-state"><strong>No announcements currently published</strong><p>New school notices will appear here.</p></section>
        @endforelse
        <div class="pagination-wrap">{{ $announcements->links() }}</div>
    </section>
@endsection
