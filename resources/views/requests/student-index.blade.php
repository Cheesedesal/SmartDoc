@extends('layouts.portal')

@section('title', 'My document requests')
@section('heading', 'My document requests')
@section('subtitle', 'Submit a request, follow its progress, and review your past requests.')

@section('content')
    <section class="panel module-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">NEW REQUEST</p><h2>Request a document</h2></div>
        </div>
        <form method="POST" action="{{ route('requests.store') }}" enctype="multipart/form-data" class="module-form">
            @csrf
            <div class="form-grid">
                <label>Document type
                    <select name="document_type_id" id="document-type-select" required>
                        <option value="">Select a document</option>
                        @foreach ($documentTypes as $documentType)
                        <option value="{{ $documentType->id }}" @selected(old('document_type_id') == $documentType->id)>
                            {{ $documentType->name }} — Fee ₱{{ number_format((float) $documentType->fee, 2) }} (pay at pickup)
                        </option>
                        @endforeach
                    </select>
                </label>
                <input type="hidden" name="claiming_method" value="Pick-up">
                <label class="form-span">Purpose
                    <textarea name="purpose" rows="2" maxlength="1000" required>{{ old('purpose') }}</textarea>
                </label>
                <label class="form-span">Remarks (optional)
                    <textarea name="requester_remarks" rows="2" maxlength="2000">{{ old('requester_remarks') }}</textarea>
                </label>
            </div>
            <p class="muted-copy pickup-payment-note">All completed documents must be collected in person from the Registrar office. Any applicable fee is paid at pickup; SmartDoc does not accept or process payments.</p>
            <div class="requirements-picker">
                <h3>Required documents</h3>
                @forelse ($documentTypes as $documentType)
                    <div class="requirement-group" data-requirements-for="{{ $documentType->id }}" hidden>
                        <p class="muted-copy">{{ $documentType->description }} Processing estimate: {{ $documentType->processing_days }} business days.</p>
                        @forelse ($documentType->requirements as $requirement)
                            <label class="upload-field">
                                <span><strong>{{ $requirement->name }}</strong> <small>{{ $requirement->is_required ? 'Required' : 'Optional' }}</small><small>{{ $requirement->description }}</small></span>
                                <input type="file" name="requirements[{{ $requirement->id }}]" accept=".pdf,.jpg,.jpeg,.png" @required($requirement->is_required)>
                            </label>
                        @empty
                            <p class="empty-inline">No files are required for this document type.</p>
                        @endforelse
                    </div>
                @empty
                    <p class="empty-inline">There are no active document types available yet. Contact the school office.</p>
                @endforelse
                <small class="muted-copy">Accepted formats: PDF, JPG, JPEG, PNG. Maximum file size: 5 MB each.</small>
            </div>
            <button class="primary-button" type="submit" @disabled($documentTypes->isEmpty())>Submit request</button>
        </form>
    </section>

    <section class="panel module-panel" id="request-history">
        <div class="panel-heading"><div><p class="eyebrow">TRACKING & HISTORY</p><h2>All my requests</h2></div><span class="count-pill">{{ $requests->total() }} total</span></div>
        @if ($requests->isEmpty())
            <div class="empty-state compact"><strong>No requests yet</strong><p>Your submitted requests and their status updates will appear here.</p></div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Reference</th><th>Document</th><th>Submitted</th><th>Status</th><th>Stage</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach ($requests as $item)
                            <tr class="hover-row" tabindex="0">
                                <td><strong>{{ $item->reference }}</strong><span class="hover-card">{{ implode("\n", array_filter([
                                    'Purpose: '.($item->purpose ?: 'Not provided'),
                                    'Remarks: '.($item->requester_remarks ?: 'None'),
                                    'Document: '.$item->documentType->name,
                                    'Fee at pickup: ₱'.number_format((float) $item->documentType->fee, 2),
                                    'Processing time: '.$item->documentType->processing_days.' business days',
                                    'Status: '.$item->status->value,
                                    'Stage: '.$item->current_stage->value,
                                    'Collection: Pick-up',
                                ])) }}</span></td>
                                <td>{{ $item->documentType->name }}</td>
                                <td>{{ $item->created_at->format('M j, Y') }}</td>
                                <td><span class="status-badge {{ 'status-'.\Illuminate\Support\Str::slug($item->status->value) }}">{{ $item->status->value }}</span></td>
                                <td>{{ $item->current_stage->value }}</td>
                                <td><a class="text-action" href="{{ route('requests.show', $item) }}">Track details</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $requests->links() }}</div>
        @endif
    </section>
@endsection

@push('scripts')
<script>
    const documentSelect = document.getElementById('document-type-select');
    const requirementGroups = document.querySelectorAll('[data-requirements-for]');
    const toggleRequirements = () => requirementGroups.forEach((group) => {
        const selected = group.dataset.requirementsFor === documentSelect.value;
        group.hidden = !selected;
        group.querySelectorAll('input[type="file"]').forEach((input) => {
            input.disabled = !selected;
        });
    });
    documentSelect.addEventListener('change', toggleRequirements);
    toggleRequirements();
</script>
@endpush
