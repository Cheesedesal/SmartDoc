<?php

namespace Tests\Feature;

use App\Actions\SubmitDocumentRequest;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\RequestAutomation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        config([
            'smartdoc.uploads.disk' => 'local',
            'smartdoc.automation.tick_seconds' => 0, // page loads must not run the engine in these tests
            'smartdoc.automation.min_stage_seconds' => 60,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('student.requests.index'))->assertRedirect(route('login'));
        $this->get(route('student.requests.create'))->assertRedirect(route('login'));
    }

    public function test_student_sees_an_empty_state_with_no_requests(): void
    {
        $this->actingAs($this->user('s1'))
            ->get(route('student.requests.index'))
            ->assertOk()
            ->assertSee('No requests yet');
    }

    public function test_student_can_submit_a_free_request_and_ownership_comes_from_the_session(): void
    {
        $student = $this->user('s1');
        $other = $this->user('s2');
        $type = $this->documentType(0);
        $requirement = $type->requirements()->first();

        $response = $this->actingAs($student)->post(route('student.requests.store'), [
            'document_type_id' => $type->id,
            'purpose' => 'Scholarship',
            // Attempts to spoof protected fields must be ignored:
            'user_id' => $other->id,
            'status' => 'Completed',
            'payment_status' => 'Paid',
            'requirements' => [$requirement->id => UploadedFile::fake()->image('id.jpg', 300, 300)],
        ]);

        $created = DocumentRequest::first();

        $response->assertRedirect(route('student.requests.show', $created));
        $this->assertSame($student->id, $created->user_id);
        $this->assertSame(RequestStatus::Pending, $created->status);
        $this->assertSame(PaymentStatus::NotRequired, $created->payment_status);
        $this->assertCount(1, $created->uploads);
        Storage::disk('local')->assertExists($created->uploads->first()->path);
    }

    public function test_second_submission_is_blocked_by_the_single_request_rule(): void
    {
        $student = $this->user('s1');
        $type = $this->documentType(0);
        $this->submit($student, $type);

        $this->actingAs($student)->post(route('student.requests.store'), [
            'document_type_id' => $type->id,
            'requirements' => [$type->requirements()->first()->id => UploadedFile::fake()->image('id.jpg', 300, 300)],
        ])->assertSessionHasErrors('request');

        $this->assertSame(1, DocumentRequest::count());
    }

    public function test_missing_required_upload_and_bad_file_types_are_rejected(): void
    {
        $student = $this->user('s1');
        $type = $this->documentType(0);
        $requirementId = $type->requirements()->first()->id;

        $this->actingAs($student)->post(route('student.requests.store'), [
            'document_type_id' => $type->id,
        ])->assertSessionHasErrors(['requirements.'.$requirementId]);

        $this->actingAs($student)->post(route('student.requests.store'), [
            'document_type_id' => $type->id,
            'requirements' => [$requirementId => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
        ])->assertSessionHasErrors(['requirements.'.$requirementId]);

        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_paid_document_requires_payment_details_and_proof(): void
    {
        $student = $this->user('s1');
        $type = $this->documentType(100);

        $this->actingAs($student)->post(route('student.requests.store'), [
            'document_type_id' => $type->id,
            'requirements' => [$type->requirements()->first()->id => UploadedFile::fake()->image('id.jpg', 300, 300)],
        ])->assertSessionHasErrors(['payment_method', 'payment_reference', 'payment_amount', 'payment_paid_on', 'payment_proof']);

        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_students_cannot_view_or_act_on_another_students_request(): void
    {
        $owner = $this->user('s1');
        $intruder = $this->user('s2');
        $request = $this->submit($owner, $this->documentType(0));

        $this->actingAs($intruder)->get(route('student.requests.show', $request))->assertForbidden();
        $this->actingAs($intruder)->post(route('student.requests.cancel', $request))->assertForbidden();
        $this->actingAs($intruder)->post(route('student.requests.receive', $request))->assertForbidden();
        $this->actingAs($intruder)->post(route('student.requests.archive', $request))->assertForbidden();

        $this->assertSame(RequestStatus::Pending, $request->fresh()->status);
    }

    public function test_student_can_cancel_a_pending_request_and_submit_again(): void
    {
        $student = $this->user('s1');
        $type = $this->documentType(0);
        $request = $this->submit($student, $type);

        $this->actingAs($student)
            ->post(route('student.requests.cancel', $request), ['reason' => 'Wrong document'])
            ->assertRedirect(route('student.requests.show', $request))
            ->assertSessionHas('status');

        $this->assertSame(RequestStatus::Cancelled, $request->fresh()->status);
        $this->assertNotNull($this->submit($student, $type));
    }

    public function test_flagged_payment_can_be_resubmitted_and_is_then_accepted(): void
    {
        $student = $this->user('s1');
        $type = $this->documentType(100);
        $request = $this->submit($student, $type, ['payment_amount' => 50]);
        $oldProof = $request->payment_proof_path;

        app(RequestAutomation::class)->run();
        $this->assertSame(PaymentStatus::Flagged, $request->fresh()->payment_status);

        $this->actingAs($student)->post(route('student.requests.payment', $request), [
            'payment_method' => 'GCash',
            'payment_reference' => 'GC-99999999',
            'payment_amount' => 100,
            'payment_paid_on' => now()->toDateString(),
            'payment_proof' => UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n%new\n"),
        ])->assertRedirect(route('student.requests.show', $request))->assertSessionHas('status');

        $request->refresh();
        $this->assertSame(PaymentStatus::Submitted, $request->payment_status);
        $this->assertNull($request->ai_checked_at);
        $this->assertSame('GC-99999999', $request->payment_reference);
        Storage::disk('local')->assertMissing($oldProof);
        Storage::disk('local')->assertExists($request->payment_proof_path);

        app(RequestAutomation::class)->run();
        $request->refresh();
        $this->assertSame(RequestStatus::InProgress, $request->status);
        $this->assertSame(PaymentStatus::Paid, $request->payment_status);
    }

    public function test_resubmission_is_refused_when_the_payment_is_not_flagged(): void
    {
        $student = $this->user('s1');
        $request = $this->submit($student, $this->documentType(100));
        $original = $request->payment_reference;

        $this->actingAs($student)->post(route('student.requests.payment', $request), [
            'payment_method' => 'GCash',
            'payment_reference' => 'GC-77777777',
            'payment_amount' => 100,
            'payment_paid_on' => now()->toDateString(),
            'payment_proof' => UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n%new\n"),
        ])->assertSessionHasErrors('request');

        $this->assertSame($original, $request->fresh()->payment_reference);
    }

    public function test_receipt_confirmation_and_archiving_over_http(): void
    {
        $student = $this->user('s1');
        $type = $this->documentType(0);
        $request = $this->submit($student, $type);

        // Cannot confirm receipt before the document is ready.
        $this->actingAs($student)->post(route('student.requests.receive', $request))->assertSessionHasErrors('request');

        $request->forceFill(['status' => RequestStatus::ReadyForPickup])->save();
        $this->actingAs($student)->post(route('student.requests.receive', $request))->assertSessionHas('status');
        $this->assertSame(RequestStatus::Completed, $request->fresh()->status);

        $this->actingAs($student)->post(route('student.requests.archive', $request))->assertSessionHas('status');
        $this->assertTrue($request->fresh()->is_archived);
        $this->assertNotNull($this->submit($student, $type));
    }

    public function test_uploads_are_only_downloadable_by_the_owner_or_staff(): void
    {
        $owner = $this->user('s1');
        $intruder = $this->user('s2');
        $staff = $this->user('st1', Role::Staff);
        $request = $this->submit($owner, $this->documentType(0));
        $upload = $request->uploads()->first();

        $url = route('requests.requirement', [$request, $upload]);

        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs($staff)->get($url)->assertOk();
        $this->actingAs($intruder)->get($url)->assertForbidden();
    }

    public function test_an_upload_cannot_be_fetched_through_a_different_request(): void
    {
        $a = $this->user('s1');
        $b = $this->user('s2');
        $typeA = $this->documentType(0);
        $requestA = $this->submit($a, $typeA);
        $requestB = $this->submit($b, $typeA);
        $uploadOfA = $requestA->uploads()->first();

        // B owns requestB, but uploadOfA does not belong to it.
        $this->actingAs($b)
            ->get(route('requests.requirement', [$requestB, $uploadOfA]))
            ->assertNotFound();
    }

    // ---- helpers ----------------------------------------------------------

    private function user(string $key, Role $role = Role::Student): User
    {
        return User::factory()->create([
            'name' => $key,
            'school_id' => $key.'-001',
            'firstname' => $key,
            'surname' => 'Test',
            'email' => $key.'@example.test',
            'password' => 'password',
            'role' => $role,
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
    private function submit(User $student, DocumentType $type, array $payment = []): DocumentRequest
    {
        return app(SubmitDocumentRequest::class)->execute(
            $student,
            $type,
            array_merge([
                'purpose' => 'Testing',
                'payment_method' => 'GCash',
                'payment_reference' => 'GC-'.strtoupper(bin2hex(random_bytes(6))),
                'payment_amount' => $type->fee,
                'payment_paid_on' => now()->toDateString(),
            ], $payment),
            [$type->requirements()->first()->id => UploadedFile::fake()->image('id.jpg', 300, 300)],
            (float) $type->fee > 0
                ? UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n%proof\n")
                : null,
        );
    }
}
