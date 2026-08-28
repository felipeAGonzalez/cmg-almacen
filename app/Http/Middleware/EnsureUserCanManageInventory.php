<?php

namespace App\Http\Middleware;

use App\Models\Cabinet;
use App\Models\Warehouse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageInventory
{
    public function handle(Request $request, Closure $next): Response
    {
        $warehouse = $request->route('warehouse');
        $cabinet = $request->route('cabinet');

        if (! $warehouse instanceof Warehouse || ! $request->user()?->canManageWarehouse($warehouse)) {
            abort(403);
        }

        if ($cabinet !== null && (! $cabinet instanceof Cabinet || $cabinet->warehouse_id !== $warehouse->getKey())) {
            abort(404);
        }

        return $next($request);
    }
}
