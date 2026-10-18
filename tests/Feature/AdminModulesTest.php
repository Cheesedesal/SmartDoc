<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_only_admins_reach_admin_pages(): void
    {
        $routes = ['admin.departments.index', 'admin.users.index', 'admin.document-types.index', 'admin.announcements.index'];

        // Guests first (before any actingAs), then each role, with users created once.
        foreach ($routes as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $student = $this->user('stu', Role::Student);
        $staff = $this->user('stf', Role::Staff);
        $admin = $this->user('adm', Role::Admin);

        foreach ($routes as $route) {
            $this->actingAs($student)->get(route($route))->assertForbidden();
            $this->actingAs($staff)->get(route($route))->assertForbidden();
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_non_admin_cannot_post_changes(): void
    {
        $staff = $this->user('stf', Role::Staff);

        $this->actingAs($staff)->post(route('admin.departments.store'), ['code' => 'X', 'name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->assertSame(0, Department::count());
    }

    public function test_department_crud_and_duplicate_rules(): void
    {
        $admin = $this->user('adm', Role::Admin);

        $this->actingAs($admin)->post(route('admin.departments.store'), ['code' => 'CS', 'name' => 'Computer Studies', 'status' => 'active'])
            ->assertRedirect(route('admin.departments.index'));
        $dept = Department::firstOrFail();

        $this->actingAs($admin)->post(route('admin.departments.store'), ['code' => 'CS', 'name' => 'Other', 'status' => 'active'])
            ->assertSessionHasErrors('code');
        $this->actingAs($admin)->post(route('admin.departments.store'), ['code' => 'XX', 'name' => 'Computer Studies', 'status' => 'active'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->put(route('admin.departments.update', $dept), ['code' => 'CS', 'name' => 'Computing', 'status' => 'inactive'])
            ->assertRedirect();
        $this->assertSame('Computing', $dept->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.departments.destroy', $dept))->assertRedirect();
        $this->assertSame(0, Department::count());
    }

    public function test_department_with_users_cannot_be_deleted(): void
    {
        $admin = $this->user('adm', Role::Admin);
        $dept = Department::create(['code' => 'CS', 'name' => 'Computing', 'status' => 'active']);
        $student = $this->user('stu', Role::Student);
        $student->forceFill(['department_id' => $dept->id])->save();

        $this->actingAs($admin)->delete(route('admin.departments.destroy', $dept))->assertSessionHasErrors('request');

        $this->assertSame(1, Department::count());
        $this->assertSame($dept->id, $student->fresh()->department_id);
    }

    public function test_admin_can_create_user_who_can_log_in_with_hashed_password(): void
    {
        $admin = $this->user('adm', Role::Admin);
        $dept = Department::create(['code' => 'CS', 'name' => 'Computing', 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'school_id' => '2026-0001', 'firstname' => 'Ana', 'surname' => 'Cruz', 'email' => 'ana@example.test',
            'department_id' => $dept->id, 'role' => 'student', 'status' => 'activated', 'password' => 'Secret-pass-1',
        ])->assertRedirect(route('admin.users.index'));

        $created = User::where('school_id', '2026-0001')->firstOrFail();
        $this->assertNotSame('Secret-pass-1', $created->password);
        $this->assertSame('Ana Cruz', $created->name);

        auth()->logout();
        $this->post(route('login'), ['school_id' => '2026-0001', 'password' => 'Secret-pass-1'])->assertRedirect(route('dashboard'));
    }

    public function test_user_validation_rejects_weak_password_duplicate_email_and_inactive_department(): void
    {
        $admin = $this->user('adm', Role::Admin);
        $inactive = Department::create(['code' => 'OLD', 'name' => 'Old', 'status' => 'inactive']);
        $base = ['school_id' => 'n1', 'firstname' => 'A', 'surname' => 'B', 'email' => 'adm@example.test', 'role' => 'student', 'status' => 'activated', 'password' => 'short'];

        $this->actingAs($admin)->post(route('admin.users.store'), $base + ['department_id' => $inactive->id])
            ->assertSessionHasErrors(['password', 'email', 'department_id']);
        $this->assertSame(1, User::count());
    }

    public function test_admin_cannot_demote_deactivate_or_delete_self_or_last_admin(): void
    {
        $admin = $this->user('adm', Role::Admin);
        $dept = Department::create(['code' => 'CS', 'name' => 'Computing', 'status' => 'active']);

        $payload = ['school_id' => $admin->school_id, 'firstname' => 'adm', 'surname' => 'Test', 'role' => 'staff', 'status' => 'activated', 'department_id' => $dept->id];
        $this->actingAs($admin)->put(route('admin.users.update', $admin), $payload)->assertSessionHasErrors('request');
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHasErrors('request');

        $this->assertSame(Role::Admin, $admin->fresh()->role);
        $this->assertSame(1, User::count());
    }

    public function test_user_with_requests_cannot_be_deleted(): void
    {
        $admin = $this->user('adm', Role::Admin);
        $student = $this->user('stu', Role::Student);
        $type = DocumentType::create(['name' => 'Cert', 'description' => 'd', 'processing_days' => 1, 'fee' => 0, 'is_active' => true]);
        $request = new DocumentRequest();
        $request->forceFill(['user_id' => $student->id, 'document_type_id' => $type->id, 'status' => 'Pending'])->save();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $student))->assertSessionHasErrors('request');
        $this->assertNotNull($student->fresh());
    }

    public function test_document_type_and_requirement_management(): void
    {
        $admin = $this->user('adm', Role::Admin);

        $this->actingAs($admin)->post(route('admin.document-types.store'), [
            'name' => 'Transcript', 'description' => 'Official transcript', 'processing_days' => 5, 'fee' => '', 'is_active' => 1,
        ])->assertRedirect(route('admin.document-types.index'));
        $type = DocumentType::firstOrFail();
        $this->assertSame('0.00', (string) $type->fee);

        $this->actingAs($admin)->post(route('admin.document-types.store'), [
            'name' => 'Transcript', 'description' => 'x', 'processing_days' => 5, 'fee' => 1, 'is_active' => 1,
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('admin.document-types.requirements.store', $type), ['name' => 'Valid ID', 'description' => 'Any government ID', 'is_required' => 1])
            ->assertRedirect(route('admin.document-types.configure', $type));
        $this->actingAs($admin)->post(route('admin.document-types.requirements.store', $type), ['name' => 'Valid ID', 'description' => 'dup', 'is_required' => 1])
            ->assertSessionHasErrors('name');

        $requirement = DocumentRequirement::firstOrFail();
        $this->actingAs($admin)->put(route('admin.requirements.update', $requirement), ['name' => 'Valid ID', 'description' => 'Updated', 'is_required' => 0])->assertRedirect();
        $this->assertFalse($requirement->fresh()->is_required);

        $this->actingAs($admin)->get(route('admin.document-types.configure', $type))->assertOk()->assertSee('Valid ID');

        $this->actingAs($admin)->delete(route('admin.requirements.destroy', $requirement))->assertRedirect();
        $this->assertSame(0, DocumentRequirement::count());
    }

    public function test_document_type_with_requests_cannot_be_deleted(): void
    {
        $admin = $this->user('adm', Role::Admin);
        $student = $this->user('stu', Role::Student);
        $type = DocumentType::create(['name' => 'Cert', 'description' => 'd', 'processing_days' => 1, 'fee' => 0, 'is_active' => true]);
        $request = new DocumentRequest();
        $request->forceFill(['user_id' => $student->id, 'document_type_id' => $type->id, 'status' => 'Pending'])->save();

        $this->actingAs($admin)->delete(route('admin.document-types.destroy', $type))->assertSessionHasErrors('request');
        $this->assertSame(1, DocumentType::count());
    }

    public function test_announcement_lifecycle_visibility_and_expiry(): void
    {
        $admin = $this->user('adm', Role::Admin);
        $student = $this->user('stu', Role::Student);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Enrollment', 'message' => 'Enroll now', 'audience' => 'student', 'status' => 'Published', 'expires_at' => now()->addDays(3)->toDateString(),
        ])->assertRedirect(route('admin.announcements.index'));

        $announcement = Announcement::firstOrFail();
        $this->assertNotNull($announcement->published_at);
        $this->assertSame($admin->id, $announcement->created_by);

        $this->actingAs($student)->get(route('announcements.index'))->assertOk()->assertSee('Enrollment');

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Enrollment', 'message' => 'dup', 'audience' => 'all', 'status' => 'Draft',
        ])->assertSessionHasErrors('title');

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Old news', 'message' => 'x', 'audience' => 'all', 'status' => 'Published', 'expires_at' => now()->subDay()->toDateString(),
        ])->assertSessionHasErrors('expires_at');

        // Overdue published announcements are flagged Expired when the admin list is opened.
        $announcement->forceFill(['expires_at' => now()->subDays(2)])->save();
        $this->actingAs($admin)->get(route('admin.announcements.index'))->assertOk();
        $this->assertSame('Expired', $announcement->fresh()->status->value);
        $this->actingAs($student)->get(route('announcements.index'))->assertOk()->assertDontSee('Enrollment');

        $this->actingAs($admin)->delete(route('admin.announcements.destroy', $announcement))->assertRedirect();
        $this->assertSame(0, Announcement::count());
    }

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
}
