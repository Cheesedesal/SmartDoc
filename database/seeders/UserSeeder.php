<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Administrators are never created with built-in credentials. When no
     * activated administrator exists, offer to run the secure, interactive
     * smartdoc:admin:create command (which prompts for the password).
     */
    public function run(): void
    {
        $hasActiveAdmin = User::query()
            ->where('role', Role::Admin->value)
            ->where('status', UserStatus::Activated->value)
            ->exists();

        if ($hasActiveAdmin) {
            $this->command?->info('An activated administrator already exists; nothing to seed.');

            return;
        }

        if ($this->command === null) {
            return;
        }

        // confirm() returns its default (false) when running with --no-interaction.
        if ($this->command->confirm('No activated administrator found. Create one now?', false)) {
            $this->command->call('smartdoc:admin:create');

            return;
        }

        $this->command->warn(
            'No default administrator is seeded. Run php artisan smartdoc:admin:create to provision one securely.'
        );
    }
}
