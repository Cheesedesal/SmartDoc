<?php

namespace App\Http\Controllers;

use App\Enums\Role;
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

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'school_id' => ['required', 'string', 'max:30', 'unique:users,school_id'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $firstName = trim($validated['firstname']);
        $surname = trim($validated['surname']);

        User::create([
            'name' => trim($firstName.' '.$surname),
            'firstname' => $firstName,
            'surname' => $surname,
            'school_id' => trim($validated['school_id']),
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'role' => Role::Student,
            'status' => UserStatus::Pending,
        ]);

        return redirect()->route('login')->with('status', 'Registration submitted. Your student account is pending administrator approval.');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'school_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('school_id', $credentials['school_id'])->first();

        if ($user && $user->status === UserStatus::Pending
            && Hash::check($credentials['password'], $user->password)) {
            return back()->withInput($request->only('school_id'))->withErrors([
                'school_id' => 'Your account is pending administrator approval.',
            ]);
        }

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
