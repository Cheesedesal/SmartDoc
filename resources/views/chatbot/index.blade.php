@extends('layouts.portal')

@section('title', 'SmartDoc assistant')
@section('heading', 'SmartDoc assistant')
@section('subtitle', 'Ask about document requests, requirements, fees, and procedures.')

@section('content')
    <div class="chatbot-main">
        <a class="text-link back-link" href="{{ route('dashboard') }}">← Back to dashboard</a>
        <section class="chat-panel" data-chatbot data-endpoint="{{ route('chatbot.ask') }}" data-clear-endpoint="{{ route('chatbot.history.clear') }}">
            <div class="chat-heading">
                <span class="assistant-avatar" aria-hidden="true">✦</span>
                <div><strong>SmartDoc assistant</strong><small>Ask naturally in English, Filipino, or Taglish.</small></div>
                <button class="chat-clear-button" type="button" data-chat-clear>Clear history</button>
            </div>
            <div class="chat-messages" data-chat-messages aria-live="polite" aria-relevant="additions">
                @forelse ($messages as $message)
                    <p class="chat-bubble {{ $message->role === 'user' ? 'student-message' : 'assistant-message' }}">{{ $message->content }}</p>
                @empty
                    <p class="chat-bubble assistant-message">Hello! I can help with SmartDoc document requests, requirements, fees, and procedures. What would you like to know?</p>
                @endforelse
            </div>
            <div class="chat-suggestions" data-chat-suggestions aria-label="Suggested questions">
                @forelse ($suggestions as $suggestion)
                    <button class="suggestion-button" type="button">{{ $suggestion }}</button>
                @empty
                    <p class="suggestions-empty">Suggested questions will appear here after FAQs are approved and activated.</p>
                @endforelse
            </div>
            <form class="chat-form" data-chat-form>
                <label class="visually-hidden" for="chat-message">Your question</label>
                <textarea id="chat-message" name="message" maxlength="1000" rows="2" placeholder="Type your question..." required></textarea>
                <button type="submit">Send</button>
            </form>
            <p class="chat-notice">Your chat history is saved to your SmartDoc account and remains available after refresh or sign-out. Messages and recent exchanges are sent to Google Gemini. Uploaded requirements submitted for optional AI review are also sent to Google Gemini; AI only checks apparent legibility/completeness and cannot confirm authenticity. Document fees and requirements come from active document types; fees are paid at pickup, not in SmartDoc. Other school-specific answers use active approved FAQs. Do not share passwords, verification codes, or private payment details. For personal request status, use My Requests or contact the school office.</p>
        </section>
    </div>
@endsection
