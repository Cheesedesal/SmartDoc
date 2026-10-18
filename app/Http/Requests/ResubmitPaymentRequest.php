<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResubmitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('documentRequest')) === true;
    }

    public function rules(): array
    {
        $current = $this->route('documentRequest');

        return [
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'payment_reference' => [
                'required', 'string', 'regex:/^[A-Za-z0-9\-]{8,40}$/',
                Rule::unique('document_requests', 'payment_reference')->ignore($current->id),
            ],
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('smartdoc.uploads.max_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_reference.regex' => 'The reference number must be 8-40 letters, digits or dashes.',
            'payment_reference.unique' => 'This payment reference number has already been used.',
            'payment_proof.mimes' => 'The payment proof must be a PDF, JPG or PNG file.',
        ];
    }
}
