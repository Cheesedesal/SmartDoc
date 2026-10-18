<?php

namespace App\Http\Requests\Admin;

use App\Enums\AnnouncementStatus;
use App\Enums\Audience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') === true;
    }

    public function rules(): array
    {
        $id = $this->route('announcement')?->id;

        // A published announcement that already expired would be hidden at once.
        $expiry = ['nullable', 'date'];
        if ($this->input('status') === AnnouncementStatus::Published->value) {
            $expiry[] = 'after_or_equal:today';
        }

        return [
            'title' => ['required', 'string', 'max:200', Rule::unique('announcements', 'title')->ignore($id)],
            'message' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::enum(Audience::class)],
            'status' => ['required', Rule::enum(AnnouncementStatus::class)],
            'expires_at' => $expiry,
        ];
    }

    public function messages(): array
    {
        return [
            'title.unique' => 'Title already exists.',
            'expires_at.after_or_equal' => 'A published announcement cannot already be expired.',
        ];
    }
}
