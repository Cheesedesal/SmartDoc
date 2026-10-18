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

class RequestRecordsTest extends TestCase
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
        $this->get(route('request-records.index'))->assertRedirect(route('login'));
    }

    public function test_students_cannot_open_request_records(): void
    {
        $student = $this->user('s1', Role::Student);

        $this->actingAs($student)->get(route('request-records.index'))->assertForbidden();
    }

    public function test_staff_and_admin_can_open_the_page_and_see_the_sidebar_link(): void
    {
        // The table (and its column headings) only renders when there is at least one record.
        $this->makeRequest($this->user('s1', Role::Student), ['status' => RequestStatus::Completed]);

        foreach ([Role::Staff, Role::Admin] as $role) {
            $viewer = $this->user('v'.$role->value, $role);

            $this->actingAs($viewer)->get(route('request-records.index'))
                ->assertOk()
                ->assertSee('Request Record History')
                ->assertSee('Student Name')
                ->assertSee('Rejected / Completed At')
                ->assertSee('Request records');
        }
    }

    public function test_the_page_is_read_only(): void
    {
        $staff = $this->user('st1', Role::Staff);

        $this->actingAs($staff)->post('/request-records')->assertStatus(405);
        $this->actingAs($staff)->put('/request-records')->assertStatus(405);
        $this->actingAs($staff)->delete('/request-records')->assertStatus(405);
    }

    public function test_the_removed_student_records_page_is_not_restored(): void
    {
        $staff = $this->user('st1', Role::Staff);

        $this->actingAs($staff)->get('/student-records')->assertNotFound();
        $this->actingAs($staff)->get('/students')->assertNotFound();
    }

    public function test_only_completed_and_legacy_rejected_requests_are_listed(): void
    {
        $student = $this->user('s1', Role::Student);
        $staff = $this->user('st1', Role::Staff);

        $completed = $this->makeRequest($student, ['status' => RequestStatus::Completed]);
        $rejected = $this->makeRequest($this->user('s2', Role::Student), ['status' => 'Rejected']);
        $pending = $this->makeRequest($this->user('s3', Role::Student), ['status' => RequestStatus::Pending]);
        $progress = $this->makeRequest($this->user('s4', Role::Student), ['status' => RequestStatus::InProgress, 'current_stage' => RequestStage::ForVerification]);
        $ready = $this->makeRequest($this->user('s5', Role::Student), ['status' => RequestStatus::ReadyForPickup]);
        $cancelled = $this->makeRequest($this->user('s6', Role::Student), ['status' => RequestStatus::Cancelled]);

        $rows = $this->actingAs($staff)->get(route('request-records.index'))->assertOk()->viewData('requests');

        $ids = $rows->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$completed->id, $rejected->id], $ids);
        $this->assertNotContains($pending->id, $ids);
        $this->assertNotContains($progress->id, $ids);
        $this->assertNotContains($ready->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
    }

    public function test_archived_completed_requests_still_appear_in_the_history(): void
    {
        $student = $this->user('s1', Role::Student);
        $staff = $this->user('st1', Role::Staff);
        $archived = $this->makeRequest($student, ['status' => RequestStatus::Completed, 'is_archived' => true]);

        $this->actingAs($staff)->get(route('request-records.index'))
            ->assertOk()->assertSee('s1 Test');

        $this->assertSame(1, DocumentRequest::query()->records()->whereKey($archived->id)->count());
    }

    public function test_summary_counts_cover_the_whole_history_not_the_filtered_rows(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($this->user('s1', Role::Student), ['status' => RequestStatus::Completed]);
        $this->makeRequest($this->user('s2', Role::Student), ['status' => RequestStatus::Completed]);
        $this->makeRequest($this->user('s3', Role::Student), ['status' => 'Rejected']);
        $this->makeRequest($this->user('s4', Role::Student), ['status' => RequestStatus::Pending]); // not counted

        $response = $this->actingAs($staff)->get(route('request-records.index', ['status' => 'Rejected']))->assertOk();

        $this->assertSame(['total' => 3, 'completed' => 2, 'rejected' => 1], $response->viewData('counts'));
        $this->assertSame(1, $response->viewData('requests')->total());
    }

    public function test_search_by_student_name_school_id_and_request_reference(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $alice = $this->user('alice', Role::Student);
        $bob = $this->user('bob', Role::Student);
        $this->makeRequest($alice, ['status' => RequestStatus::Completed]);
        $forBob = $this->makeRequest($bob, ['status' => RequestStatus::Completed]);

        $this->actingAs($staff)->get(route('request-records.index', ['search' => 'alice Test']))
            ->assertOk()->assertSee('alice Test')->assertDontSee('bob Test');

        $this->actingAs($staff)->get(route('request-records.index', ['search' => 'bob-001']))
            ->assertOk()->assertSee('bob Test')->assertDontSee('alice Test');

        $this->actingAs($staff)->get(route('request-records.index', ['search' => $forBob->reference]))
            ->assertOk()->assertSee('bob Test')->assertDontSee('alice Test');
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($this->user('s1', Role::Student), ['status' => RequestStatus::Completed]);

        $this->actingAs($staff)->get(route('request-records.index', ['search' => '%']))
            ->assertOk()->assertSee('No request records found');
    }

    public function test_filters_by_document_type_and_status(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $typeA = DocumentType::create(['name' => 'Good Moral', 'description' => 'd', 'processing_days' => 2, 'fee' => 0, 'is_active' => true]);
        $typeB = DocumentType::create(['name' => 'Transcript', 'description' => 'd', 'processing_days' => 2, 'fee' => 0, 'is_active' => false]); // inactive types stay filterable in history

        $this->makeRequest($this->user('alice', Role::Student), ['document_type_id' => $typeA->id, 'status' => RequestStatus::Completed]);
        $this->makeRequest($this->user('bob', Role::Student), ['document_type_id' => $typeB->id, 'status' => 'Rejected']);

        $this->actingAs($staff)->get(route('request-records.index', ['document_type_id' => $typeB->id]))
            ->assertOk()->assertSee('bob Test')->assertDontSee('alice Test')->assertSee('Transcript');

        $this->actingAs($staff)->get(route('request-records.index', ['status' => 'Completed']))
            ->assertOk()->assertSee('alice Test')->assertDontSee('bob Test');

        $this->actingAs($staff)->get(route('request-records.index', ['document_type_id' => $typeA->id, 'status' => 'Rejected']))
            ->assertOk()->assertSee('No request records found');
    }

    public function test_date_range_filters_on_the_requested_date(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Completed, 'created_at' => '2026-01-10 09:00:00']);
        $this->makeRequest($this->user('bob', Role::Student), ['status' => RequestStatus::Completed, 'created_at' => '2026-03-05 14:30:00']);

        $this->actingAs($staff)->get(route('request-records.index', ['date_from' => '2026-03-01']))
            ->assertOk()->assertSee('bob Test')->assertDontSee('alice Test');

        $this->actingAs($staff)->get(route('request-records.index', ['date_to' => '2026-01-31']))
            ->assertOk()->assertSee('alice Test')->assertDontSee('bob Test');

        // Inclusive on both ends.
        $this->actingAs($staff)->get(route('request-records.index', ['date_from' => '2026-01-10', 'date_to' => '2026-03-05']))
            ->assertOk()->assertSee('alice Test')->assertSee('bob Test');
    }

    public function test_invalid_filters_are_ignored_and_reported_without_leaking_other_statuses(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $done = $this->makeRequest($this->user('alice', Role::Student), ['status' => RequestStatus::Completed]);
        $this->makeRequest($this->user('bob', Role::Student), ['status' => RequestStatus::Pending]);

        // "Pending" is not a record status: the filter is dropped, and Pending rows still never show.
        $response = $this->actingAs($staff)->get(route('request-records.index', ['status' => 'Pending']))
            ->assertOk()->assertSee('alice Test')->assertDontSee('bob Test')
            ->assertSee('Choose Completed or Rejected.');
        $this->assertSame([$done->id], $response->viewData('requests')->pluck('id')->all());

        $this->actingAs($staff)->get(route('request-records.index', ['date_from' => 'not-a-date', 'document_type_id' => 'abc']))
            ->assertOk()->assertSee('Enter a valid date.');

        $this->actingAs($staff)->get(route('request-records.index', ['date_from' => '2026-05-01', 'date_to' => '2026-04-01']))
            ->assertOk()->assertSee('on or after the start date');
    }

    public function test_completed_and_rejected_dates_and_badges_are_shown(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $this->makeRequest($this->user('alice', Role::Student), [
            'status' => RequestStatus::Completed,
            'created_at' => '2026-03-05 14:30:00',
            'completed_at' => '2026-03-09 10:05:00',
        ]);
        $this->makeRequest($this->user('bob', Role::Student), [
            'status' => 'Rejected',
            'created_at' => '2026-02-01 08:00:00',
            'rejected_at' => '2026-02-03 16:45:00',
        ]);
        $this->makeRequest($this->user('cara', Role::Student), ['status' => RequestStatus::Completed, 'created_at' => '2026-02-15 08:00:00']);

        $this->actingAs($staff)->get(route('request-records.index'))
            ->assertOk()
            ->assertSee('Mar. 5, 2026 2:30 PM')
            ->assertSee('Mar. 9, 2026 10:05 AM')
            ->assertSee('Feb. 3, 2026 4:45 PM')
            ->assertSee('status-completed', false)
            ->assertSee('status-rejected', false);
    }

    public function test_results_are_newest_first_and_paginated_with_filters_kept(): void
    {
        $staff = $this->user('st1', Role::Staff);
        $student = $this->user('s1', Role::Student);

        foreach (range(1, 16) as $i) {
            $this->makeRequest($student, ['status' => RequestStatus::Completed, 'created_at' => now()->subDays(40 - $i)]);
        }

        $response = $this->actingAs($staff)->get(route('request-records.index', ['status' => 'Completed']))->assertOk();

        $rows = $response->viewData('requests');
        $this->assertSame(16, $rows->total());
        $this->assertCount(15, $rows->items());
        $this->assertTrue($rows->first()->created_at->gte($rows->last()->created_at));

        $response->assertSee('page=2', false)->assertSee('status=Completed', false);
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
