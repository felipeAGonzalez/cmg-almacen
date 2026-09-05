<?php

use App\Http\Controllers\AdministrationVoucherController;
use App\Http\Controllers\AdministrationVoucherFulfillmentController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\CabinetInventoryController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HospitalDelegatedAuthController;
use App\Http\Controllers\HospitalIntegrationController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\InventoryKardexController;
use App\Http\Controllers\InventoryOutboundController;
use App\Http\Controllers\InventoryTransferController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NursingVoucherController;
use App\Http\Controllers\NursingVoucherFulfillmentController;
use App\Http\Controllers\OperationalSettingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseInventoryController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('home')
        : redirect()->route('login');
});

Route::get('/auth/hospital/delegated', [HospitalDelegatedAuthController::class, 'consume'])
    ->name('hospital-delegated-auth.consume');

Route::middleware(['auth', 'prevent.back'])->group(function () {
    Route::get('/home', DashboardController::class)->name('home');

    Route::get('/nursing/hospital-context', [HospitalDelegatedAuthController::class, 'context'])
        ->middleware('role:nurse')
        ->name('nursing.hospital-context');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('nursing/vouchers', [NursingVoucherController::class, 'index'])->name('nursing-vouchers.index');
    Route::get('nursing/vouchers/create', [NursingVoucherController::class, 'create'])->name('nursing-vouchers.create');
    Route::post('nursing/vouchers', [NursingVoucherController::class, 'store'])->name('nursing-vouchers.store');
    Route::get('nursing/vouchers/{nursingVoucher}', [NursingVoucherController::class, 'show'])->name('nursing-vouchers.show');
    Route::post('nursing/vouchers/{nursingVoucher}/fulfillments', [NursingVoucherFulfillmentController::class, 'store'])->name('nursing-vouchers.fulfillments.store');
    Route::get('administration/vouchers', [AdministrationVoucherController::class, 'index'])->name('administration-vouchers.index');
    Route::get('administration/vouchers/create', [AdministrationVoucherController::class, 'create'])->name('administration-vouchers.create');
    Route::post('administration/vouchers', [AdministrationVoucherController::class, 'store'])->name('administration-vouchers.store');
    Route::get('administration/vouchers/{administrationVoucher}', [AdministrationVoucherController::class, 'show'])->name('administration-vouchers.show');
    Route::post('administration/vouchers/{administrationVoucher}/fulfillments', [AdministrationVoucherFulfillmentController::class, 'store'])->name('administration-vouchers.fulfillments.store');
    Route::post('administration/vouchers/{administrationVoucher}/reject', [AdministrationVoucherController::class, 'reject'])->name('administration-vouchers.reject');
    Route::post('administration/vouchers/{administrationVoucher}/cancel', [AdministrationVoucherController::class, 'cancel'])->name('administration-vouchers.cancel');

    Route::post('nursing/vouchers/{nursingVoucher}/reject', [NursingVoucherController::class, 'reject'])->name('nursing-vouchers.reject');
    Route::post('nursing/vouchers/{nursingVoucher}/cancel', [NursingVoucherController::class, 'cancel'])->name('nursing-vouchers.cancel');

    Route::resource('users', UserController::class)
        ->except('show')
        ->middleware('role:administrator');

    Route::get('settings/operations', [OperationalSettingController::class, 'edit'])
        ->middleware('role:administrator')
        ->name('operational-settings.edit');
    Route::put('settings/operations', [OperationalSettingController::class, 'update'])
        ->middleware('role:administrator')
        ->name('operational-settings.update');

    Route::get('integrations/hospital/patients', [HospitalIntegrationController::class, 'patients'])
        ->middleware('role:administrator')
        ->name('hospital-integration.patients');

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
