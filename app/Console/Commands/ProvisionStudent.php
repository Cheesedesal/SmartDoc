<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProvisionStudent extends Command
{
    protected $signature = 'smartdoc:student:create';

    protected $description = 'Create an activated student account without changing existing accounts';

    public function handle(): int
    {
        $attributes = [
            'firstname' => trim($this->ask('First name') ?? ''),
            'surname' => trim($this->ask('Surname') ?? ''),
            'school_id' => trim($this->ask('School ID') ?? ''),
            'email' => trim($this->ask('Email address') ?? ''),
            'course' => trim($this->ask('Course (leave blank if not applicable)') ?? ''),
            'year' => trim($this->ask('Year level (leave blank if not applicable)') ?? ''),
            'section' => trim($this->ask('Section (leave blank if not applicable)') ?? ''),
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
                'school_id' => ['required', 'string', 'max:30', Rule::unique('users', 'school_id')],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'course' => ['nullable', 'string', 'max:100'],
                'year' => ['nullable', 'integer', 'min:1', 'max:99'],
                'section' => ['nullable', 'string', 'max:50'],
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
        $attributes['year'] = $attributes['year'] === '' ? null : (int) $attributes['year'];
        $attributes['course'] = $attributes['course'] === '' ? null : $attributes['course'];
        $attributes['section'] = $attributes['section'] === '' ? null : $attributes['section'];
        $attributes['password'] = $password;
        $attributes['role'] = Role::Student;
        $attributes['status'] = UserStatus::Activated;

        $student = User::create($attributes);

        $this->info("Student account {$student->school_id} created and activated.");

        return self::SUCCESS;
    }
}
