<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\CabinetInventoryController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\InventoryKardexController;
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

    Route::get('warehouses/{warehouse}/kardex', [InventoryKardexController::class, 'warehouse'])
        ->middleware('inventory.access')
        ->name('warehouses.kardex.index');

    Route::get('warehouses/{warehouse}/cabinets/{cabinet}/kardex', [InventoryKardexController::class, 'cabinet'])
        ->middleware('inventory.access')
        ->scopeBindings()
        ->name('warehouses.cabinets.kardex.index');

    Route::controller(InventoryAdjustmentController::class)->middleware('inventory.access')->scopeBindings()->group(function (): void {
        Route::get('warehouses/{warehouse}/inventory/{inventoryItem}/adjustments', 'index')->name('warehouses.inventory.adjustments.index');
        Route::get('warehouses/{warehouse}/inventory/{inventoryItem}/adjustments/create', 'create')->name('warehouses.inventory.adjustments.create');
        Route::post('warehouses/{warehouse}/inventory/{inventoryItem}/adjustments', 'store')->name('warehouses.inventory.adjustments.store');
        Route::get('warehouses/{warehouse}/inventory/{inventoryItem}/adjustments/{inventoryAdjustment}', 'show')->name('warehouses.inventory.adjustments.show');

        Route::get('warehouses/{warehouse}/cabinets/{cabinet}/inventory/{inventoryItem}/adjustments', 'cabinetIndex')->name('warehouses.cabinets.inventory.adjustments.index');
        Route::get('warehouses/{warehouse}/cabinets/{cabinet}/inventory/{inventoryItem}/adjustments/create', 'cabinetCreate')->name('warehouses.cabinets.inventory.adjustments.create');
        Route::post('warehouses/{warehouse}/cabinets/{cabinet}/inventory/{inventoryItem}/adjustments', 'cabinetStore')->name('warehouses.cabinets.inventory.adjustments.store');
        Route::get('warehouses/{warehouse}/cabinets/{cabinet}/inventory/{inventoryItem}/adjustments/{inventoryAdjustment}', 'cabinetShow')->name('warehouses.cabinets.inventory.adjustments.show');
    });

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
