<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Validates a new document request. Ownership comes from the session user, never
 * from the form; status, stage and payment status are never accepted from input.
 */
class StoreDocumentRequestRequest extends FormRequest
{
    private ?DocumentType $type = null;

    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Student) === true;
    }

    /** The chosen document type, only if it exists and is active. */
    public function documentType(): ?DocumentType
    {
        if ($this->type === null && is_numeric($this->input('document_type_id'))) {
            $this->type = DocumentType::query()
                ->with('requirements')
                ->where('is_active', true)
                ->find((int) $this->input('document_type_id'));
        }

        return $this->type;
    }

    public function rules(): array
    {
        $rules = [
            'document_type_id' => ['required', 'integer', Rule::exists('document_types', 'id')->where('is_active', true)],
            'purpose' => ['nullable', 'string', 'max:255'],
            'requester_remarks' => ['nullable', 'string', 'max:1000'],
        ];

        $type = $this->documentType();

        if ($type === null) {
            return $rules;
        }

        $fileRule = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('smartdoc.uploads.max_kb')];

        foreach ($type->requirements as $requirement) {
            $rules['requirements.'.$requirement->id] = $requirement->is_required
                ? ['required', ...$fileRule]
                : ['nullable', ...$fileRule];
        }

        if ((float) $type->fee > 0) {
            $rules += [
                'payment_method' => ['required', Rule::in(PaymentMethod::values())],
                'payment_reference' => [
                    'required', 'string', 'regex:/^[A-Za-z0-9\-]{8,40}$/',
                    Rule::unique('document_requests', 'payment_reference'),
                ],
                'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
                'payment_paid_on' => ['required', 'date', 'before_or_equal:today'],
                'payment_proof' => ['required', ...$fileRule],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'payment_reference.regex' => 'The reference number must be 8-40 letters, digits or dashes.',
            'payment_reference.unique' => 'This payment reference number has already been used.',
            'requirements.*.required' => 'Please upload this required document.',
            'requirements.*.mimes' => 'Uploads must be a PDF, JPG or PNG file.',
            'payment_proof.mimes' => 'The payment proof must be a PDF, JPG or PNG file.',
        ];
    }

    /**
     * Uploads keyed by requirement id, limited to requirements that belong to the
     * chosen document type (unknown keys in the request are ignored).
     *
     * @return array<int, UploadedFile>
     */
    public function requirementFiles(): array
    {
        $files = [];
        $type = $this->documentType();

        if ($type === null) {
            return $files;
        }

        foreach ($type->requirements as $requirement) {
            $file = $this->file('requirements.'.$requirement->id);

            if ($file instanceof UploadedFile) {
                $files[$requirement->id] = $file;
            }
        }

        return $files;
    }

    /** @return array<string, mixed> */
    public function requestData(): array
    {
        return $this->safe()->only([
            'purpose', 'requester_remarks',
            'payment_method', 'payment_reference', 'payment_amount', 'payment_paid_on',
        ]);
    }
}
