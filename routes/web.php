<?php

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

    // Ejemplo de ruta restringida por rol:
    // Route::middleware('role:admin')->group(function () {
    //     Route::get('/admin', fn () => view('admin.index'))->name('admin.index');
    // });
});

require __DIR__.'/auth.php';
