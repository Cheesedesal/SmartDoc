<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_student_dashboard_only_shows_their_own_request_data(): void
    {
        $student = $this->createUser(Role::Student, 'Student');
        $otherStudent = $this->createUser(Role::Student, 'Other');
        $documentType = DocumentType::create([
            'name' => 'Enrollment Verification',
            'description' => 'Verification letter',
            'processing_days' => 3,
            'fee' => 0,
            'is_active' => true,
        ]);

        DocumentRequest::create([
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'status' => 'Pending',
        ]);
        DocumentRequest::create([
            'user_id' => $otherStudent->id,
            'document_type_id' => $documentType->id,
            'status' => 'In Progress',
        ]);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Student dashboard')
            ->assertSee('Enrollment Verification')
            ->assertSee('REQ-001')
            ->assertDontSee('REQ-002')
            ->assertSee('My requests');
    }

    public function test_admin_dashboard_displays_role_specific_system_totals(): void
    {
        $admin = $this->createUser(Role::Admin, 'Admin');
        $this->createUser(Role::Student, 'Student');
        $this->createUser(Role::Staff, 'Staff');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Admin dashboard')
            ->assertSee('Total users')
            ->assertSee('Departments')
            ->assertSee('Document types');
    }

    public function test_staff_dashboard_renders_the_request_queue(): void
    {
        $staff = $this->createUser(Role::Staff, 'Staff');

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Staff dashboard')
            ->assertSee('Request queue');
    }

    private function createUser(Role $role, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'school_id' => strtolower($name).'-001',
            'firstname' => $name,
            'surname' => 'Test',
            'email' => strtolower($name).'@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => UserStatus::Activated,
        ]);
    }
}
