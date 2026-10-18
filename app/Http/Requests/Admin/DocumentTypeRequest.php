<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['fee' => $this->input('fee') === null || $this->input('fee') === '' ? 0 : $this->input('fee')]);
    }

    public function rules(): array
    {
        $id = $this->route('documentType')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('document_types', 'name')->ignore($id)],
            'description' => ['required', 'string', 'max:1000'],
            'processing_days' => ['required', 'integer', 'min:1', 'max:365'],
            'fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Document name already exists.'];
    }
}
