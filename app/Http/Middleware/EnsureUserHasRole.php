<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        $requiredRole = $role === 'admin'
            ? UserRole::ADMINISTRATOR
            : UserRole::tryFrom($role);

        $hasRole = $requiredRole === UserRole::ADMINISTRATOR
            ? $user?->isAdmin() === true
            : $user?->role === $requiredRole;

        if (! $hasRole) {
            abort(403);
        }

        return $next($request);
    }
}
