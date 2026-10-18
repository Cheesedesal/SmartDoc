<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Account settings for the signed-in user (route group: `auth`, every role). Port of the legacy
 * accountsettings page: read-only identity details, an editable profile, and a password change.
 *
 * It only ever touches the authenticated user's own row (`$request->user()`); there is no user id
 * in any URL or form field. It does not touch request status or lifecycle.
 */
class AccountSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.settings', [
            'user' => $request->user()->load('department'),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['firstname'] = trim($data['firstname']);
        $data['surname'] = trim((string) ($data['surname'] ?? ''));
        // users.name is NOT NULL in the base migration; keep it in step with the real name.
        $data['name'] = trim($data['firstname'].' '.$data['surname']);

        $request->user()->update($data);

        return redirect()->route('account.edit')->with('status', 'Your profile has been updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->password = $request->validated('password'); // hashed by the model cast
        // A new remember-me token signs the account out of "remember me" sessions on other devices.
        $user->setRememberToken(Str::random(60));
        $user->save();

        return redirect()->route('account.edit')->with('status', 'Your password has been changed.');
    }
}
