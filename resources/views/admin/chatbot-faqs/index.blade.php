@extends('layouts.portal')

@section('title', 'Manage FAQs')
@section('heading', 'Manage approved FAQs')
@section('subtitle', 'Only active, approved answers are available to students.')

@section('content')

        @if (session('status'))<div class="notice-success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif

        <section class="panel faq-recommendations">
            <div class="faq-admin-intro">
                <strong>AI-suggested FAQ topics</strong>
                <p>These are anonymized, unanswered SmartDoc topics asked at least 3 times in the last 30 days. Student messages and identities are not shown or stored. Recommendations are drafts only; verify the facts and write the official answer before approval.</p>
            </div>
            <div class="faq-table-wrap">
                <table class="faq-table">
                    <thead><tr><th>Suggested topic</th><th>Language</th><th>Recent inquiries</th><th>Last asked</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse ($recommendations as $recommendation)
                        <tr class="hover-row" tabindex="0">
                            <td><strong>{{ $recommendation->suggested_intent }}</strong><span>{{ $recommendation->suggested_question }}</span><span class="hover-card">{{ $recommendation->inquiry_count }} related inquiries in the last 30 days. Questions are anonymized; review the suggested topic before drafting an official answer.</span></td>
                            <td>{{ strtoupper($recommendation->locale) }}</td>
                            <td>{{ number_format($recommendation->inquiry_count) }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($recommendation->last_inquired_at)->diffForHumans() }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.chatbot-faqs.recommendations.create', $recommendation->suggested_intent) }}">
                                    @csrf
                                    <input type="hidden" name="locale" value="{{ $recommendation->locale }}">
                                    <button class="table-button" type="submit">Create FAQ draft</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">No unanswered topic has reached 3 inquiries in the last 30 days yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel faq-admin-panel">
            <div class="faq-admin-intro">
                <strong>Approval workflow</strong>
                <p>New FAQs start as drafts. Review the source and answer, approve it, then activate it to make it available in chat. Editing approved content disables it and requires approval again.</p>
            </div>
            <form method="GET" class="filter-bar"><select name="archived"><option value="0" @selected(! request()->boolean('archived'))>Current FAQs</option><option value="1" @selected(request()->boolean('archived'))>Archived FAQs</option></select><button class="secondary-button" type="submit">Show</button></form>
            <div class="table-toolbar">
                <a class="secondary-button" href="{{ route('admin.archive.index', ['type' => 'faqs']) }}">Archive</a>
                @unless(request()->boolean('archived'))
                    <a class="secondary-button" href="{{ route('admin.imports.show', 'faqs') }}">Bulk import</a>
                    <a class="primary-button" href="{{ route('admin.chatbot-faqs.create') }}">+ Add FAQ</a>
                @endunless
            </div>
            <form id="faqs-bulk-form" method="POST" action="{{ route(request()->boolean('archived') ? 'admin.chatbot-faqs.bulk-restore' : 'admin.chatbot-faqs.bulk-archive') }}">@csrf
                <div class="bulk-actions"><strong>{{ request()->boolean('archived') ? 'Restore selected FAQs' : 'Archive selected FAQs' }}</strong><button class="secondary-button" type="submit" data-confirm="Apply this action to all selected FAQs?">{{ request()->boolean('archived') ? 'Restore selected' : 'Archive selected' }}</button></div>
            </form>
            <div class="faq-table-wrap">
                <table class="faq-table">
                    <thead><tr><th><input type="checkbox" data-select-all="faqs-bulk-form" aria-label="Select all FAQs on this page"></th><th>Intent / question</th><th>Language</th><th>Status</th><th>Availability</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse ($faqs as $faq)
                        <tr class="hover-row" tabindex="0">
                            <td><input type="checkbox" name="ids[]" value="{{ $faq->id }}" form="faqs-bulk-form" aria-label="Select FAQ {{ $faq->intent }}"></td>
                            <td><strong>{{ $faq->intent }}</strong><span>{{ $faq->canonical_question }}</span><small>{{ $faq->category }}</small><span class="hover-card">{{ $faq->answer."\n\nExamples: ".implode(', ', $faq->question_examples ?? [])."\nCreated by: ".($faq->creator?->full_name ?? 'System')."\nApproved by: ".($faq->approver?->full_name ?? 'Not approved') }}</span></td>
                            <td>{{ strtoupper($faq->locale) }}</td>
                            <td><span class="faq-status">{{ str_replace('_', ' ', $faq->status->value) }}</span></td>
                            <td>{{ $faq->archived_at ? 'Archived' : ($faq->is_active ? 'Active' : 'Inactive') }}{{ $faq->is_suggested ? ' · Suggested' : '' }}</td>
                            <td class="faq-actions">
                                @if(request()->boolean('archived'))
                                    <form method="POST" action="{{ route('admin.chatbot-faqs.restore', $faq) }}">@csrf<button class="table-button" type="submit">Restore</button></form>
                                @else
                                <a class="text-link" href="{{ route('admin.chatbot-faqs.edit', $faq) }}">Edit</a>
                                @if ($faq->status !== \App\Enums\ChatbotFaqStatus::Approved)
                                    <form method="POST" action="{{ route('admin.chatbot-faqs.approve', $faq) }}">@csrf<button class="table-button" type="submit">Approve</button></form>
                                @endif
                                @if ($faq->status === \App\Enums\ChatbotFaqStatus::Approved)
                                    <form method="POST" action="{{ route('admin.chatbot-faqs.active', $faq) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $faq->is_active ? '0' : '1' }}">
                                        <button class="table-button" type="submit">{{ $faq->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.chatbot-faqs.archive', $faq) }}" data-confirm="Archive this FAQ and remove it from chatbot answers?">@csrf<button class="table-button" type="submit">Archive</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">No FAQs in this view. Add a draft after confirming the answer with the responsible school office.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="faq-pagination">{{ $faqs->links() }}</div>
        </section>
@endsection
