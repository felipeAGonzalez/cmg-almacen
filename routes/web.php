<?php

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

    Route::resource('warehouses', WarehouseController::class)
        ->except('show')
        ->middleware('role:administrator');

});

require __DIR__.'/auth.php';
