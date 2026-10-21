<?php

namespace Tests\Unit;

use App\Services\GeminiChatAssistant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class GeminiChatAssistantTest extends TestCase
{
    public function test_it_returns_a_conversational_response_and_uses_faq_content_as_grounding(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"faq_id":"12","document_type_id":"none","knowledge_source":"faq","response_type":"faq_answer","locale":"fil","confidence":0.94,"is_smartdoc_related":true,"suggested_intent":"","suggested_question":"","answer":"Ang TOR ay nagkakahalaga ng [approved fee]."}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $result = app(GeminiChatAssistant::class)->respond(
            'Magkano yung transcript?',
            [[
                'id' => 12,
                'intent' => 'document_fee_tor',
                'category' => 'Fees',
                'question' => 'Magkano ang TOR?',
                'answer' => 'The approved TOR fee is [approved fee].',
                'locale' => 'fil',
            ]],
            [],
            [['role' => 'user', 'content' => 'Hello!']]
        );

        $this->assertSame([
            'faq_id' => '12',
            'document_type_id' => 'none',
            'knowledge_source' => 'faq',
            'response_type' => 'faq_answer',
            'locale' => 'fil',
            'confidence' => 0.94,
            'is_smartdoc_related' => true,
            'suggested_intent' => '',
            'suggested_question' => '',
            'answer' => 'Ang TOR ay nagkakahalaga ng [approved fee].',
        ], $result);

        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-api-key')
            && $request['generationConfig']['responseMimeType'] === 'application/json'
            && $request['generationConfig']['temperature'] === 0.3
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'The approved TOR fee is [approved fee].')
            && $request['contents'][0]['role'] === 'user'
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Hello!')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Latest message: Magkano yung transcript?'));
    }

    public function test_it_accepts_friendly_greetings_without_faqs_or_recommendations(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"faq_id":"none","document_type_id":"none","knowledge_source":"none","response_type":"greeting","locale":"en","confidence":0.98,"is_smartdoc_related":false,"suggested_intent":"","suggested_question":"","answer":"Hello! How can I help you with SmartDoc today?"}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $result = app(GeminiChatAssistant::class)->respond('hellow', []);

        $this->assertSame('Hello! How can I help you with SmartDoc today?', $result['answer']);
        $this->assertSame('greeting', $result['response_type']);
    }

    public function test_it_uses_document_type_fees_and_requirements_as_a_distinct_knowledge_source(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => '{"faq_id":"none","document_type_id":"7","knowledge_source":"document_type","response_type":"document_type_answer","locale":"en","confidence":0.96,"is_smartdoc_related":true,"suggested_intent":"","suggested_question":"","answer":"The TOR fee is PHP 150.00, payable when you pick it up. SmartDoc does not process payments."}',
                    ]]],
                ]],
            ]),
        ]);

        $result = app(GeminiChatAssistant::class)->respond('How much is the TOR?', [], [[
            'id' => 7,
            'name' => 'Transcript of Records',
            'description' => 'Official transcript.',
            'processing_days' => 5,
            'fee' => '150.00',
            'requirements' => [[
                'name' => 'Request form',
                'description' => 'Completed and signed form.',
                'is_required' => true,
            ]],
        ]]);

        $this->assertSame('document_type', $result['knowledge_source']);
        $this->assertSame('7', $result['document_type_id']);
        $this->assertSame('The TOR fee is PHP 150.00, payable when you pick it up. SmartDoc does not process payments.', $result['answer']);
        Http::assertSent(fn ($request) => str_contains($request['systemInstruction']['parts'][0]['text'], 'Completed and signed form.')
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'The fee is paid at document pickup.')
            && $request['generationConfig']['responseSchema']['properties']['document_type_id']['enum'] === ['7', 'none']);
    }

    public function test_it_retries_a_transient_provider_error_once(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'Temporary overload.']], 503)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => '{"faq_id":"none","document_type_id":"none","knowledge_source":"none","response_type":"greeting","locale":"en","confidence":0.98,"is_smartdoc_related":false,"suggested_intent":"","suggested_question":"","answer":"Hello! How can I help?"}',
                        ]]],
                    ]],
                ]),
        ]);

        $result = app(GeminiChatAssistant::class)->respond('hello', []);

        $this->assertSame('Hello! How can I help?', $result['answer']);
        Http::assertSentCount(2);
    }

    public function test_it_rejects_unavailable_faq_ids_and_invalid_confidence(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => '{"faq_id":"99","document_type_id":"none","knowledge_source":"faq","response_type":"faq_answer","locale":"en","confidence":0.99,"is_smartdoc_related":true,"suggested_intent":"","suggested_question":"","answer":"Invented answer."}',
                        ]]],
                    ]],
                ])
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => '{"faq_id":"12","document_type_id":"none","knowledge_source":"faq","response_type":"faq_answer","locale":"en","confidence":1.4,"is_smartdoc_related":true,"suggested_intent":"","suggested_question":"","answer":"Answer."}',
                        ]]],
                    ]],
                ]),
        ]);

        $assistant = app(GeminiChatAssistant::class);
        $faqs = [[
            'id' => 12,
            'intent' => 'document_fee_tor',
            'category' => 'Fees',
            'question' => 'How much is the TOR?',
            'answer' => 'Approved fee.',
            'locale' => 'en',
        ]];

        $this->assertNull($assistant->respond('How much?', $faqs));
        $this->assertNull($assistant->respond('How much?', $faqs));
    }

    public function test_it_discards_personal_information_from_recommendation_metadata(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => '{"faq_id":"none","document_type_id":"none","knowledge_source":"none","response_type":"unanswered_smartdoc","locale":"fil","confidence":0.91,"is_smartdoc_related":true,"suggested_intent":"document_request_pickup","suggested_question":"Saan kukunin ni Juan student ID 12345678 ang request?","answer":"Pakitingnan ang My Requests page."}',
                    ]]],
                ]],
            ]),
        ]);

        $result = app(GeminiChatAssistant::class)->respond('Saan ang request ko?', []);

        $this->assertSame('', $result['suggested_question']);
        $this->assertSame('', $result['suggested_intent']);
        $this->assertSame('Pakitingnan ang My Requests page.', $result['answer']);
    }

    public function test_provider_errors_log_model_and_safe_error_message(): void
    {
        Config::set('services.gemini.api_key', 'test-api-key');
        Config::set('services.gemini.model', 'gemini-3.8-flash');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'The model is temporarily unavailable.']], 503)
                ->push(['error' => ['message' => 'The model is temporarily unavailable.']], 503),
        ]);
        Log::shouldReceive('warning')
            ->once()
            ->with('Gemini chatbot request failed.', [
                'status' => 503,
                'model' => 'gemini-3.8-flash',
                'provider_message' => 'The model is temporarily unavailable.',
            ]);

        $this->expectException(RuntimeException::class);

        app(GeminiChatAssistant::class)->respond('status of document request', []);
    }
}
