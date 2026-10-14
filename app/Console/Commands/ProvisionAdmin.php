<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ProvisionAdmin extends Command
{
    protected $signature = 'smartdoc:admin:create';

    protected $description = 'Create a new administrator without changing existing accounts';

    public function handle(): int
    {
        $attributes = [
            'firstname' => trim($this->ask('First name') ?? ''),
            'surname' => trim($this->ask('Surname') ?? ''),
            'school_id' => trim($this->ask('School ID') ?? ''),
            'email' => trim($this->ask('Email address') ?? ''),
        ];

        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');

        $validator = Validator::make(
            $attributes + [
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'firstname' => ['required', 'string', 'max:255'],
                'surname' => ['required', 'string', 'max:255'],
                'school_id' => ['required', 'string', 'max:30', 'unique:users,school_id'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12', 'confirmed'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $attributes['name'] = trim($attributes['firstname'].' '.$attributes['surname']);
        $attributes['password'] = $password;
        $attributes['role'] = Role::Admin;
        $attributes['status'] = UserStatus::Activated;

        $admin = User::create($attributes);

        $this->info("Administrator account {$admin->school_id} created.");

        return self::SUCCESS;
    }
}
