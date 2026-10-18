<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * For actions that take no input (confirm receipt, archive): the only job here is
 * to make sure the authenticated student owns the request.
 */
class OwnedRequestActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('documentRequest')) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
