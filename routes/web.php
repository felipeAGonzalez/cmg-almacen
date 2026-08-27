<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
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


    Route::resource('units', UnitController::class)
        ->except('show')
        ->middleware('role:administrator');

    Route::resource('warehouses', WarehouseController::class)
        ->except('show')
        ->middleware('role:administrator');

    Route::resource('warehouses.suppliers', SupplierController::class)
        ->except('show')
        ->middleware('supplier.access')
        ->scoped();
});

require __DIR__.'/auth.php';
