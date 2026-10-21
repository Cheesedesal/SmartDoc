<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.password-email');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (TransportExceptionInterface $exception) {
            Log::error('Password reset email delivery failed.', [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'exception_class' => get_class($exception),
            ]);

            return back()->with('error', 'If that email belongs to an account, we could not send its reset link. Please try again later or contact the administrator.');
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'If that email is associated with an account, a password reset link will be sent.')
            : back()->with('error', __($status));
    }

    public function sendAuthenticatedLink(Request $request): RedirectResponse
    {
        $user = $request->user();
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (TransportExceptionInterface $exception) {
            Log::error('Password reset email delivery failed.', [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'exception_class' => get_class($exception),
            ]);

            return back()->with('error', 'The reset email could not be sent. Check the mail configuration or contact the administrator.');
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'A password reset link was sent to '.$user->email.'.')
            : back()->with('error', __($status));
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.password-reset', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);

        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->only('email'))->with('error', __($status));
    }
}
