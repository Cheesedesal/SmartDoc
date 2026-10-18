@extends('layouts.app')

@section('title', $documentRequest->reference)

@php
    $logLabels = [
        'verified' => 'Verified automatically',
        'held' => 'Held for correction',
        'stage_advanced' => 'Stage updated',
        'ready_for_pickup' => 'Ready for pick-up',
    ];
    $hasPayment = $documentRequest->payment_status !== \App\Enums\PaymentStatus::NotRequired
        || (float) $documentRequest->documentType?->fee > 0;
@endphp

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">{{ $documentRequest->reference }}</p>
            <h1>{{ $documentRequest->documentType?->name ?? 'Document request' }}</h1>
            <p class="page-subtitle">
                <span class="status-badge status-{{ \Illuminate\Support\Str::slug($documentRequest->status->value) }}">{{ $documentRequest->status->value }}</span>
                @if ($documentRequest->is_archived) <span class="count-pill">Archived</span> @endif
            </p>
        </div>
        <a class="button button-ghost" href="{{ route('student.requests.index') }}">Back to my requests</a>
    </header>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">PROGRESS</p>
                <h2>{{ $documentRequest->current_stage?->value ?? 'Request Received' }}</h2>
            </div>
            <span class="count-pill">{{ $documentRequest->progress }}%</span>
        </div>
        <div class="form-body">
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $documentRequest->progress }}" aria-label="Request progress">
                <span style="width: {{ (int) $documentRequest->progress }}%"></span>
            </div>
            @if ($documentRequest->status === \App\Enums\RequestStatus::ReadyForPickup)
                <p class="muted">Your document is ready. Pick it up at the school, then confirm below.</p>
            @elseif ($documentRequest->status === \App\Enums\RequestStatus::Pending && $documentRequest->ai_checked_at === null)
                <p class="muted">Waiting for the automatic check. Refresh this page in a moment.</p>
            @endif
        </div>
    </section>

    <x-request-timeline :request="$documentRequest" />

    @if ($documentRequest->status === \App\Enums\RequestStatus::Pending && $documentRequest->ai_checked_at !== null && $documentRequest->ai_remarks)
        <div class="alert alert-warn" role="alert">
            <strong>Your request needs attention</strong>
            <p>{!! nl2br(e($documentRequest->ai_remarks)) !!}</p>
            @if ($canResubmit)
                <p>Correct your payment details below. For a problem with an uploaded document, cancel this request and submit a new one.</p>
            @else
                <p>Cancel this request and submit a new one with the corrected documents.</p>
            @endif
        </div>
    @endif

    <div class="dashboard-columns">
        <div class="stack">
            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">DETAILS</p><h2>Request details</h2></div></div>
                <dl class="detail-list">
                    <div><dt>Reference</dt><dd>{{ $documentRequest->reference }}</dd></div>
                    <div><dt>Submitted</dt><dd>{{ $documentRequest->created_at?->format('M j, Y g:i A') }}</dd></div>
                    <div><dt>Estimated completion</dt><dd>{{ $documentRequest->estimated_completion_date?->format('M j, Y') ?? '—' }}</dd></div>
                    <div><dt>Claiming method</dt><dd>{{ $documentRequest->claiming_method === \App\Enums\ClaimingMethod::Email ? 'Email (legacy request)' : 'Pick-up' }}</dd></div>
                    <div><dt>Purpose</dt><dd>{{ $documentRequest->purpose ?: '—' }}</dd></div>
                    <div><dt>My remarks</dt><dd>{{ $documentRequest->requester_remarks ?: '—' }}</dd></div>
                    @if ($documentRequest->completed_at)
                        <div><dt>Completed</dt><dd>{{ $documentRequest->completed_at->format('M j, Y g:i A') }}</dd></div>
                    @endif
                    @if ($documentRequest->status === \App\Enums\RequestStatus::Cancelled)
                        <div><dt>Cancelled</dt><dd>{{ $documentRequest->cancelled_at?->format('M j, Y g:i A') }}{{ $documentRequest->cancel_reason ? ' — '.$documentRequest->cancel_reason : '' }}</dd></div>
                    @endif
                </dl>
            </section>

            @if ($hasPayment)
                <section class="panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">PAYMENT</p><h2>Online payment</h2></div>
                        <span class="status-badge status-{{ \Illuminate\Support\Str::slug($documentRequest->payment_status->value) }}">{{ $documentRequest->payment_status->value }}</span>
                    </div>
                    <dl class="detail-list">
                        <div><dt>Method</dt><dd>{{ $documentRequest->payment_method ?: '—' }}</dd></div>
                        <div><dt>Reference number</dt><dd>{{ $documentRequest->payment_reference ?: '—' }}</dd></div>
                        <div><dt>Amount</dt><dd>{{ number_format((float) $documentRequest->payment_amount, 2) }}</dd></div>
                        <div><dt>Date paid</dt><dd>{{ $documentRequest->payment_paid_on?->format('M j, Y') ?? '—' }}</dd></div>
                        <div><dt>Verified</dt><dd>{{ $documentRequest->payment_verified_at?->format('M j, Y g:i A') ?? 'Not yet' }}</dd></div>
                        @if ($documentRequest->payment_proof_path)
                            <div><dt>Proof</dt><dd><a href="{{ route('requests.payment-proof', $documentRequest) }}">Download proof of payment</a></dd></div>
                        @endif
                    </dl>
                </section>
            @endif

            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">UPLOADS</p><h2>Supporting documents</h2></div></div>
                @if ($documentRequest->uploads->isEmpty())
                    <div class="empty-state compact"><strong>No uploads</strong><p>This request has no supporting documents on file.</p></div>
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
            @if ($canResubmit)
                <section class="panel form-panel">
                    <div class="panel-heading"><div><p class="eyebrow">ACTION NEEDED</p><h2>Resubmit payment details</h2></div></div>
                    <form class="form-body" method="POST" action="{{ route('student.requests.payment', $documentRequest) }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        <div class="field">
                            <label for="payment_method">Payment method</label>
                            <select id="payment_method" name="payment_method" required>
                                @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                    <option value="{{ $method->value }}" @selected(old('payment_method', $documentRequest->payment_method) === $method->value)>{{ $method->value }}</option>
                                @endforeach
                            </select>
                            @error('payment_method') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="payment_reference">Reference number</label>
                            <input id="payment_reference" name="payment_reference" type="text" maxlength="40" autocomplete="off" value="{{ old('payment_reference', $documentRequest->payment_reference) }}" required>
                            @error('payment_reference') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="payment_amount">Amount paid</label>
                                <input id="payment_amount" name="payment_amount" type="number" step="0.01" min="0.01" value="{{ old('payment_amount', $documentRequest->payment_amount) }}" required>
                                @error('payment_amount') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div class="field">
                                <label for="payment_paid_on">Date paid</label>
                                <input id="payment_paid_on" name="payment_paid_on" type="date" max="{{ now()->toDateString() }}" value="{{ old('payment_paid_on', $documentRequest->payment_paid_on?->toDateString()) }}" required>
                                @error('payment_paid_on') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="field">
                            <label for="payment_proof">New proof of payment</label>
                            <input id="payment_proof" name="payment_proof" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                            @error('payment_proof') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <button class="button" type="submit">Resubmit for checking</button>
                    </form>
                </section>
            @endif

            @if ($canReceive)
                <section class="panel form-panel">
                    <div class="panel-heading"><div><p class="eyebrow">PICK-UP</p><h2>Confirm receipt</h2></div></div>
                    <form class="form-body" method="POST" action="{{ route('student.requests.receive', $documentRequest) }}">
                        @csrf
                        <p class="muted">Only confirm after you have received the document.</p>
                        <button class="button" type="submit">I received my document</button>
                    </form>
                </section>
            @endif

            @if ($canArchive)
                <section class="panel form-panel">
                    <div class="panel-heading"><div><p class="eyebrow">DONE</p><h2>Archive this request</h2></div></div>
                    <form class="form-body" method="POST" action="{{ route('student.requests.archive', $documentRequest) }}">
                        @csrf
                        <p class="muted">Archiving keeps the record but lets you submit a new request.</p>
                        <button class="button" type="submit">Archive request</button>
                    </form>
                </section>
            @endif

            @if ($canCancel)
                <section class="panel form-panel">
                    <div class="panel-heading"><div><p class="eyebrow">CHANGED YOUR MIND?</p><h2>Cancel request</h2></div></div>
                    <form class="form-body" method="POST" action="{{ route('student.requests.cancel', $documentRequest) }}">
                        @csrf
                        <div class="field">
                            <label for="reason">Reason <span class="muted">(optional)</span></label>
                            <input id="reason" name="reason" type="text" maxlength="255" value="{{ old('reason') }}">
                            @error('reason') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <button class="button button-danger" type="submit">Cancel request</button>
                    </form>
                </section>
            @endif

            <section class="panel">
                <div class="panel-heading"><div><p class="eyebrow">HISTORY</p><h2>Activity</h2></div></div>
                @if ($documentRequest->aiLogs->isEmpty())
                    <div class="empty-state compact"><strong>No activity yet</strong><p>Updates appear here as your request is processed.</p></div>
                @else
                    <ul class="plain-list">
                        @foreach ($documentRequest->aiLogs as $log)
                            <li>
                                <span>
                                    <strong>{{ $logLabels[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)) }}</strong>
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
