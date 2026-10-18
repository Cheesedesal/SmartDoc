<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side role gate: `->middleware('role:staff,admin')`.
 * Must run after `auth`; a missing user is sent to the login page.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        abort_unless($user->hasRole(...$roles), 403, 'You do not have access to this page.');

        return $next($request);
    }
}
