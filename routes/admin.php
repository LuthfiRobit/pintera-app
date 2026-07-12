<?php

use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\LembagaController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::resource('lembaga', LembagaController::class)->except(['show', 'destroy']);
    Route::resource('guru', GuruController::class)->except(['show', 'destroy']);
});
