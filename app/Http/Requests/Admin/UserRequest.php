<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') === true;
    }

    public function rules(): array
    {
        $editing = $this->route('user');
        $id = $editing?->id;

        return [
            'school_id' => ['required', 'string', 'max:30', Rule::unique('users', 'school_id')->ignore($id)],
            'firstname' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            // Email is fixed after creation (as in the reference app).
            'email' => $editing
                ? ['prohibited']
                : ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'department_id' => [
                'nullable', 'required_unless:role,admin', 'integer',
                Rule::exists('departments', 'id')->where('status', 'active'),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            // The reference app assigned the shared plaintext password "Password01"; the admin now sets one.
            'password' => $editing
                ? ['nullable', 'string', Password::min(8)]
                : ['required', 'string', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.unique' => 'That school ID is already in use.',
            'email.unique' => 'Email already exists.',
            'email.prohibited' => 'The email address cannot be changed here.',
            'department_id.required_unless' => 'Choose a department for staff and students.',
        ];
    }
}
