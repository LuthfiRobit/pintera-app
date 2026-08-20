<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('roles/permissions-catalog', [RoleController::class, 'permissionsCatalog'])->name('roles.permissions-catalog');
Route::resource('roles', RoleController::class)->except(['show']);
Route::resource('users', UserController::class)->except(['show', 'destroy']);
Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
