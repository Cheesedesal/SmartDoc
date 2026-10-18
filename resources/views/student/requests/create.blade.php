@extends('layouts.app')

@section('title', 'New request')

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">NEW REQUEST</p>
            <h1>Request a document</h1>
            <p class="page-subtitle">Choose a document, upload what is required, and pay online. Everything is checked automatically.</p>
        </div>
        <a class="button button-ghost" href="{{ route('student.requests.index') }}">Back to my requests</a>
    </header>

    @if ($blocking !== null)
        <section class="panel">
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true">!</span>
                <strong>You already have an active request</strong>
                <p>
                    {{ $blocking->reference }} ({{ $blocking->documentType?->name }}) is {{ $blocking->status->value }}.
                    You can submit a new request once it is completed and archived.
                </p>
                <p><a class="button" href="{{ route('student.requests.show', $blocking) }}">View {{ $blocking->reference }}</a></p>
            </div>
        </section>
    @else
        <section class="panel form-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">STEP 1</p>
                    <h2>Choose a document</h2>
                </div>
            </div>
            <form class="form-body" method="GET" action="{{ route('student.requests.create') }}">
                <div class="field">
                    <label for="type">Document type</label>
                    <select id="type" name="type" required>
                        <option value="">Select a document…</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected($selected && $selected->id === $type->id)>
                                {{ $type->name }} — {{ (float) $type->fee > 0 ? number_format((float) $type->fee, 2) : 'Free' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button class="button" type="submit">Continue</button>
            </form>
        </section>

        @if ($selected)
            <section class="panel form-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">STEP 2</p>
                        <h2>{{ $selected->name }}</h2>
                    </div>
                    <span class="count-pill">{{ $selected->processing_days }} day(s) processing</span>
                </div>

                <form class="form-body" method="POST" action="{{ route('student.requests.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="document_type_id" value="{{ $selected->id }}">

                    <p class="muted">{{ $selected->description }}</p>

                    @if ($errors->any() && ! $errors->has('request'))
                        <div class="alert alert-error" role="alert">Please fix the highlighted fields below.</div>
                    @endif

                    <div class="field">
                        <label for="purpose">Purpose <span class="muted">(optional)</span></label>
                        <input id="purpose" name="purpose" type="text" maxlength="255" value="{{ old('purpose') }}">
                        @error('purpose') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label for="requester_remarks">Remarks <span class="muted">(optional)</span></label>
                        <textarea id="requester_remarks" name="requester_remarks" rows="3" maxlength="1000">{{ old('requester_remarks') }}</textarea>
                        @error('requester_remarks') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <p class="muted">Claiming method: <strong>Pick-up</strong> at the school (the only available option).</p>

                    @if ($selected->requirements->isNotEmpty())
                        <fieldset class="field-group">
                            <legend>Supporting documents</legend>
                            <p class="muted">PDF, JPG or PNG, up to {{ number_format(config('smartdoc.uploads.max_kb') / 1024, 0) }} MB each.</p>
                            @foreach ($selected->requirements as $requirement)
                                <div class="field">
                                    <label for="req-{{ $requirement->id }}">
                                        {{ $requirement->name }}
                                        <span class="muted">{{ $requirement->is_required ? '(required)' : '(optional)' }}</span>
                                    </label>
                                    @if ($requirement->description)
                                        <small class="muted">{{ $requirement->description }}</small>
                                    @endif
                                    <input id="req-{{ $requirement->id }}" name="requirements[{{ $requirement->id }}]" type="file" accept=".pdf,.jpg,.jpeg,.png" @required($requirement->is_required)>
                                    @error('requirements.'.$requirement->id) <p class="field-error" role="alert">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </fieldset>
                    @endif

                    @if ((float) $selected->fee > 0)
                        <fieldset class="field-group">
                            <legend>Online payment</legend>
                            <p class="muted">
                                Fee: <strong>{{ number_format((float) $selected->fee, 2) }}</strong>.
                                Pay through your chosen channel, then enter the details and attach a screenshot or receipt.
                            </p>

                            <div class="field">
                                <label for="payment_method">Payment method</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="">Select…</option>
                                    @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                        <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>{{ $method->value }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                            </div>

                            <div class="field">
                                <label for="payment_reference">Reference number</label>
                                <input id="payment_reference" name="payment_reference" type="text" maxlength="40" autocomplete="off" value="{{ old('payment_reference') }}" required>
                                @error('payment_reference') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                            </div>

                            <div class="field-row">
                                <div class="field">
                                    <label for="payment_amount">Amount paid</label>
                                    <input id="payment_amount" name="payment_amount" type="number" step="0.01" min="0.01" value="{{ old('payment_amount', $selected->fee) }}" required>
                                    @error('payment_amount') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div class="field">
                                    <label for="payment_paid_on">Date paid</label>
                                    <input id="payment_paid_on" name="payment_paid_on" type="date" max="{{ now()->toDateString() }}" value="{{ old('payment_paid_on') }}" required>
                                    @error('payment_paid_on') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="field">
                                <label for="payment_proof">Proof of payment</label>
                                <input id="payment_proof" name="payment_proof" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                                @error('payment_proof') <p class="field-error" role="alert">{{ $message }}</p> @enderror
                            </div>
                        </fieldset>
                    @endif

                    <button class="button" type="submit">Submit request</button>
                </form>
            </section>
        @endif
    @endif
@endsection
