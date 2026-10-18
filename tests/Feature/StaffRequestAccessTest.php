<?php

namespace Tests\Feature;

use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRequestAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['smartdoc.automation.tick_seconds' => 0]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('staff.requests.index'))->assertRedirect(route('login'));
    }

    public function test_students_cannot_open_staff_pages(): void
    {
        $student = $this->user('s1', Role::Student);
        $request = $this->makeRequest($student);

        $this->actingAs($student)->get(route('staff.requests.index'))->assertForbidden();
        $this->actingAs($student)->get(route('staff.requests.show', $request))->assertForbidden();
    }

    public function test_staff_and_admin_can_monitor_requests(): void
    {
        $student = $this->user('s1', Role::Student);
        $request = $this->makeRequest($student);

        foreach ([Role::Staff, Role::Admin] as $role) {
            $viewer = $this->user('v'.$role->value, $role);

            $this->actingAs($viewer)->get(route('staff.requests.index'))
                ->assertOk()->assertSee($request->reference)->assertSee('s1-001');

            $this->actingAs($viewer)->get(route('staff.requests.show', $request))
                ->assertOk()->assertSee('Evaluator remarks')->assertSee('cannot change');
        }
    }

    public function test_staff_cannot_perform_student_actions(): void
    {
        $student = $this->user('s1', Role::Student);
        $staff = $this->user('st1', Role::Staff);
        $request = $this->makeRequest($student);

        $this->actingAs($staff)->post(route('student.requests.cancel', $request))->assertForbidden();
        $this->actingAs($staff)->post(route('student.requests.receive', $request))->assertForbidden();
        $this->actingAs($staff)->post(route('student.requests.archive', $request))->assertForbidden();
        $this->actingAs($staff)->get(route('student.requests.index'))->assertForbidden();

        $this->assertSame(RequestStatus::Pending, $request->fresh()->status);
    }

    public function test_staff_can_search_and_filter(): void
    {
        $alice = $this->user('alice', Role::Student);
        $bob = $this->user('bob', Role::Student);
        $staff = $this->user('st1', Role::Staff);

        $forAlice = $this->makeRequest($alice);
        $forBob = $this->makeRequest($bob, ['status' => RequestStatus::InProgress]);
        $archived = $this->makeRequest($alice, ['status' => RequestStatus::Completed, 'is_archived' => true]);

        $this->actingAs($staff)->get(route('staff.requests.index', ['q' => 'alice-001']))
            ->assertOk()->assertSee($forAlice->reference)->assertDontSee($forBob->reference);

        $this->actingAs($staff)->get(route('staff.requests.index', ['q' => $forBob->reference]))
            ->assertOk()->assertSee('bob-001')->assertDontSee('alice-001');

        $this->actingAs($staff)->get(route('staff.requests.index', ['status' => 'In Progress']))
            ->assertOk()->assertSee($forBob->reference)->assertDontSee($forAlice->reference);

        // Archived requests are hidden by default and visible on request.
        $this->actingAs($staff)->get(route('staff.requests.index'))
            ->assertOk()->assertDontSee($archived->reference);
        $this->actingAs($staff)->get(route('staff.requests.index', ['archived' => 'archived']))
            ->assertOk()->assertSee($archived->reference);
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $student = $this->user('s1', Role::Student);
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($student);

        $this->actingAs($staff)->get(route('staff.requests.index', ['q' => '%']))
            ->assertOk()->assertSee('No requests found');
    }

    public function test_staff_dashboard_open_count_excludes_cancelled_completed_and_legacy_rejected(): void
    {
        $student = $this->user('s1', Role::Student);
        $staff = $this->user('st1', Role::Staff);

        $this->makeRequest($student, ['status' => RequestStatus::Pending]);
        $this->makeRequest($student, ['status' => RequestStatus::Cancelled]);
        $this->makeRequest($student, ['status' => RequestStatus::Completed]);
        $this->makeRequest($student, ['status' => 'Rejected']); // legacy row, read-only history

        $response = $this->actingAs($staff)->get(route('dashboard'))->assertOk();

        $counts = $response->viewData('requestCounts');
        $this->assertSame(1, $counts['open']);
        $this->assertSame(1, $counts['cancelled']);
        $this->assertSame(1, $counts['legacy_rejected']);
        $this->assertSame(4, $counts['total']);
    }

    public function test_legacy_rejected_and_email_rows_still_render_read_only(): void
    {
        $student = $this->user('s1', Role::Student);
        $staff = $this->user('st1', Role::Staff);
        $legacy = $this->makeRequest($student, ['status' => 'Rejected', 'claiming_method' => 'Email']);

        $this->actingAs($staff)->get(route('staff.requests.show', $legacy))
            ->assertOk()->assertSee('Rejected')->assertSee('Email (legacy request)');

        $this->actingAs($student)->get(route('student.requests.show', $legacy))
            ->assertOk()->assertSee('Rejected');

        // A legacy rejected row is not "blocking": the student may submit again.
        $this->assertFalse(DocumentRequest::query()->where('user_id', $student->id)->blocking()->exists());
    }

    public function test_student_dashboard_only_counts_their_own_requests(): void
    {
        $a = $this->user('s1', Role::Student);
        $b = $this->user('s2', Role::Student);
        $this->makeRequest($a);
        $this->makeRequest($b);
        $this->makeRequest($b, ['status' => RequestStatus::Cancelled]);

        $counts = $this->actingAs($a)->get(route('dashboard'))->assertOk()->viewData('requestCounts');

        $this->assertSame(1, $counts['total']);
        $this->assertSame(1, $counts['open']);
    }

    public function test_page_load_trigger_runs_automation_at_most_once_per_window(): void
    {
        config(['smartdoc.automation.tick_seconds' => 60]);

        $student = $this->user('s1', Role::Student);
        $staff = $this->user('st1', Role::Staff);
        $request = $this->makeRequest($student);

        // No uploads/payment for a free type with no requirements -> passes verification.
        $this->actingAs($staff)->get(route('staff.requests.index'))->assertOk();
        $this->assertSame(RequestStatus::InProgress, $request->fresh()->status);
        $this->assertSame(RequestStage::ForVerification, $request->fresh()->current_stage);

        // A second request created inside the window is not touched by the next page load.
        $second = $this->makeRequest($this->user('s2', Role::Student));
        $this->actingAs($staff)->get(route('staff.requests.index'))->assertOk();
        $this->assertSame(RequestStatus::Pending, $second->fresh()->status);
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
        $type = DocumentType::query()->first() ?? DocumentType::create([
            'name' => 'Enrollment Verification',
            'description' => 'Verification letter',
            'processing_days' => 3,
            'fee' => 0,
            'is_active' => true,
        ]);

        // forceFill: fields such as is_archived are intentionally not mass-assignable.
        $request = new DocumentRequest();
        $request->forceFill(array_merge([
            'user_id' => $student->id,
            'document_type_id' => $type->id,
            'status' => RequestStatus::Pending,
        ], $attributes))->save();

        return $request->refresh();
    }
}
