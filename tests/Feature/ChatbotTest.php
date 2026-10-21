<?php

namespace Tests\Feature;

use App\Enums\ChatbotFaqStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ChatbotFaq;
use App\Models\ChatbotInquiry;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_create_approve_and_activate_an_faq_for_suggestions(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');

        $this->actingAs($admin)
            ->post(route('admin.chatbot-faqs.store'), [
                'intent' => 'document_fee_tor',
                'category' => 'Fees',
                'canonical_question' => 'Magkano ang TOR?',
                'question_examples' => "How much is the TOR?\nMagkano po ang transcript?",
                'answer' => 'The approved TOR fee is [school-approved fee].',
                'locale' => 'fil',
                'is_suggested' => '1',
            ])
            ->assertRedirect(route('admin.chatbot-faqs.index'));

        $faq = ChatbotFaq::where('intent', 'document_fee_tor')->firstOrFail();
        $this->assertSame(ChatbotFaqStatus::Draft, $faq->status);
        $this->assertFalse($faq->is_active);

        $this->actingAs($admin)
            ->post(route('admin.chatbot-faqs.approve', $faq))
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.chatbot-faqs.active', $faq), ['is_active' => '1'])
            ->assertRedirect();

        $this->assertDatabaseHas('chatbot_faqs', [
            'id' => $faq->id,
            'status' => 'approved',
            'is_active' => true,
            'is_suggested' => true,
            'approved_by' => $admin->id,
        ]);

        $student = $this->createUser(Role::Student, 'student');
        $this->actingAs($student)
            ->get(route('chatbot.index'))
            ->assertOk()
            ->assertSee('Magkano ang TOR?');
    }

    public function test_students_cannot_manage_chatbot_faqs(): void
    {
        $student = $this->createUser(Role::Student, 'student');

        $this->actingAs($student)
            ->get(route('admin.chatbot-faqs.index'))
            ->assertForbidden();
    }

    public function test_greetings_receive_a_conversational_reply_without_creating_inquiry_records(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => '{"faq_id":"none","document_type_id":"none","knowledge_source":"none","response_type":"greeting","locale":"en","confidence":0.98,"is_smartdoc_related":false,"suggested_intent":"","suggested_question":"","answer":"Hello! How can I help with SmartDoc today?"}',
                    ]]],
                ]],
            ]),
        ]);

        $this->actingAs($student)
            ->postJson(route('chatbot.ask'), ['message' => 'hellow'])
            ->assertOk()
            ->assertJsonPath('answer', 'Hello! How can I help with SmartDoc today?');

        $this->assertDatabaseCount('chatbot_inquiries', 0);
    }

    public function test_chat_history_is_loaded_after_reauthentication_and_can_be_cleared(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        $anotherStudent = $this->createUser(Role::Student, 'another-student');
        $student->chatbotMessages()->createMany([
            ['role' => 'user', 'content' => 'What do I need for a transcript?'],
            ['role' => 'assistant', 'content' => 'Check the active transcript requirements.'],
        ]);
        $anotherStudent->chatbotMessages()->create([
            'role' => 'user',
            'content' => 'This is another student conversation.',
        ]);

        $this->actingAs($student)
            ->get(route('chatbot.index'))
            ->assertOk()
            ->assertSee('What do I need for a transcript?')
            ->assertSee('Check the active transcript requirements.')
            ->assertDontSee('This is another student conversation.');

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->actingAs($student)
            ->get(route('chatbot.index'))
            ->assertOk()
            ->assertSee('What do I need for a transcript?')
            ->assertSee('Check the active transcript requirements.');

        $this->deleteJson(route('chatbot.history.clear'))
            ->assertOk()
            ->assertJsonPath('message', 'Chat history cleared.');

        $this->assertDatabaseCount('chatbot_messages', 1);
        $this->assertDatabaseHas('chatbot_messages', [
            'user_id' => $anotherStudent->id,
            'content' => 'This is another student conversation.',
        ]);
    }

    public function test_active_document_types_are_sent_as_fee_and_requirement_sources(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        $documentType = DocumentType::create([
            'name' => 'Transcript of Records',
            'description' => 'Official transcript.',
            'processing_days' => 5,
            'fee' => '150.00',
            'is_active' => true,
        ]);
        DocumentRequirement::create([
            'document_type_id' => $documentType->id,
            'name' => 'Request form',
            'description' => 'Signed request form.',
            'is_required' => true,
        ]);

        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'faq_id' => 'none',
                            'document_type_id' => (string) $documentType->id,
                            'knowledge_source' => 'document_type',
                            'response_type' => 'document_type_answer',
                            'locale' => 'en',
                            'confidence' => 0.96,
                            'is_smartdoc_related' => true,
                            'suggested_intent' => '',
                            'suggested_question' => '',
                            'answer' => 'The TOR fee is PHP 150.00, payable at pickup. SmartDoc does not process payments.',
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        $this->actingAs($student)
            ->postJson(route('chatbot.ask'), ['message' => 'How much is the transcript?'])
            ->assertOk()
            ->assertJsonPath('answer', 'The TOR fee is PHP 150.00, payable at pickup. SmartDoc does not process payments.');

        Http::assertSent(fn ($request) => str_contains($request['systemInstruction']['parts'][0]['text'], 'Signed request form.')
            && str_contains($request['systemInstruction']['parts'][0]['text'], '"fee":"150.00"')
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'SmartDoc does not collect or process payments.'));
    }

    public function test_editing_an_approved_answer_deactivates_it_until_reapproved(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');
        $faq = ChatbotFaq::create([
            'intent' => 'document_fee_tor',
            'category' => 'Fees',
            'canonical_question' => 'Magkano ang TOR?',
            'question_examples' => [],
            'answer' => 'Old approved answer.',
            'locale' => 'fil',
            'status' => ChatbotFaqStatus::Approved,
            'is_active' => true,
            'is_suggested' => false,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.chatbot-faqs.update', $faq), [
                'intent' => $faq->intent,
                'category' => $faq->category,
                'canonical_question' => $faq->canonical_question,
                'question_examples' => '',
                'answer' => 'Updated answer awaiting approval.',
                'locale' => 'fil',
            ])
            ->assertRedirect(route('admin.chatbot-faqs.index'));

        $this->assertDatabaseHas('chatbot_faqs', [
            'id' => $faq->id,
            'answer' => 'Updated answer awaiting approval.',
            'status' => 'pending_approval',
            'is_active' => false,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function test_unapproved_faqs_are_not_used_as_chatbot_answers(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');
        $student = $this->createUser(Role::Student, 'student');

        $faq = ChatbotFaq::create([
            'intent' => 'document_fee_tor',
            'category' => 'Fees',
            'canonical_question' => 'Magkano ang TOR?',
            'question_examples' => [],
            'answer' => 'This draft must never be shown.',
            'locale' => 'fil',
            'status' => ChatbotFaqStatus::Draft,
            'is_active' => false,
            'is_suggested' => true,
            'created_by' => $admin->id,
        ]);

        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"faq_id":"none","document_type_id":"none","knowledge_source":"none","response_type":"out_of_scope","locale":"fil","confidence":0.9,"is_smartdoc_related":false,"suggested_intent":"","suggested_question":"","answer":"I can help with SmartDoc questions. What would you like to know?"}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($student)
            ->postJson(route('chatbot.ask'), ['message' => 'Magkano ang TOR?'])
            ->assertOk()
            ->assertJsonMissing(['answer' => 'This draft must never be shown.'])
            ->assertJsonPath('suggestions', []);

        Http::assertSent(fn ($request) => ! str_contains($request->body(), 'This draft must never be shown.'));
    }

    public function test_unanswered_smartdoc_questions_are_logged_without_student_messages_or_identity(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"faq_id":"none","document_type_id":"none","knowledge_source":"none","response_type":"unanswered_smartdoc","locale":"fil","confidence":0.91,"is_smartdoc_related":true,"suggested_intent":"document_request_pickup","suggested_question":"Saan at kailan ko makukuha ang dokumento ko?","answer":"Wala pa akong aprubadong sagot tungkol dito. Maaari mong tingnan ang My Requests o makipag-ugnayan sa opisina."}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($student)
            ->postJson(route('chatbot.ask'), ['message' => 'Saan ko kukunin yung dokumento pag tapos na?'])
            ->assertOk()
            ->assertJsonPath('answer', 'Wala pa akong aprubadong sagot tungkol dito. Maaari mong tingnan ang My Requests o makipag-ugnayan sa opisina.');

        $this->assertDatabaseHas('chatbot_inquiries', [
            'matched_intent' => null,
            'suggested_intent' => 'document_request_pickup',
            'suggested_question' => 'Saan at kailan ko makukuha ang dokumento ko?',
            'locale' => 'fil',
            'outcome' => 'unanswered',
        ]);
        $inquiry = ChatbotInquiry::firstOrFail();
        $this->assertArrayNotHasKey('message', $inquiry->getAttributes());
        $this->assertArrayNotHasKey('student_id', $inquiry->getAttributes());
        $this->assertDatabaseHas('chatbot_messages', [
            'user_id' => $student->id,
            'role' => 'user',
            'content' => 'Saan ko kukunin yung dokumento pag tapos na?',
        ]);
    }

    public function test_admin_sees_repeated_anonymous_topics_and_can_create_a_draft(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');
        $student = $this->createUser(Role::Student, 'student');

        foreach (range(1, 3) as $number) {
            ChatbotInquiry::create([
                'suggested_intent' => 'document_request_pickup',
                'suggested_question' => 'Saan at kailan ko makukuha ang dokumento ko?',
                'locale' => 'fil',
                'confidence' => 0.91,
                'outcome' => 'unanswered',
            ]);
        }

        $this->actingAs($student)
            ->get(route('admin.chatbot-faqs.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.chatbot-faqs.index'))
            ->assertOk()
            ->assertSee('document_request_pickup')
            ->assertSee('Saan at kailan ko makukuha ang dokumento ko?')
            ->assertSee('3');

        $this->actingAs($admin)
            ->post(route('admin.chatbot-faqs.recommendations.create', 'document_request_pickup'), [
                'locale' => 'fil',
            ])
            ->assertRedirect();

        $faq = ChatbotFaq::where('intent', 'document_request_pickup')->firstOrFail();
        $this->assertSame(ChatbotFaqStatus::Draft, $faq->status);
        $this->assertFalse($faq->is_active);
        $this->assertSame($admin->id, $faq->created_by);
        $this->assertStringContainsString('Add an answer confirmed', $faq->answer);
    }

    public function test_chatbot_returns_a_natural_answer_grounded_in_the_approved_faq(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');
        $student = $this->createUser(Role::Student, 'student');
        $answer = 'The approved TOR fee is [school-approved fee].';

        $faq = ChatbotFaq::create([
            'intent' => 'document_fee_tor',
            'category' => 'Fees',
            'canonical_question' => 'Magkano ang TOR?',
            'question_examples' => ['How much is the TOR?'],
            'answer' => $answer,
            'locale' => 'fil',
            'status' => ChatbotFaqStatus::Approved,
            'is_active' => true,
            'is_suggested' => false,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        Config::set('services.gemini.api_key', 'test-api-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'faq_id' => (string) $faq->id,
                                'document_type_id' => 'none',
                                'knowledge_source' => 'faq',
                                'response_type' => 'faq_answer',
                                'locale' => 'fil',
                                'confidence' => 0.94,
                                'is_smartdoc_related' => true,
                                'suggested_intent' => '',
                                'suggested_question' => '',
                                'answer' => 'Ang aprubadong bayad para sa TOR ay [school-approved fee].',
                            ]),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($student)
            ->postJson(route('chatbot.ask'), ['message' => 'Hi po, magkano po yung TOR?'])
            ->assertOk()
            ->assertExactJson([
                'answer' => 'Ang aprubadong bayad para sa TOR ay [school-approved fee].',
                'suggestions' => ['Magkano ang TOR?'],
            ]);

        Http::assertSent(fn ($request) => str_contains($request->body(), 'document_fee_tor')
            && str_contains($request['systemInstruction']['parts'][0]['text'], $answer)
            && str_contains($request->body(), 'Hi po, magkano po yung TOR?'));

        $this->assertDatabaseHas('chatbot_messages', [
            'user_id' => $student->id,
            'role' => 'assistant',
            'content' => 'Ang aprubadong bayad para sa TOR ay [school-approved fee].',
        ]);
    }

    private function createUser(Role $role, string $schoolId): User
    {
        return User::factory()->create([
            'school_id' => $schoolId,
            'firstname' => ucfirst($schoolId),
            'surname' => 'Test',
            'role' => $role,
            'status' => UserStatus::Activated,
        ]);
    }
}
