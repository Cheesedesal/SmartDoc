<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_nothing_when_an_active_admin_exists(): void
    {
        $admin = User::factory()->create([
            'school_id' => 'EXISTING-1',
            'role' => Role::Admin,
            'status' => UserStatus::Activated,
            'password' => 'existing-password',
        ]);
        $originalHash = $admin->password;

        $this->artisan('db:seed')
            ->expectsOutputToContain('An activated administrator already exists')
            ->assertExitCode(0);

        $this->assertSame(1, User::count());
        $this->assertSame($originalHash, $admin->fresh()->password);
    }

    public function test_declining_creates_no_user_and_shows_the_provisioning_hint(): void
    {
        $this->artisan('db:seed')
            ->expectsConfirmation('No activated administrator found. Create one now?', 'no')
            ->expectsOutputToContain('smartdoc:admin:create')
            ->assertExitCode(0);

        $this->assertSame(0, User::count());
    }

    public function test_accepting_runs_the_secure_admin_command(): void
    {
        $this->artisan('db:seed')
            ->expectsConfirmation('No activated administrator found. Create one now?', 'yes')
            ->expectsQuestion('First name', 'Seed')
            ->expectsQuestion('Surname', 'Admin')
            ->expectsQuestion('School ID', 'SEED-ADMIN')
            ->expectsQuestion('Email address', 'seed.admin@example.test')
            ->expectsQuestion('Password', 'a-secure-test-password')
            ->expectsQuestion('Confirm password', 'a-secure-test-password')
            ->assertExitCode(0);

        $admin = User::where('school_id', 'SEED-ADMIN')->firstOrFail();

        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame(UserStatus::Activated, $admin->status);
        $this->assertTrue(Hash::check('a-secure-test-password', $admin->password));
        $this->assertSame(1, User::count());
    }
}
