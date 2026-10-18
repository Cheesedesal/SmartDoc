<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Self-service profile update (Account settings). Only the keys validated here can reach the
 * model: school ID, email, role, status and department are never accepted from this form, so a
 * hand-crafted request cannot change them.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $user = $this->user();

        $rules = [
            'firstname' => ['required', 'string', 'max:100'],
            // A few accounts (the seeded administrator) have no surname on record, so it is only
            // required once one exists; otherwise they could never save this form.
            'surname' => [Rule::requiredIf(filled($user->surname)), 'nullable', 'string', 'max:100'],
        ];

        // Academic details belong to students only; for staff and admins these keys are not
        // validated, so they are dropped from validated() and never stored.
        if ($user->role === Role::Student) {
            $rules += [
                'course' => ['nullable', 'string', 'max:100'],
                'year' => ['nullable', 'integer', 'between:1,10'],
                'section' => ['nullable', 'string', 'max:50'],
            ];
        }

        return $rules;
    }
}
