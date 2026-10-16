<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_registration_form(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('REGISTER');
    }

    public function test_authenticated_user_is_redirected_from_the_registration_page(): void
    {
        $user = User::factory()->create([
            'role' => Role::Student,
            'status' => UserStatus::Activated,
        ]);

        $this->actingAs($user)
            ->get('/register')
            ->assertRedirect('/dashboard');
    }

    public function test_student_can_register_with_pending_approval_status(): void
    {
        $response = $this->post('/register', [
            'firstname' => 'Maria',
            'surname' => 'Dela Cruz',
            'school_id' => 'STU-1001',
            'email' => 'maria@example.test',
            'password' => 'a-very-secure-password',
            'password_confirmation' => 'a-very-secure-password',
        ]);

        $response->assertRedirect('/login');

        $user = User::where('school_id', 'STU-1001')->firstOrFail();

        $this->assertSame('Maria Dela Cruz', $user->name);
        $this->assertSame(Role::Student, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertTrue(Hash::check('a-very-secure-password', $user->password));
    }

    public function test_required_fields_are_validated(): void
    {
        $this->from('/register')
            ->post('/register', [
                'firstname' => '',
                'surname' => '',
                'school_id' => '',
                'email' => '',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasErrors(['firstname', 'surname', 'school_id', 'email', 'password']);
    }

    public function test_duplicate_school_id_and_email_are_rejected(): void
    {
        User::factory()->create([
            'school_id' => 'STU-1002',
            'email' => 'existing@example.test',
            'role' => Role::Student,
            'status' => UserStatus::Activated,
        ]);

        $this->from('/register')
            ->post('/register', [
                'firstname' => 'Jane',
                'surname' => 'Doe',
                'school_id' => 'STU-1002',
                'email' => 'new@example.test',
                'password' => 'a-very-secure-password',
                'password_confirmation' => 'a-very-secure-password',
            ])
            ->assertSessionHasErrors(['school_id']);

        $this->from('/register')
            ->post('/register', [
                'firstname' => 'Jane',
                'surname' => 'Doe',
                'school_id' => 'STU-1003',
                'email' => 'existing@example.test',
                'password' => 'a-very-secure-password',
                'password_confirmation' => 'a-very-secure-password',
            ])
            ->assertSessionHasErrors(['email']);
    }

    public function test_public_registration_never_accepts_admin_role_or_activated_status(): void
    {
        $this->post('/register', [
            'firstname' => 'Admin',
            'surname' => 'Tryer',
            'school_id' => 'STU-1004',
            'email' => 'admin-tryer@example.test',
            'password' => 'a-very-secure-password',
            'password_confirmation' => 'a-very-secure-password',
            'role' => 'admin',
            'status' => 'activated',
        ])->assertRedirect('/login');

        $user = User::where('school_id', 'STU-1004')->firstOrFail();

        $this->assertSame(Role::Student, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }

    public function test_pending_students_cannot_log_in_until_approved(): void
    {
        User::factory()->create([
            'school_id' => 'STU-1005',
            'firstname' => 'Pending',
            'surname' => 'Student',
            'name' => 'Pending Student',
            'email' => 'pending@example.test',
            'password' => 'a-very-secure-password',
            'role' => Role::Student,
            'status' => UserStatus::Pending,
        ]);

        $this->from('/login')
            ->post('/login', [
                'school_id' => 'STU-1005',
                'password' => 'a-very-secure-password',
            ])
            ->assertSessionHasErrors(['school_id'])
            ->assertSessionHas('errors');
    }
}
