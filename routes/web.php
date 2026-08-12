<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\BriWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/bri/payment-notification', [BriWebhookController::class, 'handlePaymentNotification']);

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

Route::middleware(['auth', 'verified', 'permission:keuangan.akses', 'resolve.active.siswa'])
    ->prefix('keuangan')->name('keuangan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Keuangan\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/tagihan', [\App\Http\Controllers\Keuangan\TagihanController::class, 'index'])->name('tagihan.index');
    });

require __DIR__.'/spmb.php';
require __DIR__.'/portal.php';
