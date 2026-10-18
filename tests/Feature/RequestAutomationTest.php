<?php

namespace Tests\Feature;

use App\Actions\SubmitDocumentRequest;
use App\Enums\PaymentStatus;
use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\RequestNotAllowedException;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\RequestAiLog;
use App\Models\User;
use App\Services\RequestAutomation;
use App\Services\RequestLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'smartdoc.uploads.disk' => 'local',
            'smartdoc.automation.min_stage_seconds' => 60,
        ]);
    }

    public function test_a_student_cannot_hold_two_blocking_requests(): void
    {
        $student = $this->student('s1');
        $type = $this->documentType(fee: 0);

        $this->submit($student, $type);

        $this->expectException(RequestNotAllowedException::class);
        $this->submit($student, $type);
    }

    public function test_cancelling_a_pending_request_frees_the_slot(): void
    {
        $student = $this->student('s1');
        $type = $this->documentType(fee: 0);

        $request = $this->submit($student, $type);
        app(RequestLifecycle::class)->cancel($request, $student, 'changed my mind');

        $this->assertSame(RequestStatus::Cancelled, $request->fresh()->status);
        $this->assertNotNull($this->submit($student, $type));
    }

    public function test_free_request_is_verified_and_moves_in_progress(): void
    {
        $student = $this->student('s1');
        $request = $this->submit($student, $this->documentType(fee: 0));

        app(RequestAutomation::class)->run();

        $request->refresh();
        $this->assertSame(RequestStatus::InProgress, $request->status);
        $this->assertSame(RequestStage::ForVerification, $request->current_stage);
        $this->assertSame(PaymentStatus::NotRequired, $request->payment_status);
        $this->assertSame(100, $request->ai_confidence);
        $this->assertSame(1, RequestAiLog::where('document_request_id', $request->id)->where('action', 'verified')->count());
    }

    public function test_wrong_payment_amount_holds_the_request_as_pending(): void
    {
        $student = $this->student('s1');
        $request = $this->submit($student, $this->documentType(fee: 100), payment: ['payment_amount' => 50]);

        app(RequestAutomation::class)->run();

        $request->refresh();
        $this->assertSame(RequestStatus::Pending, $request->status);
        $this->assertSame(PaymentStatus::Flagged, $request->payment_status);
        $this->assertStringContainsString('amount', (string) $request->ai_remarks);
        $this->assertNotNull($request->ai_checked_at);

        // A second pass must not re-evaluate or re-log a held request.
        app(RequestAutomation::class)->run();
        $this->assertSame(1, RequestAiLog::where('document_request_id', $request->id)->count());
    }

    public function test_correct_paid_request_is_marked_paid(): void
    {
        $student = $this->student('s1');
        $request = $this->submit($student, $this->documentType(fee: 100));

        app(RequestAutomation::class)->run();

        $request->refresh();
        $this->assertSame(RequestStatus::InProgress, $request->status);
        $this->assertSame(PaymentStatus::Paid, $request->payment_status);
        $this->assertNotNull($request->payment_verified_at);
    }

    public function test_a_payment_reference_cannot_be_reused(): void
    {
        $type = $this->documentType(fee: 100);
        $this->submit($this->student('s1'), $type);

        $this->expectException(RequestNotAllowedException::class);
        $this->submit($this->student('s2'), $type);
    }

    public function test_request_progresses_to_pickup_then_completion_and_archive_frees_the_slot(): void
    {
        $student = $this->student('s1');
        $type = $this->documentType(fee: 0);
        $request = $this->submit($student, $type);
        $automation = app(RequestAutomation::class);

        $automation->run(); // verified

        foreach ([RequestStage::ForPreparation, RequestStage::ForFinalization, RequestStage::ReadyForRelease] as $stage) {
            $this->travel(61)->seconds();
            $automation->run();
            $this->assertSame($stage, $request->fresh()->current_stage);
        }

        $this->assertSame(RequestStatus::ReadyForPickup, $request->fresh()->status);

        $lifecycle = app(RequestLifecycle::class);
        $lifecycle->confirmReceived($request, $student);
        $this->assertSame(RequestStatus::Completed, $request->fresh()->status);

        // Completed but not archived still blocks a new request.
        try {
            $this->submit($student, $type);
            $this->fail('A completed, unarchived request should block a new one.');
        } catch (RequestNotAllowedException) {
            // expected
        }

        $lifecycle->archive($request, $student);
        $this->assertNotNull($this->submit($student, $type));
    }

    public function test_a_student_cannot_act_on_someone_elses_request(): void
    {
        $owner = $this->student('s1');
        $other = $this->student('s2');
        $request = $this->submit($owner, $this->documentType(fee: 0));

        $this->expectException(RequestNotAllowedException::class);
        app(RequestLifecycle::class)->cancel($request, $other);
    }

    // ---- helpers ----------------------------------------------------------

    private function student(string $key): User
    {
        return User::factory()->create([
            'name' => $key,
            'school_id' => $key.'-001',
            'firstname' => $key,
            'surname' => 'Test',
            'email' => $key.'@example.test',
            'password' => 'password',
            'role' => Role::Student,
            'status' => UserStatus::Activated,
        ]);
    }

    private function documentType(float $fee): DocumentType
    {
        $type = DocumentType::create([
            'name' => 'Certificate '.uniqid(),
            'description' => 'Test document',
            'processing_days' => 0,
            'fee' => $fee,
            'is_active' => true,
        ]);

        DocumentRequirement::create([
            'document_type_id' => $type->id,
            'name' => 'Valid ID',
            'description' => 'A government or school ID',
            'is_required' => true,
        ]);

        return $type;
    }

    /** @param  array<string, mixed>  $payment */
    private function submit(User $student, DocumentType $type, array $payment = [])
    {
        $requirement = $type->requirements()->first();

        $data = [
            'purpose' => 'Testing',
            'payment_method' => 'GCash',
            'payment_reference' => 'GC-12345678',
            'payment_amount' => $type->fee,
            'payment_paid_on' => now()->toDateString(),
        ];

        return app(SubmitDocumentRequest::class)->execute(
            $student,
            $type,
            array_merge($data, $payment),
            [$requirement->id => UploadedFile::fake()->image('id.jpg', 300, 300)],
            (float) $type->fee > 0
                ? UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n%proof\n")
                : null,
        );
    }
}
