<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\CabinetInventoryController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\InventoryOutboundController;
use App\Http\Controllers\InventoryTransferController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseInventoryController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('home')
        : redirect()->route('login');
});

Route::middleware(['auth', 'prevent.back'])->group(function () {
    Route::get('/home', function () {
        return view('home');
    })->name('home');

    Route::resource('users', UserController::class)
        ->except('show')
        ->middleware('role:administrator');

    Route::resource('categories', CategoryController::class)
        ->except('show')
        ->middleware('role:administrator');

    Route::resource('brands', BrandController::class)
        ->except('show')
        ->middleware('role:administrator');

    Route::resource('products', ProductController::class)
        ->except('show')
        ->middleware('role:administrator');

    Route::resource('units', UnitController::class)
        ->except('show')
        ->middleware('role:administrator');

    Route::resource('warehouses', WarehouseController::class)
        ->except('show')
        ->middleware('role:administrator');

    Route::resource('warehouses.entries', EntryController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->middleware('entry.access')
        ->scoped();

    Route::resource('warehouses.transfers', InventoryTransferController::class)
        ->parameters(['transfers' => 'inventoryTransfer'])
        ->only(['index', 'create', 'store', 'show'])
        ->middleware('transfer.access')
        ->scoped();

    Route::resource('warehouses.outbounds', InventoryOutboundController::class)
        ->parameters(['outbounds' => 'inventoryOutbound'])
        ->only(['index', 'create', 'store', 'show'])
        ->middleware('outbound.access')
        ->scoped();

    Route::resource('warehouses.inventory', WarehouseInventoryController::class)
        ->parameters(['inventory' => 'inventoryItem'])
        ->except('show')
        ->middleware('inventory.access')
        ->scoped();

    Route::resource('warehouses.suppliers', SupplierController::class)
        ->except('show')
        ->middleware('supplier.access')
        ->scoped();

    Route::resource('warehouses.cabinets.inventory', CabinetInventoryController::class)
        ->parameters(['inventory' => 'inventoryItem'])
        ->except('show')
        ->middleware('inventory.access')
        ->scoped();

    Route::resource('warehouses.cabinets', CabinetController::class)
        ->except('show')
        ->middleware('cabinet.access')
        ->scoped();

    Route::resource('warehouses.locations', LocationController::class)
        ->except('show')
        ->middleware('location.access')
        ->scoped();
});

require __DIR__.'/auth.php';
