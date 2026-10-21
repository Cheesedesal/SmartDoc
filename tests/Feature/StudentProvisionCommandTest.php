<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentProvisionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_activated_student_with_a_hashed_password(): void
    {
        $this->artisan('smartdoc:student:create')
            ->expectsQuestion('First name', 'Taylor')
            ->expectsQuestion('Surname', 'Student')
            ->expectsQuestion('School ID', 'STU-100')
            ->expectsQuestion('Email address', 'taylor.student@example.test')
            ->expectsQuestion('Course (leave blank if not applicable)', 'Computer Science')
            ->expectsQuestion('Year level (leave blank if not applicable)', '2')
            ->expectsQuestion('Section (leave blank if not applicable)', 'A')
            ->expectsQuestion('Password', 'a-secure-test-password')
            ->expectsQuestion('Confirm password', 'a-secure-test-password')
            ->expectsOutputToContain('Student account STU-100 created and activated.')
            ->assertExitCode(0);

        $student = User::where('school_id', 'STU-100')->firstOrFail();

        $this->assertSame('Taylor Student', $student->name);
        $this->assertSame('Computer Science', $student->course);
        $this->assertSame(2, $student->year);
        $this->assertSame('A', $student->section);
        $this->assertSame(Role::Student, $student->role);
        $this->assertSame(UserStatus::Activated, $student->status);
        $this->assertTrue(Hash::check('a-secure-test-password', $student->password));
    }

    public function test_it_does_not_change_an_existing_account_when_school_id_is_taken(): void
    {
        $existing = User::factory()->create([
            'name' => 'Existing Account',
            'school_id' => 'STU-100',
            'firstname' => 'Existing',
            'surname' => 'Account',
            'email' => 'existing.student@example.test',
            'password' => 'existing-password',
            'role' => Role::Student,
            'status' => UserStatus::Activated,
        ]);
        $originalPassword = $existing->password;

        $this->artisan('smartdoc:student:create')
            ->expectsQuestion('First name', 'Changed')
            ->expectsQuestion('Surname', 'Account')
            ->expectsQuestion('School ID', 'STU-100')
            ->expectsQuestion('Email address', 'changed.student@example.test')
            ->expectsQuestion('Course (leave blank if not applicable)', '')
            ->expectsQuestion('Year level (leave blank if not applicable)', '')
            ->expectsQuestion('Section (leave blank if not applicable)', '')
            ->expectsQuestion('Password', 'another-secure-password')
            ->expectsQuestion('Confirm password', 'another-secure-password')
            ->expectsOutputToContain('school id has already been taken')
            ->assertExitCode(1);

        $this->assertSame($originalPassword, $existing->fresh()->password);
        $this->assertDatabaseMissing('users', ['email' => 'changed.student@example.test']);
    }
}
