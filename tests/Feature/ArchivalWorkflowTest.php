<?php

namespace Tests\Feature;

use App\Enums\AnnouncementStatus;
use App\Enums\Audience;
use App\Enums\ChatbotFaqStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\ChatbotFaq;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArchivalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_deactivated_accounts_are_archived_after_three_years_and_not_before(): void
    {
        $old = $this->createUser('old-inactive', UserStatus::Deactivated);
        $old->forceFill(['inactive_since' => now()->subYears(3)->subDay()])->save();
        $recent = $this->createUser('recent-inactive', UserStatus::Deactivated);
        $recent->forceFill(['inactive_since' => now()->subYears(3)->addDay()])->save();
        $active = $this->createUser('active-account', UserStatus::Activated);

        $this->artisan('smartdoc:archive-inactive-users')->assertSuccessful();

        $this->assertNotNull($old->fresh()->archived_at);
        $this->assertNull($recent->fresh()->archived_at);
        $this->assertNull($active->fresh()->archived_at);
    }

    public function test_deactivated_account_with_an_open_request_is_not_auto_archived(): void
    {
        $student = $this->createUser('inactive-student', UserStatus::Deactivated);
        $student->forceFill(['inactive_since' => now()->subYears(4)])->save();
        $documentType = DocumentType::create([
            'name' => 'Transcript',
            'description' => 'Official transcript',
            'processing_days' => 5,
            'fee' => 0,
            'is_active' => false,
        ]);
        DocumentRequest::create([
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'status' => 'Pending',
            'current_stage' => 'Request Received',
        ]);

        $this->artisan('smartdoc:archive-inactive-users')->assertSuccessful();

        $this->assertNull($student->fresh()->archived_at);
    }

    public function test_admin_can_bulk_archive_and_restore_records_without_deleting_them(): void
    {
        $admin = $this->createUser('admin', UserStatus::Activated, Role::Admin);
        $departmentA = Department::create(['code' => 'A', 'name' => 'Office A', 'status' => 'active']);
        $departmentB = Department::create(['code' => 'B', 'name' => 'Office B', 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('admin.departments.bulk-archive'), ['ids' => [$departmentA->id, $departmentB->id]])
            ->assertRedirect();
        $this->assertNotNull($departmentA->fresh()->archived_at);
        $this->assertNotNull($departmentB->fresh()->archived_at);
        $this->assertDatabaseCount('departments', 2);
        $this->get(route('admin.departments.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('Office A');

        $this->post(route('admin.departments.bulk-restore'), ['ids' => [$departmentA->id, $departmentB->id]])
            ->assertRedirect();
        $this->assertNull($departmentA->fresh()->archived_at);
        $this->assertNull($departmentB->fresh()->archived_at);
    }

    public function test_archived_user_cannot_log_in_and_can_be_restored_by_admin(): void
    {
        $admin = $this->createUser('admin', UserStatus::Activated, Role::Admin);
        $student = $this->createUser('student', UserStatus::Activated);
        $student->archive();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('student');

        $this->post(route('logout'));
        $this->post(route('login'), [
            'school_id' => $student->school_id,
            'password' => 'secure-test-password',
        ])->assertSessionHasErrors('school_id');

        $this->actingAs($admin)
            ->post(route('admin.users.restore', $student))
            ->assertRedirect();
        $this->assertNull($student->fresh()->archived_at);
    }

    public function test_archived_announcements_document_types_requirements_and_faqs_are_restorable(): void
    {
        $admin = $this->createUser('admin', UserStatus::Activated, Role::Admin);
        $announcement = Announcement::create([
            'title' => 'Office closed',
            'message' => 'The office is closed today.',
            'audience' => Audience::All,
            'status' => AnnouncementStatus::Published,
            'created_by' => $admin->id,
        ]);
        $documentType = DocumentType::create([
            'name' => 'Transcript',
            'description' => 'Official transcript',
            'processing_days' => 5,
            'fee' => 0,
            'is_active' => true,
        ]);
        $requirement = DocumentRequirement::create([
            'document_type_id' => $documentType->id,
            'name' => 'School ID',
            'description' => 'Current student ID',
            'is_required' => true,
        ]);
        $faq = ChatbotFaq::create([
            'intent' => 'office_hours',
            'category' => 'Office',
            'canonical_question' => 'What are the office hours?',
            'question_examples' => [],
            'answer' => 'The office is open on weekdays.',
            'locale' => 'en',
            'status' => ChatbotFaqStatus::Approved,
            'is_active' => true,
            'is_suggested' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.announcements.archive', $announcement))->assertRedirect();
        $this->post(route('admin.document-types.archive', $documentType))->assertRedirect();
        $this->post(route('admin.document-types.requirements.archive', [$documentType, $requirement]))->assertRedirect();
        $this->post(route('admin.chatbot-faqs.archive', $faq))->assertRedirect();

        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
        $this->assertDatabaseHas('document_types', ['id' => $documentType->id]);
        $this->assertDatabaseHas('document_requirements', ['id' => $requirement->id]);
        $this->assertDatabaseHas('chatbot_faqs', ['id' => $faq->id]);
        $this->assertDatabaseCount('announcements', 1);
        $this->assertDatabaseCount('document_types', 1);
        $this->assertDatabaseCount('document_requirements', 1);
        $this->assertDatabaseCount('chatbot_faqs', 1);

        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
        $this->assertDatabaseHas('document_types', ['id' => $documentType->id, 'is_active' => false]);
        $this->assertSame(0, \App\Models\ChatbotFaq::available()->count());

        $this->get(route('admin.announcements.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('Office closed');
        $this->get(route('admin.document-types.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('Transcript');
        $this->get(route('admin.document-types.index', ['archived_requirements' => 1]))
            ->assertOk()
            ->assertSee('School ID');
        $this->get(route('admin.chatbot-faqs.index', ['archived' => 1]))
            ->assertOk()
            ->assertSee('office_hours');

        $this->post(route('admin.announcements.restore', $announcement))->assertRedirect();
        $this->post(route('admin.document-types.restore', $documentType))->assertRedirect();
        $this->post(route('admin.document-types.requirements.restore', [$documentType, $requirement]))->assertRedirect();
        $this->post(route('admin.chatbot-faqs.restore', $faq))->assertRedirect();

        $this->assertNull($announcement->fresh()->archived_at);
        $this->assertNull($documentType->fresh()->archived_at);
        $this->assertNull($requirement->fresh()->archived_at);
        $this->assertNull($faq->fresh()->archived_at);
        $this->assertFalse($documentType->fresh()->is_active);
        $this->assertFalse($faq->fresh()->is_active);
    }

    public function test_account_settings_show_read_only_role_details_and_photo_picker_on_avatar(): void
    {
        $student = $this->createUser('student', UserStatus::Activated);
        $student->forceFill([
            'course' => 'Computer Studies',
            'year' => 3,
            'section' => 'A',
        ])->save();

        $this->actingAs($student)
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Computer Studies')
            ->assertSee('profile-photo-input')
            ->assertSee('data-profile-preview-input')
            ->assertDontSee('name="firstname"', false)
            ->assertDontSee('name="course"', false);
    }

    private function createUser(
        string $prefix,
        UserStatus $status,
        Role $role = Role::Student
    ): User {
        return User::factory()->create([
            'name' => ucfirst($prefix),
            'firstname' => ucfirst($prefix),
            'surname' => 'Test',
            'school_id' => strtoupper($prefix).'-001',
            'email' => $prefix.'@example.test',
            'password' => Hash::make('secure-test-password'),
            'role' => $role,
            'status' => $status,
        ]);
    }
}
