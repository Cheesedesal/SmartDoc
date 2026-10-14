<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProvisionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_with_a_hashed_password_and_login_identifier(): void
    {
        $this->artisan('smartdoc:admin:create')
            ->expectsQuestion('First name', 'Taylor')
            ->expectsQuestion('Surname', 'Example')
            ->expectsQuestion('School ID', 'STAFF-100')
            ->expectsQuestion('Email address', 'taylor@example.test')
            ->expectsQuestion('Password', 'a-secure-test-password')
            ->expectsQuestion('Confirm password', 'a-secure-test-password')
            ->expectsOutputToContain('Administrator account STAFF-100 created.')
            ->assertExitCode(0);

        $admin = User::where('school_id', 'STAFF-100')->firstOrFail();

        $this->assertSame('Taylor Example', $admin->name);
        $this->assertSame('taylor@example.test', $admin->email);
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame(UserStatus::Activated, $admin->status);
        $this->assertTrue(Hash::check('a-secure-test-password', $admin->password));
    }

    public function test_it_does_not_change_an_existing_account_when_identifier_is_taken(): void
    {
        $existing = User::factory()->create([
            'name' => 'Existing Admin',
            'school_id' => 'EXISTING-1',
            'firstname' => 'Existing',
            'surname' => 'Admin',
            'email' => 'existing@example.test',
            'password' => 'existing-password',
            'role' => Role::Admin,
            'status' => UserStatus::Activated,
        ]);
        $originalPassword = $existing->password;

        $this->artisan('smartdoc:admin:create')
            ->expectsQuestion('First name', 'Changed')
            ->expectsQuestion('Surname', 'Account')
            ->expectsQuestion('School ID', 'EXISTING-1')
            ->expectsQuestion('Email address', 'changed@example.test')
            ->expectsQuestion('Password', 'another-secure-password')
            ->expectsQuestion('Confirm password', 'another-secure-password')
            ->expectsOutputToContain('school id has already been taken')
            ->assertExitCode(1);

        $this->assertSame($originalPassword, $existing->fresh()->password);
        $this->assertDatabaseMissing('users', ['email' => 'changed@example.test']);
    }
}
