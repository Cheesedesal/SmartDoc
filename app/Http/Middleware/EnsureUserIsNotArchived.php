<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotArchived
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->archived_at !== null) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'school_id' => 'This account is archived. Please contact an administrator.',
            ]);
        }

        return $next($request);
    }
}
