@extends('layouts.app')

@section('title', $documentRequest->reference)

@php
    $logLabels = [
        'verified' => 'Verified automatically',
        'held' => 'Held for correction',
        'stage_advanced' => 'Stage updated',
        'ready_for_pickup' => 'Ready for pick-up',
    ];
    $student = $documentRequest->student;
@endphp

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">{{ $documentRequest->reference }}</p>
            <h1>{{ $documentRequest->documentType?->name ?? 'Document request' }}</h1>
            <p class="page-subtitle">
                <span class="status-badge status-{{ \Illuminate\Support\Str::slug($documentRequest->status->value) }}">{{ $documentRequest->status->value }}</span>
                @if ($documentRequest->is_archived) <span class="count-pill">Archived</span> @endif
                <span class="count-pill">{{ $documentRequest->current_stage?->value }} · {{ $documentRequest->progress }}%</span>
            </p>
        </div>
        <a class="button button-ghost" href="{{ route('staff.requests.index') }}">Back to queue</a>
    </header>

    <div class="alert alert-info" role="note">
        Processing is automatic. Staff cannot change this request's status or approve payments.
    </div>

    <x-request-timeline :request="$documentRequest" />

    <div class="dashboard-columns">
        <div class="stack">
            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">AUTOMATED CHECK</p><h2>Evaluator remarks</h2></div>
                    @if ($documentRequest->ai_confidence !== null)
                        <span class="count-pill">Confidence {{ $documentRequest->ai_confidence }}%</span>
                    @endif
                </div>
                <div class="form-body">
                    @if ($documentRequest->ai_checked_at === null)
                        <p class="muted">Not checked yet.</p>
                    @else
                        <p>{!! nl2br(e($documentRequest->ai_remarks ?: 'No remarks.')) !!}</p>
                        <p class="muted">Checked {{ $documentRequest->ai_checked_at->format('M j, Y g:i A') }}</p>
                    @endif
                    <p class="muted">These checks are rule-based (file readability, required uploads, payment details). They do not read the content of documents.</p>
                </div>
            </section>

            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">PAYMENT</p><h2>Online payment</h2></div>
                    <span class="status-badge status-{{ \Illuminate\Support\Str::slug($documentRequest->payment_status->value) }}">{{ $documentRequest->payment_status->value }}</span>
                </div>
                <dl class="detail-list">
                    <div><dt>Fee</dt><dd>{{ number_format((float) ($documentRequest->documentType?->fee ?? 0), 2) }}</dd></div>
                    <div><dt>Method</dt><dd>{{ $documentRequest->payment_method ?: '—' }}</dd></div>
                    <div><dt>Reference number</dt><dd>{{ $documentRequest->payment_reference ?: '—' }}</dd></div>
                    <div><dt>Amount entered</dt><dd>{{ number_format((float) $documentRequest->payment_amount, 2) }}</dd></div>
                    <div><dt>Date paid</dt><dd>{{ $documentRequest->payment_paid_on?->format('M j, Y') ?? '—' }}</dd></div>
                    <div><dt>Verified</dt><dd>{{ $documentRequest->payment_verified_at?->format('M j, Y g:i A') ?? 'Not yet' }}</dd></div>
                    @if ($documentRequest->payment_proof_path)
                        <div><dt>Proof</dt><dd><a href="{{ route('requests.payment-proof', $documentRequest) }}">Download proof of payment</a></dd></div>
                    @endif
                </dl>
            </section>

            @php($uploadsByRequirement = $documentRequest->uploads->keyBy('document_requirement_id'))
            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">REQUIREMENTS</p><h2>Requirement checklist</h2></div></div>
                @if ($documentRequest->documentType === null || $documentRequest->documentType->requirements->isEmpty())
                    <div class="empty-state compact"><strong>No requirements</strong><p>This document type has no configured requirements.</p></div>
                @else
                    <ul class="plain-list">
                        @foreach ($documentRequest->documentType->requirements as $requirement)
                            @php($upload = $uploadsByRequirement->get($requirement->id))
                            <li>
                                <span>
                                    <strong>{{ $requirement->name }}</strong>
                                    <small class="muted">{{ $requirement->is_required ? 'Required' : 'Optional' }}</small>
                                </span>
                                @if ($upload === null)
                                    <span class="status-badge {{ $requirement->is_required ? 'status-rejected' : 'status-cancelled' }}">{{ $requirement->is_required ? 'Missing' : 'Not provided' }}</span>
                                @elseif ($upload->is_verified)
                                    <span class="status-badge status-completed">Verified</span>
                                @else
                                    <span class="status-badge status-submitted">Uploaded</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">UPLOADS</p><h2>Supporting documents</h2></div></div>
                @if ($documentRequest->uploads->isEmpty())
                    <div class="empty-state compact"><strong>No uploads</strong><p>No supporting documents on file.</p></div>
                @else
                    <ul class="plain-list">
                        @foreach ($documentRequest->uploads as $upload)
                            <li>
                                <span>
                                    <strong>{{ $upload->requirement?->name ?? 'Document' }}</strong><br>
                                    <a href="{{ route('requests.requirement', [$documentRequest, $upload]) }}">{{ $upload->original_name }}</a>
                                </span>
                                <span class="status-badge {{ $upload->is_verified ? 'status-completed' : '' }}">{{ $upload->is_verified ? 'Verified' : 'Not yet verified' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="stack">
            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">STUDENT</p><h2>Requester</h2></div></div>
                <dl class="detail-list">
                    <div><dt>Name</dt><dd>{{ $student?->full_name ?? '—' }}</dd></div>
                    <div><dt>School ID</dt><dd>{{ $student?->school_id ?? '—' }}</dd></div>
                    <div><dt>Department</dt><dd>{{ $student?->department?->name ?? '—' }}</dd></div>
                    <div><dt>Course / year / section</dt><dd>{{ collect([$student?->course, $student?->year, $student?->section])->filter()->implode(' · ') ?: '—' }}</dd></div>
                </dl>
            </section>

            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">DETAILS</p><h2>Request details</h2></div></div>
                <dl class="detail-list">
                    <div><dt>Submitted</dt><dd>{{ $documentRequest->created_at?->format('M j, Y g:i A') }}</dd></div>
                    <div><dt>Estimated completion</dt><dd>{{ $documentRequest->estimated_completion_date?->format('M j, Y') ?? '—' }}</dd></div>
                    <div><dt>Claiming method</dt><dd>{{ $documentRequest->claiming_method === \App\Enums\ClaimingMethod::Email ? 'Email (legacy request)' : 'Pick-up' }}</dd></div>
                    <div><dt>Purpose</dt><dd>{{ $documentRequest->purpose ?: '—' }}</dd></div>
                    <div><dt>Student remarks</dt><dd>{{ $documentRequest->requester_remarks ?: '—' }}</dd></div>
                    @if ($documentRequest->status === \App\Enums\RequestStatus::Rejected)
                        <div><dt>Legacy rejection reason</dt><dd>{{ $documentRequest->rejection_reason ?: '—' }}</dd></div>
                    @endif
                    @if ($documentRequest->status === \App\Enums\RequestStatus::Cancelled)
                        <div><dt>Cancelled</dt><dd>{{ $documentRequest->cancelled_at?->format('M j, Y g:i A') }}{{ $documentRequest->cancel_reason ? ' — '.$documentRequest->cancel_reason : '' }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">AUDIT</p><h2>Automation log</h2></div></div>
                @if ($documentRequest->aiLogs->isEmpty())
                    <div class="empty-state compact"><strong>No automation activity</strong><p>Entries appear as the request is processed.</p></div>
                @else
                    <ul class="plain-list">
                        @foreach ($documentRequest->aiLogs as $log)
                            <li>
                                <span>
                                    <strong>{{ $logLabels[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)) }}</strong>
                                    @if ($log->from_stage || $log->to_stage)<br><small class="muted">{{ $log->from_stage ?: '—' }} → {{ $log->to_stage ?: '—' }}</small>@endif
                                    @if ($log->details)<br><small class="muted">{{ $log->details }}</small>@endif
                                </span>
                                <small class="muted">{{ $log->created_at?->format('M j, g:i A') }}</small>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection
