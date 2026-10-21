<?php

namespace Tests\Feature;

use App\Enums\AnnouncementStatus;
use App\Enums\Audience;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Office;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_create_and_update_a_department_from_the_management_page(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');

        $this->actingAs($admin)
            ->get(route('admin.departments.index'))
            ->assertOk()
            ->assertSee('data-modal-open', false);

        $this->post(route('admin.departments.store'), [
            'code' => 'REG',
            'name' => 'Registrar',
            'status' => 'active',
        ])->assertRedirect();
        $department = Department::firstOrFail();
        $this->get(route('admin.departments.index'))->assertSee('hover-card', false);
        $this->put(route('admin.departments.update', $department), [
            'code' => 'REG2',
            'name' => 'Registrar Office',
            'status' => 'inactive',
        ])->assertRedirect();
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'code' => 'REG2', 'status' => 'inactive']);
    }

    public function test_admin_can_create_a_staff_account_with_a_password_hash(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');
        $department = Department::create([
            'code' => 'REG',
            'name' => 'Registrar',
            'status' => 'active',
        ]);
        $office = Office::create(['code' => 'REG', 'name' => 'Registrar Office', 'status' => 'active']);
        $position = Position::create(['name' => 'Registrar Staff', 'role' => 'staff', 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'firstname' => 'Morgan',
            'surname' => 'Staff',
            'school_id' => 'STAFF-2026',
            'email' => 'morgan.staff@example.test',
            'department_id' => $department->id,
            'office_id' => $office->id,
            'position_id' => $position->id,
            'role' => Role::Staff->value,
            'status' => UserStatus::Activated->value,
            'password' => 'long-secure-password',
        ])->assertRedirect();

        $staff = User::where('school_id', 'STAFF-2026')->firstOrFail();
        $this->assertSame(Role::Staff, $staff->role);
        $this->assertTrue(Hash::check('long-secure-password', $staff->password));
        $this->assertNotSame('long-secure-password', $staff->password);
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Morgan Staff')
            ->assertSee('data-modal-open', false);
    }

    public function test_admin_can_manage_document_types_and_requirements(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');
        $this->actingAs($admin)
            ->post(route('admin.document-types.store'), [
                'name' => 'Enrollment Verification',
                'description' => 'Certificate of enrollment',
                'processing_days' => 4,
                'fee' => '25.00',
                'is_active' => '1',
            ])
            ->assertRedirect();
        $documentType = DocumentType::firstOrFail();

        $this->post(route('admin.document-types.requirements.store', $documentType), [
            'name' => 'Request Form',
            'description' => 'Completed request form',
            'is_required' => '1',
        ])->assertRedirect();
        $requirement = DocumentRequirement::firstOrFail();
        $this->put(route('admin.document-types.requirements.update', [$documentType, $requirement]), [
            'name' => 'Signed Request Form',
            'description' => 'Signed by the student',
            'is_required' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('document_requirements', [
            'id' => $requirement->id,
            'name' => 'Signed Request Form',
            'is_required' => false,
        ]);
        $this->get(route('admin.document-types.index'))
            ->assertOk()
            ->assertSee('Enrollment Verification')
            ->assertSee('Signed Request Form')
            ->assertSee('hover-card', false);
    }

    public function test_admin_can_publish_announcements_to_audience_and_users_only_see_their_audience(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');
        $student = $this->createUser(Role::Student, 'student');
        $staff = $this->createUser(Role::Staff, 'staff');

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Student registration',
            'message' => 'Registration is open.',
            'audience' => Audience::Student->value,
            'status' => AnnouncementStatus::Published->value,
            'expires_at' => now()->addWeek()->toDateString(),
        ])->assertRedirect();
        $this->post(route('admin.announcements.store'), [
            'title' => 'Staff meeting',
            'message' => 'Staff meeting at 3 PM.',
            'audience' => Audience::Staff->value,
            'status' => AnnouncementStatus::Published->value,
        ])->assertRedirect();

        $this->actingAs($student)->get(route('announcements.index'))
            ->assertOk()->assertSee('Student registration')->assertDontSee('Staff meeting');
        $this->actingAs($staff)->get(route('announcements.index'))
            ->assertOk()->assertSee('Staff meeting')->assertDontSee('Student registration');
        $this->actingAs($admin)->get(route('admin.announcements.index'))
            ->assertOk()->assertSee('data-modal-open', false)->assertSee('hover-card', false);
    }

    public function test_staff_cannot_create_admin_announcements(): void
    {
        $staff = $this->createUser(Role::Staff, 'staff');

        $this->actingAs($staff)
            ->get(route('admin.announcements.index'))
            ->assertForbidden();
    }

    public function test_admin_archives_an_account_without_removing_its_audit_history(): void
    {
        $admin = $this->createUser(Role::Admin, 'admin');
        $student = $this->createUser(Role::Student, 'student');
        ActivityLog::create([
            'actor_id' => $student->id,
            'action' => 'updated',
            'subject_type' => User::class,
            'subject_id' => $student->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.archive', $student))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $student->id]);
        $this->assertNotNull($student->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $student->id, 'subject_id' => $student->id]);
    }

    private function createUser(Role $role, string $name): User
    {
        return User::factory()->create([
            'name' => ucfirst($name),
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
