<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'school_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Same behaviour as legacy login.php: deactivated accounts get their own message,
        // but only after the password has been verified (no account enumeration).
        $user = User::where('school_id', $credentials['school_id'])->first();

        if ($user && $user->status === UserStatus::Deactivated
            && Hash::check($credentials['password'], $user->password)) {
            return back()->withInput($request->only('school_id'))->withErrors([
                'school_id' => 'Your account has not been activated by the admin. Please contact the admin.',
            ]);
        }

        if (Auth::attempt([
            'school_id' => $credentials['school_id'],
            'password' => $credentials['password'],
            'status' => UserStatus::Activated->value,
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withInput($request->only('school_id'))->withErrors([
            'school_id' => 'Invalid ID or password.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
