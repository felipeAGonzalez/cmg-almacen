<?php

namespace App\Http\Middleware;

use App\Models\Warehouse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageCabinets
{
    public function handle(Request $request, Closure $next): Response
    {
        $warehouse = $request->route('warehouse');

        if (! $warehouse instanceof Warehouse || ! $request->user()?->canManageWarehouse($warehouse)) {
            abort(403);
        }

        return $next($request);
    }
}
