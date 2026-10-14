<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $requiredRoles = array_map(
            fn (string $role): ?UserRole => $role === 'admin'
                ? UserRole::ADMINISTRATOR
                : UserRole::tryFrom($role),
            $roles,
        );

        $hasRole = collect($requiredRoles)->contains(
            fn (?UserRole $requiredRole): bool => $requiredRole === UserRole::ADMINISTRATOR
                ? $user?->isAdmin() === true
                : $user?->role === $requiredRole,
        );

        if (! $hasRole) {
            abort(403);
        }

        return $next($request);
    }
}
