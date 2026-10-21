<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\Office;
use App\Models\Section;
use App\Models\StudentYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileOptionsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_manage_and_archive_profile_options(): void
    {
        $admin = $this->user('admin', Role::Admin);
        $this->actingAs($admin)
            ->get(route('admin.profile-options.index', 'offices'))
            ->assertOk()
            ->assertSee('User management pages')
            ->assertSee('+ Add office');

        $this->post(route('admin.profile-options.store', 'offices'), [
            'code' => 'REG',
            'name' => 'Registrar Office',
            'status' => 'active',
        ])->assertRedirect();

        $office = Office::where('code', 'REG')->firstOrFail();
        $this->post(route('admin.profile-options.archive', ['offices', $office->id]))->assertRedirect();
        $this->get(route('admin.profile-options.index', ['offices', 'archived' => 1]))
            ->assertOk()
            ->assertSee('Registrar Office')
            ->assertSee('Restore');
        $this->post(route('admin.profile-options.restore', ['offices', $office->id]))->assertRedirect();
        $this->assertNull($office->fresh()->archived_at);
    }

    public function test_student_profile_selectors_require_matching_course_year_and_section(): void
    {
        $admin = $this->user('admin', Role::Admin);
        $courseA = Course::create(['code' => 'BSIT', 'name' => 'Information Technology', 'status' => 'active']);
        $courseB = Course::create(['code' => 'BSCS', 'name' => 'Computer Science', 'status' => 'active']);
        $yearA = StudentYear::create(['course_id' => $courseA->id, 'year_level' => 2, 'status' => 'active']);
        $yearB = StudentYear::create(['course_id' => $courseB->id, 'year_level' => 2, 'status' => 'active']);
        $sectionA = Section::create(['student_year_id' => $yearA->id, 'name' => 'A', 'status' => 'active']);
        $sectionB = Section::create(['student_year_id' => $yearB->id, 'name' => 'B', 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'firstname' => 'Student',
            'surname' => 'One',
            'school_id' => 'STU-001',
            'email' => 'student@example.test',
            'role' => 'student',
            'status' => 'activated',
            'course_id' => $courseA->id,
            'student_year_id' => $yearA->id,
            'section_id' => $sectionA->id,
            'password' => 'long-secure-password',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', [
            'school_id' => 'STU-001',
            'course_id' => $courseA->id,
            'student_year_id' => $yearA->id,
            'section_id' => $sectionA->id,
            'course' => 'Information Technology',
            'year' => 2,
            'section' => 'A',
        ]);

        $this->from(route('admin.users.index'))->post(route('admin.users.store'), [
            'firstname' => 'Student',
            'surname' => 'Two',
            'school_id' => 'STU-002',
            'email' => 'student-two@example.test',
            'role' => 'student',
            'status' => 'activated',
            'course_id' => $courseA->id,
            'student_year_id' => $yearB->id,
            'section_id' => $sectionB->id,
            'password' => 'long-secure-password',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasErrors('student_year_id');
        $this->assertDatabaseMissing('users', ['school_id' => 'STU-002']);
    }

    public function test_successful_login_is_recorded_in_activity_logs(): void
    {
        $student = $this->user('student', Role::Student);

        $this->post(route('login'), [
            'school_id' => $student->school_id,
            'password' => 'long-secure-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $student->id,
            'action' => 'login',
            'subject_type' => User::class,
            'subject_id' => $student->id,
            'subject_label' => $student->full_name,
        ]);
    }

    private function user(string $name, Role $role): User
    {
        return User::create([
            'name' => ucfirst($name).' Test',
            'firstname' => ucfirst($name),
            'surname' => 'Test',
            'school_id' => strtoupper($name).'-001',
            'email' => $name.'@example.test',
            'password' => Hash::make('long-secure-password'),
            'role' => $role,
            'status' => UserStatus::Activated,
        ]);
    }
}
