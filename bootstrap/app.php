<?php

use App\Http\Middleware\EnsureUserCanManageCabinets;
use App\Http\Middleware\EnsureUserCanManageEntries;
use App\Http\Middleware\EnsureUserCanManageInventory;
use App\Http\Middleware\EnsureUserCanManageLocations;
use App\Http\Middleware\EnsureUserCanManageOutbounds;
use App\Http\Middleware\EnsureUserCanManageSuppliers;
use App\Http\Middleware\EnsureUserCanManageTransfers;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'prevent.back' => PreventBackHistory::class,
            'role' => EnsureUserHasRole::class,
            'cabinet.access' => EnsureUserCanManageCabinets::class,
            'entry.access' => EnsureUserCanManageEntries::class,
            'inventory.access' => EnsureUserCanManageInventory::class,
            'location.access' => EnsureUserCanManageLocations::class,
            'outbound.access' => EnsureUserCanManageOutbounds::class,
            'supplier.access' => EnsureUserCanManageSuppliers::class,
            'transfer.access' => EnsureUserCanManageTransfers::class,
        ]);

        // Aplica no-cache a TODAS las rutas web: evita que el browser cachee
        // tanto las páginas privadas (tras logout) como el login (tras iniciar sesión).
        $middleware->appendToGroup('web', [
            PreventBackHistory::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
