<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountSettingsController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user()->load(['department', 'office', 'positionRecord', 'courseRecord', 'yearRecord', 'sectionRecord']);

        return view('account.settings', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => [
                Rule::requiredIf($request->input('email') !== $user->email || $request->filled('password')),
                'current_password',
            ],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);

        $user->email = $data['email'];
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('local')->delete($user->profile_photo_path);
            }
            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos/'.$user->id, 'local');
        }
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('status', 'Account settings updated.');
    }

    public function avatar(Request $request): StreamedResponse
    {
        $path = $request->user()->profile_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
