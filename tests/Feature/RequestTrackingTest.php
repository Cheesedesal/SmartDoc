<?php

namespace Tests\Feature;

use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\RequestAiLog;
use App\Models\UploadedRequirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['smartdoc.automation.tick_seconds' => 0]); // keep persisted state fixed during assertions
    }

    public function test_timeline_reflects_persisted_stage_and_log_times(): void
    {
        $student = $this->user('s1', Role::Student);
        $request = $this->makeRequest($student, [
            'status' => RequestStatus::InProgress,
            'current_stage' => RequestStage::ForPreparation,
        ]);
        RequestAiLog::forceCreate([
            'document_request_id' => $request->id, 'action' => 'verified',
            'from_stage' => 'Request Received', 'to_stage' => 'For Verification',
        ]);
        RequestAiLog::forceCreate([
            'document_request_id' => $request->id, 'action' => 'stage_advanced',
            'from_stage' => 'For Verification', 'to_stage' => 'For Preparation',
        ]);

        $steps = collect($request->fresh()->timeline())->keyBy('stage');

        $this->assertSame('done', $steps['Request Received']['state']);
        $this->assertSame('done', $steps['For Verification']['state']);
        $this->assertNotNull($steps['For Verification']['at']);
        $this->assertSame('current', $steps['For Preparation']['state']);
        $this->assertSame('upcoming', $steps['For Finalization']['state']);
        $this->assertNull($steps['For Finalization']['at']); // no invented times for future steps
        $this->assertSame('upcoming', $steps['Released']['state']);
    }

    public function test_timeline_for_completed_and_cancelled_requests(): void
    {
        $student = $this->user('s1', Role::Student);

        $done = $this->makeRequest($student, ['status' => RequestStatus::Completed, 'current_stage' => RequestStage::Released, 'completed_at' => now(), 'released_at' => now()]);
        $this->assertSame(['done'], array_values(array_unique(array_column($done->fresh()->timeline(), 'state'))));

        $cancelled = $this->makeRequest($this->user('s2', Role::Student), ['status' => RequestStatus::Cancelled]);
        $states = array_column($cancelled->fresh()->timeline(), 'state');
        $this->assertSame('done', $states[0]);
        $this->assertSame(['upcoming'], array_values(array_unique(array_slice($states, 1))));
    }

    public function test_student_sees_timeline_and_counts_only_for_own_requests(): void
    {
        $alice = $this->user('alice', Role::Student);
        $bob = $this->user('bob', Role::Student);
        $mine = $this->makeRequest($alice, ['status' => RequestStatus::InProgress, 'current_stage' => RequestStage::ForVerification]);
        $this->makeRequest($bob, ['status' => RequestStatus::Pending]);

        $this->actingAs($alice)->get(route('student.requests.show', $mine))
            ->assertOk()->assertSee('Processing stages')->assertSee('For Verification');

        $response = $this->actingAs($alice)->get(route('student.requests.index'))->assertOk();
        $counts = $response->viewData('counts');
        $this->assertSame(1, $counts['total']);
        $this->assertSame(0, $counts['pending']);
        $this->assertSame(1, $counts['in_progress']);
    }

    public function test_student_cannot_open_another_students_request(): void
    {
        $alice = $this->user('alice', Role::Student);
        $bob = $this->user('bob', Role::Student);
        $bobs = $this->makeRequest($bob);

        $this->actingAs($alice)->get(route('student.requests.show', $bobs))->assertForbidden();
    }

    public function test_student_filters_by_status_and_document_without_leaking_others(): void
    {
        $alice = $this->user('alice', Role::Student);
        $bob = $this->user('bob', Role::Student);

        $pending = $this->makeRequest($alice, ['status' => RequestStatus::Pending]);
        $cancelled = $this->makeRequest($alice, ['status' => RequestStatus::Cancelled]);
        $others = $this->makeRequest($bob, ['status' => RequestStatus::Pending]);

        $this->actingAs($alice)->get(route('student.requests.index', ['status' => 'Pending']))
            ->assertOk()->assertSee($pending->reference)->assertDontSee($cancelled->reference);

        $this->actingAs($alice)->get(route('student.requests.index', ['status' => 'Pending', 'type' => $pending->document_type_id]))
            ->assertOk()->assertSee($pending->reference);

        $this->assertNotSame($pending->reference, $others->reference);
        $this->actingAs($alice)->get(route('student.requests.index'))
            ->assertOk()->assertDontSee($others->reference);
    }

    public function test_staff_queue_shows_open_counts_and_requirement_checklist(): void
    {
        $student = $this->user('s1', Role::Student);
        $staff = $this->user('st1', Role::Staff);

        $type = DocumentType::create(['name' => 'Good Moral', 'description' => 'd', 'processing_days' => 2, 'fee' => 0, 'is_active' => true]);
        $required = DocumentRequirement::create(['document_type_id' => $type->id, 'name' => 'Valid ID', 'description' => 'ID', 'is_required' => true]);
        DocumentRequirement::create(['document_type_id' => $type->id, 'name' => 'Clearance', 'description' => 'Clr', 'is_required' => true]);

        $request = $this->makeRequest($student, ['document_type_id' => $type->id, 'status' => RequestStatus::Pending]);
        $this->makeRequest($this->user('s2', Role::Student), ['document_type_id' => $type->id, 'status' => RequestStatus::InProgress]);
        $this->makeRequest($this->user('s3', Role::Student), ['document_type_id' => $type->id, 'status' => RequestStatus::Completed]); // not open

        UploadedRequirement::forceCreate([
            'document_request_id' => $request->id,
            'document_requirement_id' => $required->id,
            'original_name' => 'id.png',
            'path' => 'requirements/x.png',
        ]);

        $counts = $this->actingAs($staff)->get(route('staff.requests.index'))->assertOk()->viewData('counts');
        $this->assertSame(2, $counts['open']);
        $this->assertSame(1, $counts['pending']);
        $this->assertSame(1, $counts['in_progress']);
        $this->assertSame(0, $counts['ready']);

        $this->actingAs($staff)->get(route('staff.requests.show', $request))
            ->assertOk()
            ->assertSee('Requirement checklist')
            ->assertSee('Valid ID')->assertSee('Uploaded')
            ->assertSee('Clearance')->assertSee('Missing');
    }

    public function test_staff_still_cannot_change_status(): void
    {
        $student = $this->user('s1', Role::Student);
        $staff = $this->user('st1', Role::Staff);
        $request = $this->makeRequest($student);

        $this->actingAs($staff)->post(route('student.requests.receive', $request))->assertForbidden();
        $this->assertSame(RequestStatus::Pending, $request->fresh()->status);
    }

    // ---- helpers ----------------------------------------------------------

    private function user(string $key, Role $role): User
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

    /** @param  array<string, mixed>  $attributes */
    private function makeRequest(User $student, array $attributes = []): DocumentRequest
    {
        $typeId = $attributes['document_type_id'] ?? (DocumentType::query()->first() ?? DocumentType::create([
            'name' => 'Enrollment Verification',
            'description' => 'Verification letter',
            'processing_days' => 3,
            'fee' => 0,
            'is_active' => true,
        ]))->id;

        $request = new DocumentRequest();
        $request->forceFill(array_merge([
            'user_id' => $student->id,
            'document_type_id' => $typeId,
            'status' => RequestStatus::Pending,
        ], $attributes))->save();

        return $request->refresh();
    }
}
