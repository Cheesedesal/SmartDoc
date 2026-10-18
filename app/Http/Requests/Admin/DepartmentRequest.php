<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') === true;
    }

    public function rules(): array
    {
        $id = $this->route('department')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Department code already exists.',
            'name.unique' => 'Department name already exists.',
        ];
    }
}
