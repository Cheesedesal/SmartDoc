<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') === true;
    }

    public function rules(): array
    {
        // Create: {documentType}; update: {requirement} (which knows its own type).
        $requirement = $this->route('requirement');
        $typeId = $requirement?->document_type_id ?? $this->route('documentType')?->id;

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('document_requirements', 'name')
                    ->where('document_type_id', $typeId)
                    ->ignore($requirement?->id),
            ],
            'description' => ['required', 'string', 'max:150'],
            'is_required' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Requirement already exists for this document.'];
    }
}
