<?php

namespace Tests\Feature;

use App\Enums\ClaimingMethod;
use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\UploadedRequirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_student_can_submit_a_document_request_with_private_requirement_uploads(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        $staff = $this->createUser(Role::Staff, 'staff');
        [$documentType, $requirement] = $this->createDocumentType();
        $this->actingAs($student)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee('Request a document')
            ->assertSee('Valid ID');

        $response = $this->actingAs($student)->post(route('requests.store'), [
            'document_type_id' => $documentType->id,
            'purpose' => 'For employment application',
            'claiming_method' => ClaimingMethod::PickUp->value,
            'requester_remarks' => 'Please contact me through my school email.',
            'requirements' => [
                $requirement->id => UploadedFile::fake()->create('valid-id.pdf', 100, 'application/pdf'),
            ],
        ]);

        $requestRecord = DocumentRequest::firstOrFail();
        $response->assertRedirect(route('requests.show', $requestRecord));
        $this->assertSame(RequestStatus::Pending, $requestRecord->status);
        $this->assertSame(RequestStage::RequestReceived, $requestRecord->current_stage);
        $this->assertDatabaseHas('document_request_histories', [
            'document_request_id' => $requestRecord->id,
            'event' => 'Request submitted',
        ]);
        Storage::disk('local')->assertExists($requestRecord->uploads()->firstOrFail()->path);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $staff->id,
            'type' => \App\Notifications\PortalNotification::class,
        ]);
        $this->get(route('requests.show', $requestRecord))->assertOk()->assertSee('Request history');
    }

    public function test_student_cannot_submit_a_request_for_email_delivery(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        [$documentType, $requirement] = $this->createDocumentType();

        $this->actingAs($student)
            ->from(route('requests.index'))
            ->post(route('requests.store'), [
                'document_type_id' => $documentType->id,
                'purpose' => 'For school use',
                'claiming_method' => 'Email',
                'requirements' => [
                    $requirement->id => UploadedFile::fake()->create('valid-id.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertSessionHasErrors('claiming_method');

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_student_must_upload_all_required_files_before_request_submission(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        [$documentType] = $this->createDocumentType();

        $this->actingAs($student)
            ->from(route('requests.index'))
            ->post(route('requests.store'), [
                'document_type_id' => $documentType->id,
                'purpose' => 'For school use',
                'claiming_method' => ClaimingMethod::PickUp->value,
            ])
            ->assertSessionHasErrors('requirements.1');

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_staff_can_verify_requirements_process_and_finish_a_pickup_request(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        $staff = $this->createUser(Role::Staff, 'staff');
        [$documentType, $requirement] = $this->createDocumentType();
        $documentRequest = DocumentRequest::create([
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'purpose' => 'Employment',
            'claiming_method' => ClaimingMethod::PickUp,
            'status' => RequestStatus::Pending,
            'current_stage' => RequestStage::RequestReceived,
        ]);
        $upload = $documentRequest->uploads()->create([
            'document_requirement_id' => $requirement->id,
            'original_name' => 'valid-id.pdf',
            'path' => 'document-requests/1/valid-id.pdf',
        ]);

        $this->actingAs($staff)
            ->get(route('request-management.index'))
            ->assertOk()
            ->assertSee('Request management');

        $this->get(route('request-records.index'))->assertOk()->assertSee('Request history');

        $this->post(route('request-management.verify', [$documentRequest, $upload]), [
            'is_verified' => '1',
            'verification_remarks' => 'Name is readable.',
        ])
            ->assertRedirect();
        $this->assertTrue($upload->fresh()->is_verified);

        $this->post(route('request-management.start', $documentRequest))
            ->assertRedirect();
        $this->assertSame(RequestStatus::InProgress, $documentRequest->fresh()->status);
        $this->assertSame(RequestStage::ForVerification, $documentRequest->fresh()->current_stage);

        foreach ([RequestStage::ForPreparation, RequestStage::ForFinalization] as $expectedStage) {
            $this->post(route('request-management.advance', $documentRequest))->assertRedirect();
            $this->assertSame($expectedStage, $documentRequest->fresh()->current_stage);
        }
        $this->post(route('request-management.advance', $documentRequest))->assertRedirect();
        $this->assertSame(RequestStatus::ReadyForPickup, $documentRequest->fresh()->status);
        $this->assertSame(RequestStage::ReadyForRelease, $documentRequest->fresh()->current_stage);

        $this->actingAs($student)
            ->post(route('requests.release', $documentRequest))
            ->assertRedirect();
        $this->assertSame(RequestStatus::Completed, $documentRequest->fresh()->status);
        $this->assertSame(RequestStage::Released, $documentRequest->fresh()->current_stage);
        $this->assertDatabaseHas('document_request_histories', [
            'document_request_id' => $documentRequest->id,
            'event' => 'Student confirmed document collection',
        ]);
    }

    public function test_processing_cannot_start_until_all_required_files_are_verified(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        $staff = $this->createUser(Role::Staff, 'staff');
        [$documentType, $requirement] = $this->createDocumentType();
        $documentRequest = DocumentRequest::create([
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'status' => RequestStatus::Pending,
            'current_stage' => RequestStage::RequestReceived,
        ]);
        $documentRequest->uploads()->create([
            'document_requirement_id' => $requirement->id,
            'original_name' => 'valid-id.pdf',
            'path' => 'document-requests/1/valid-id.pdf',
        ]);

        $this->actingAs($staff)
            ->from(route('request-management.show', $documentRequest))
            ->post(route('request-management.start', $documentRequest))
            ->assertSessionHasErrors('requirements');

        $this->assertSame(RequestStatus::Pending, $documentRequest->fresh()->status);
    }

    public function test_staff_can_request_correction_and_student_can_replace_requirement_without_rejection(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        $staff = $this->createUser(Role::Staff, 'staff');
        [$documentType, $requirement] = $this->createDocumentType();
        $documentRequest = DocumentRequest::create([
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'status' => RequestStatus::Pending,
            'current_stage' => RequestStage::RequestReceived,
        ]);
        $upload = $documentRequest->uploads()->create([
            'document_requirement_id' => $requirement->id,
            'original_name' => 'blurry-id.pdf',
            'path' => 'document-requests/'.$documentRequest->id.'/blurry-id.pdf',
        ]);
        Storage::disk('local')->put($upload->path, 'original');

        $this->actingAs($staff)
            ->post(route('request-management.verify', [$documentRequest, $upload]), [
                'is_verified' => '0',
                'verification_remarks' => 'Please upload a clearer image.',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('document_request_histories', [
            'document_request_id' => $documentRequest->id,
            'event' => 'Requirement needs correction',
        ]);

        $this->actingAs($student)
            ->post(route('requests.requirements.replace', [$documentRequest, $upload]), [
                'requirement_file' => UploadedFile::fake()->create('clear-id.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertSame('clear-id.pdf', $upload->fresh()->original_name);
        $this->assertNull($upload->fresh()->verification_remarks);
        $this->assertFalse($upload->fresh()->is_verified);
        $this->assertSame(RequestStatus::Pending, $documentRequest->fresh()->status);
        $this->assertFalse(Route::has('request-management.reject'));
    }

    public function test_ai_requirement_review_is_only_a_recommendation_and_does_not_verify_the_upload(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        $staff = $this->createUser(Role::Staff, 'staff');
        [$documentType, $requirement] = $this->createDocumentType();
        $documentRequest = DocumentRequest::create([
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'status' => RequestStatus::Pending,
            'current_stage' => RequestStage::RequestReceived,
        ]);
        $upload = $documentRequest->uploads()->create([
            'document_requirement_id' => $requirement->id,
            'original_name' => 'valid-id.pdf',
            'path' => 'document-requests/'.$documentRequest->id.'/valid-id.pdf',
        ]);
        Storage::disk('local')->put($upload->path, 'pdf data');
        config(['services.gemini.api_key' => 'test-api-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'recommendation' => 'appears_complete',
                            'remarks' => 'The visible information appears complete.',
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        $this->actingAs($staff)
            ->post(route('request-management.ai-review', [$documentRequest, $upload]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse($upload->fresh()->is_verified);
        $this->assertSame('appears_complete', $upload->fresh()->ai_review_status);
        $this->assertDatabaseHas('document_request_histories', [
            'document_request_id' => $documentRequest->id,
            'event' => 'AI review recommendation generated',
        ]);
    }

    public function test_students_can_only_view_and_download_their_own_request_files(): void
    {
        $owner = $this->createUser(Role::Student, 'owner');
        $otherStudent = $this->createUser(Role::Student, 'other');
        [$documentType, $requirement] = $this->createDocumentType();
        $documentRequest = DocumentRequest::create([
            'user_id' => $owner->id,
            'document_type_id' => $documentType->id,
            'status' => RequestStatus::Pending,
            'current_stage' => RequestStage::RequestReceived,
        ]);
        $path = 'document-requests/'.$documentRequest->id.'/proof.pdf';
        Storage::disk('local')->put($path, 'sample pdf bytes');
        $upload = UploadedRequirement::create([
            'document_request_id' => $documentRequest->id,
            'document_requirement_id' => $requirement->id,
            'original_name' => 'proof.pdf',
            'path' => $path,
        ]);

        $this->actingAs($otherStudent)
            ->get(route('requests.show', $documentRequest))
            ->assertNotFound();
        $this->get(route('request-files.download', [$documentRequest, $upload]))->assertNotFound();
        $this->actingAs($owner)
            ->get(route('request-files.download', [$documentRequest, $upload]))
            ->assertOk();
    }

    public function test_staff_and_students_cannot_open_admin_management_pages(): void
    {
        $student = $this->createUser(Role::Student, 'student');
        $staff = $this->createUser(Role::Staff, 'staff');

        $this->actingAs($student)->get(route('admin.departments.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
    }

    private function createDocumentType(): array
    {
        $documentType = DocumentType::create([
            'name' => 'Transcript of Records',
            'description' => 'Official academic transcript',
            'processing_days' => 3,
            'fee' => 100,
            'is_active' => true,
        ]);
        $requirement = DocumentRequirement::create([
            'document_type_id' => $documentType->id,
            'name' => 'Valid ID',
            'description' => 'Upload one current school ID.',
            'is_required' => true,
        ]);

        return [$documentType, $requirement];
    }

    private function createUser(Role $role, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'school_id' => $name.'-001',
            'firstname' => ucfirst($name),
            'surname' => 'Test',
            'email' => $name.'@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => UserStatus::Activated,
        ]);
    }
}
