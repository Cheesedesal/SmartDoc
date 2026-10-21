<?php

namespace App\Http\Controllers;

use App\Models\ChatbotFaq;
use App\Models\ChatbotInquiry;
use App\Models\ChatbotMessage;
use App\Models\DocumentType;
use App\Services\GeminiChatAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;

class ChatbotController extends Controller
{
    private const FALLBACK = 'I can help with SmartDoc document requests, requirements, fees, and procedures. '
        .'What would you like to know?';

    public function index(): View
    {
        $suggestions = $this->suggestions();
        $messages = auth()->user()->chatbotMessages()
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'created_at']);

        return view('chatbot.index', compact('messages', 'suggestions'));
    }

    public function ask(Request $request, GeminiChatAssistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $availableFaqs = ChatbotFaq::available()
            ->orderBy('intent')
            ->get(['id', 'intent', 'category', 'canonical_question', 'answer', 'locale']);
        $faqContext = $availableFaqs->map(static fn (ChatbotFaq $faq) => [
            'id' => (int) $faq->id,
            'intent' => (string) $faq->intent,
            'category' => (string) $faq->category,
            'question' => (string) $faq->canonical_question,
            'answer' => (string) $faq->answer,
            'locale' => (string) $faq->locale,
        ])->all();
        $documentTypes = DocumentType::active()
            ->with(['requirements' => fn ($query) => $query->unarchived()->orderBy('name')])
            ->get(['id', 'name', 'description', 'processing_days', 'fee'])
            ->map(static fn (DocumentType $type) => [
                'id' => (int) $type->id,
                'name' => (string) $type->name,
                'description' => (string) $type->description,
                'processing_days' => (int) $type->processing_days,
                'fee' => (string) $type->fee,
                'requirements' => $type->requirements->map(static fn ($requirement) => [
                    'name' => (string) $requirement->name,
                    'description' => (string) $requirement->description,
                    'is_required' => (bool) $requirement->is_required,
                ])->all(),
            ])->all();
        $conversationHistory = auth()->user()->chatbotMessages()
            ->orderByDesc('id')
            ->limit(8)
            ->get(['role', 'content'])
            ->reverse()
            ->map(static fn (ChatbotMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();

        try {
            $result = $assistant->respond(
                $validated['message'],
                $faqContext,
                $documentTypes,
                $conversationHistory
            );
        } catch (RuntimeException $exception) {
            Log::warning('SmartDoc chatbot is unavailable.', [
                'exception' => $exception::class,
            ]);

            $this->saveExchange(
                $validated['message'],
                'The SmartDoc assistant is temporarily unavailable. Please try again later.'
            );

            return response()->json([
                'error' => 'The SmartDoc assistant is temporarily unavailable. Please contact the school office for help.',
            ], 503);
        }

        if ($result === null) {
            $this->saveExchange($validated['message'], self::FALLBACK);

            return response()->json([
                'answer' => self::FALLBACK,
                'suggestions' => $this->suggestions(),
            ]);
        }

        $faq = $result['knowledge_source'] !== 'faq'
            ? null
            : $availableFaqs->firstWhere('id', (int) $result['faq_id']);
        $documentType = $result['knowledge_source'] !== 'document_type'
            ? null
            : collect($documentTypes)->firstWhere('id', (int) $result['document_type_id']);
        if (($result['knowledge_source'] === 'faq' && ! $faq)
            || ($result['knowledge_source'] === 'document_type' && ! $documentType)) {
            Log::warning('Selected chatbot knowledge record was no longer available.', [
                'faq_id' => $result['faq_id'],
                'document_type_id' => $result['document_type_id'],
            ]);

            $this->saveExchange($validated['message'], self::FALLBACK);

            return response()->json([
                'answer' => self::FALLBACK,
                'suggestions' => $this->suggestions(),
            ]);
        }

        $matchedIntent = $faq?->intent
            ?? ($documentType ? 'document_type_'.$documentType['id'] : null);
        $isUnansweredSmartDoc = $faq === null
            && $result['response_type'] === 'unanswered_smartdoc'
            && $result['is_smartdoc_related']
            && $result['confidence'] >= 0.75
            && $result['suggested_intent'] !== '';
        $suggestedIntent = $isUnansweredSmartDoc ? $result['suggested_intent'] : null;

        DB::transaction(function () use ($result, $matchedIntent, $suggestedIntent, $validated) {
            if ($result['is_smartdoc_related']) {
                ChatbotInquiry::create([
                    'matched_intent' => $matchedIntent,
                    'suggested_intent' => $suggestedIntent,
                    'suggested_question' => $suggestedIntent ? $result['suggested_question'] : null,
                    'locale' => $result['locale'] !== '' ? $result['locale'] : null,
                    'confidence' => $result['confidence'],
                    'outcome' => $matchedIntent ? 'matched' : ($suggestedIntent ? 'unanswered' : 'fallback'),
                ]);
            }

            $this->saveExchange($validated['message'], $result['answer']);
        });

        return response()->json([
            'answer' => $result['answer'],
            'suggestions' => $this->suggestions(),
        ]);
    }

    public function clearHistory(): JsonResponse
    {
        auth()->user()->chatbotMessages()->delete();

        return response()->json(['message' => 'Chat history cleared.']);
    }

    private function saveExchange(string $message, string $answer): void
    {
        DB::transaction(function () use ($message, $answer) {
            auth()->user()->chatbotMessages()->create([
                'role' => ChatbotMessage::ROLE_USER,
                'content' => $message,
            ]);
            auth()->user()->chatbotMessages()->create([
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'content' => $answer,
            ]);
        });
    }

    private function suggestions(): array
    {
        return ChatbotFaq::available()
            ->orderByDesc('is_suggested')
            ->orderBy('category')
            ->orderBy('canonical_question')
            ->limit(8)
            ->get(['canonical_question'])
            ->pluck('canonical_question')
            ->all();
    }
}
