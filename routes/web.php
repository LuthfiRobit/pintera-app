<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('/snap/v1.0/access-token/b2b', [\App\Http\Controllers\Api\BriVaInboundController::class, 'token']);
Route::post('/snap/v1.0/transfer-va/inquiry', [\App\Http\Controllers\Api\BriVaInboundController::class, 'inquiry']);
Route::post('/snap/v1.0/transfer-va/payment', [\App\Http\Controllers\Api\BriVaInboundController::class, 'payment']);

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/notification-preference', [ProfileController::class, 'updateNotificationPreference'])->name('profile.notification-preference.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/notifikasi/{id}/baca', [\App\Http\Controllers\Keuangan\NotifikasiController::class, 'bacaSatu'])->name('notifikasi.baca');
    Route::post('/notifikasi/baca-semua', [\App\Http\Controllers\Keuangan\NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/kasus.php';
require __DIR__.'/guru.php';

Route::middleware(['auth', 'verified', 'permission:keuangan.akses', 'resolve.active.siswa'])
    ->prefix('keuangan')->name('keuangan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Keuangan\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/tagihan', [\App\Http\Controllers\Portal\Keuangan\TagihanController::class, 'index'])->name('tagihan.index');
        Route::get('/riwayat', [\App\Http\Controllers\Portal\Keuangan\RiwayatController::class, 'index'])->name('riwayat.index');
        Route::get('/riwayat/{pembayaran}/kwitansi', [\App\Http\Controllers\Portal\Keuangan\RiwayatController::class, 'kwitansi'])->name('riwayat.kwitansi');
        Route::get('/checkout', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'create'])->name('checkout.create');
        Route::post('/checkout/va', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'va'])->name('checkout.va');
        Route::post('/checkout/qris', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'qris'])->name('checkout.qris');
        Route::post('/checkout/wallet', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'wallet'])->name('checkout.wallet');
        Route::get('/checkout/va-info', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'vaInfo'])->name('checkout.va-info');
        Route::post('/checkout/transfer', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'transfer'])->name('checkout.transfer');
        Route::get('/checkout/{pembayaran}/sukses', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'sukses'])->name('checkout.sukses');
        Route::get('/checkout/{pembayaran}/menunggu-verifikasi', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'menungguVerifikasi'])->name('checkout.menunggu-verifikasi');
        Route::get('/checkout/{pembayaran}', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'show'])->name('checkout.show');
        Route::get('/checkout/{pembayaran}/status', [\App\Http\Controllers\Portal\Keuangan\CheckoutController::class, 'status'])->name('checkout.status');
    });

require __DIR__.'/spmb.php';
require __DIR__.'/portal.php';
require __DIR__.'/sdm.php';
