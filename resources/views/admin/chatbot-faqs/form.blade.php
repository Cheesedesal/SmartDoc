@extends('layouts.portal')

@section('title', ($faq->exists ? 'Edit' : 'Add').' chatbot FAQ')
@section('heading', $faq->exists ? 'Edit FAQ' : 'Add FAQ draft')
@section('subtitle', 'Use only information verified by the responsible school office.')

@section('content')
        <a class="text-link back-link" href="{{ route('admin.chatbot-faqs.index') }}">← Back to FAQs</a>
        @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif
        <form class="faq-form panel" method="POST" action="{{ $faq->exists ? route('admin.chatbot-faqs.update', $faq) : route('admin.chatbot-faqs.store') }}">
            @csrf
            @if ($faq->exists) @method('PUT') @endif
            <div class="faq-form-grid">
                <label>Intent ID<input name="intent" value="{{ old('intent', $faq->intent) }}" placeholder="document_fee_tor" required maxlength="80"><small>Lowercase letters, numbers, and underscores; shared by language variants.</small></label>
                <label>Category<input name="category" value="{{ old('category', $faq->category) }}" placeholder="Fees" required maxlength="100"></label>
                <label>Answer language<select name="locale" required><option value="fil" @selected(old('locale', $faq->locale ?: 'fil') === 'fil')>Filipino / Taglish</option><option value="en" @selected(old('locale', $faq->locale) === 'en')>English</option></select></label>
                <label class="faq-form-wide">Canonical question<input name="canonical_question" value="{{ old('canonical_question', $faq->canonical_question) }}" placeholder="Magkano ang bayad para sa TOR?" required maxlength="255"></label>
                <label class="faq-form-wide">Alternative question examples<textarea name="question_examples" rows="4" placeholder="One example per line">{{ old('question_examples', implode("\n", $faq->question_examples ?? [])) }}</textarea><small>Include equivalent Filipino, English, Taglish, typo, or long phrasings. Do not include private student details.</small></label>
                <label class="faq-form-wide">Prepared answer<textarea name="answer" rows="6" required maxlength="5000" placeholder="Enter only an answer verified by the responsible office.">{{ old('answer', $faq->answer) }}</textarea><small>Do not guess fees, requirements, processing times, payment status, or official procedures.</small></label>
                <label class="suggested-checkbox"><input type="checkbox" name="is_suggested" value="1" @checked(old('is_suggested', $faq->is_suggested))> Show this question as a suggested prompt</label>
            </div>
            <div class="faq-form-actions"><button class="primary-link" type="submit">{{ $faq->exists ? 'Save changes' : 'Save as draft' }}</button><a class="text-link" href="{{ route('admin.chatbot-faqs.index') }}">Cancel</a></div>
        </form>
@endsection
