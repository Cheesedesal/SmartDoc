<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowedRoles = array_map(static fn (string $role) => Role::tryFrom($role), $roles);
        $allowedRoles = array_filter($allowedRoles);

        abort_unless($user && in_array($user->role, $allowedRoles, true), 403);

        return $next($request);
    }
}
