@extends('layouts.portal')

@php($isStudent = auth()->user()->hasRole('student'))
@section('title', $documentRequest->reference)
@section('heading', 'Request '.$documentRequest->reference)
@section('subtitle', $documentRequest->documentType->name.' · Submitted '.$documentRequest->created_at->format('M j, Y g:i A'))

@section('content')
    <div class="detail-grid">
        <section class="panel module-panel">
            <div class="panel-heading"><div><p class="eyebrow">CURRENT STATUS</p><h2>{{ $documentRequest->status->value }}</h2></div><span class="status-badge {{ 'status-'.\Illuminate\Support\Str::slug($documentRequest->status->value) }}">{{ $documentRequest->current_stage->value }}</span></div>
            <div class="detail-body">
                <div class="progress-track"><span style="width: {{ $documentRequest->progress }}%"></span></div>
                <div class="detail-facts">
                    <div><small>Document</small><strong>{{ $documentRequest->documentType->name }}</strong></div>
                    <div><small>Fee at pickup</small><strong>₱{{ number_format((float) $documentRequest->documentType->fee, 2) }} · paid at pickup</strong></div>
                    <div><small>Purpose</small><strong>{{ $documentRequest->purpose ?: 'Not provided' }}</strong></div>
                    @if ($documentRequest->requester_remarks)<div><small>Student remarks</small><strong>{{ $documentRequest->requester_remarks }}</strong></div>@endif
                    <div><small>Collection</small><strong>Pick-up at the Registrar office</strong></div>
                    <div><small>Estimated completion</small><strong>{{ $documentRequest->estimated_completion_date?->format('M j, Y') ?? 'Not yet estimated' }}</strong></div>
                    @unless ($isStudent)
                        <div><small>Student</small><strong>{{ $documentRequest->student->full_name }} ({{ $documentRequest->student->school_id }})</strong></div>
                        <div><small>Contact</small><strong>{{ $documentRequest->student->email }}</strong></div>
                        <div><small>Course · Year · Section</small><strong>{{ $documentRequest->student->courseRecord?->name ?? $documentRequest->student->course ?? 'Not set' }} · {{ $documentRequest->student->yearRecord?->year_level ?? $documentRequest->student->year ?? 'Not set' }} · {{ $documentRequest->student->sectionRecord?->name ?? $documentRequest->student->section ?? 'Not set' }}</strong></div>
                    @endunless
                </div>
            </div>
        </section>

        <section class="panel module-panel">
            <div class="panel-heading"><div><p class="eyebrow">REQUIREMENTS</p><h2>Uploaded documents</h2></div></div>
            <div class="stack-list">
                @forelse ($documentRequest->documentType->requirements as $requirement)
                    @php($upload = $documentRequest->uploads->firstWhere('document_requirement_id', $requirement->id))
                    <article class="requirement-review">
                        <div class="requirement-review-head">
                            <div><strong>{{ $requirement->name }}</strong><small>{{ $requirement->is_required ? 'Required' : 'Optional' }} · {{ $requirement->description }}</small></div>
                            @if ($upload)
                                <a class="text-action" href="{{ route('request-files.download', [$documentRequest, $upload]) }}">{{ $upload->original_name }}</a>
                            @else
                                <span class="status-badge status-rejected">Not uploaded</span>
                            @endif
                        </div>
                        @if (! $isStudent && $upload && ! in_array($documentRequest->status, [\App\Enums\RequestStatus::Completed, \App\Enums\RequestStatus::Rejected], true))
                            @if ($upload->ai_review_status)
                                <p class="ai-review-note"><strong>AI recommendation (not a final decision):</strong> {{ str_replace('_', ' ', ucfirst($upload->ai_review_status)) }} — {{ $upload->ai_review_remarks }} AI cannot verify authenticity; staff must make the final decision.</p>
                            @endif
                            <form method="POST" action="{{ route('request-management.ai-review', [$documentRequest, $upload]) }}" class="ai-review-form">
                                @csrf
                                <button class="secondary-button" type="submit">Get AI legibility/completeness recommendation</button>
                            </form>
                            <form method="POST" action="{{ route('request-management.verify', [$documentRequest, $upload]) }}" class="inline-review-form">
                                @csrf
                                <select name="is_verified" aria-label="Verification status for {{ $requirement->name }}">
                                    <option value="1" @selected($upload->is_verified)>Verified</option>
                                    <option value="0" @selected(! $upload->is_verified)>Needs correction</option>
                                </select>
                                <input name="verification_remarks" value="{{ $upload->verification_remarks }}" placeholder="Review note (optional)">
                                <button class="secondary-button" type="submit">Save review</button>
                            </form>
                        @elseif ($isStudent && $upload && ! $upload->is_verified && $upload->verification_remarks)
                            <p class="correction-note"><strong>Needs correction:</strong> {{ $upload->verification_remarks }}</p>
                            <form method="POST" action="{{ route('requests.requirements.replace', [$documentRequest, $upload]) }}" enctype="multipart/form-data" class="inline-review-form">
                                @csrf
                                <input type="file" name="requirement_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                <button class="secondary-button" type="submit">Upload replacement</button>
                            </form>
                        @elseif ($upload)
                            <small class="verification-note">{{ $upload->is_verified ? 'Verified' : 'Awaiting verification' }}{{ $upload->verification_remarks ? ' · '.$upload->verification_remarks : '' }}</small>
                        @endif
                    </article>
                @empty
                    <p class="empty-inline">No requirements are configured for this document.</p>
                @endforelse
            </div>
        </section>
    </div>

    @unless ($isStudent)
        @if (! in_array($documentRequest->status, [\App\Enums\RequestStatus::Completed, \App\Enums\RequestStatus::Rejected], true))
            <section class="panel module-panel">
                <div class="panel-heading"><div><p class="eyebrow">PROCESSING ACTIONS</p><h2>Update request</h2></div></div>
                <div class="workflow-actions">
                    @if ($documentRequest->status === \App\Enums\RequestStatus::Pending)
                        <form method="POST" action="{{ route('request-management.start', $documentRequest) }}">@csrf<button class="primary-button" type="submit">Verify documents & start processing</button></form>
                    @elseif ($documentRequest->status === \App\Enums\RequestStatus::InProgress)
                        <form method="POST" action="{{ route('request-management.advance', $documentRequest) }}">@csrf<button class="primary-button" type="submit">Complete {{ $documentRequest->current_stage->value }}</button></form>
                    @endif
                </div>
            </section>
        @endif
    @else
        @if ($documentRequest->status === \App\Enums\RequestStatus::ReadyForPickup)
            <section class="panel module-panel action-banner">
                <div><strong>Your document is ready for pickup.</strong><p>Confirm after you have received the document from the office.</p></div>
                <form method="POST" action="{{ route('requests.release', $documentRequest) }}">@csrf<button class="primary-button" type="submit">Confirm collection</button></form>
            </section>
        @endif
    @endunless

    <section class="panel module-panel">
        <div class="panel-heading"><div><p class="eyebrow">ACTIVITY</p><h2>Request history</h2></div><span class="count-pill">{{ $documentRequest->history->count() }} updates</span></div>
        <ol class="activity-list">
            @forelse ($documentRequest->history as $entry)
                <li>
                    <span class="activity-dot"></span>
                    <div><strong>{{ $entry->event }}</strong><p>{{ $entry->remarks ?: trim(($entry->from_status ? $entry->from_status.' → ' : '').($entry->to_status ?? $entry->to_stage ?? '')) }}</p><small>{{ $entry->created_at->format('M j, Y g:i A') }} · {{ $entry->actor?->full_name ?? 'System' }}</small></div>
                </li>
            @empty
                <li><div><strong>Request submitted</strong><small>{{ $documentRequest->created_at->format('M j, Y g:i A') }}</small></div></li>
            @endforelse
        </ol>
    </section>
    <a class="text-action back-link" href="{{ $isStudent ? route('requests.index') : route('request-management.index') }}">← Back to requests</a>
@endsection
