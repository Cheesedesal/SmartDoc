<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn(
            'No default administrator is seeded. Run php artisan smartdoc:admin:create to provision one securely.'
        );
    }
}
