<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Api\Keuangan\BriVaInboundController;
use App\Http\Controllers\Keuangan\NotifikasiController;
use App\Http\Controllers\Portal\Keuangan\CheckoutController;
use App\Http\Controllers\Portal\Keuangan\RiwayatController;
use App\Http\Controllers\Portal\Keuangan\TagihanController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/snap/v1.0/access-token/b2b', [BriVaInboundController::class, 'token']);
Route::post('/snap/v1.0/transfer-va/inquiry', [BriVaInboundController::class, 'inquiry']);
Route::post('/snap/v1.0/transfer-va/payment', [BriVaInboundController::class, 'payment']);

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dalam-pengembangan', function (Request $request) {
        $labelMap = [
            'nilai-rapor' => 'Nilai & Rapor',
            'jadwal-pelajaran' => 'Jadwal Pelajaran',
            'presensi-saya' => 'Presensi Saya',
            'nilai-anak' => 'Nilai Anak',
            'jadwal-anak' => 'Jadwal Anak',
            'riwayat-izin-sakit-anak' => 'Riwayat Izin/Sakit Anak',
        ];
        $fitur = $labelMap[$request->query('fitur')] ?? 'Fitur Ini';

        return view('shared.dalam-pengembangan', ['fitur' => $fitur]);
    })->name('dalam-pengembangan');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/notification-preference', [ProfileController::class, 'updateNotificationPreference'])->name('profile.notification-preference.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/notifikasi/{id}/baca', [NotifikasiController::class, 'bacaSatu'])->name('notifikasi.baca');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/kasus.php';
require __DIR__.'/guru.php';

Route::middleware(['auth', 'verified', 'permission:keuangan.akses', 'resolve.active.siswa'])
    ->prefix('keuangan')->name('keuangan.')->group(function () {
        Route::get('/', [App\Http\Controllers\Portal\Keuangan\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
        Route::get('/tagihan/{tagihan}', [TagihanController::class, 'show'])->name('tagihan.show');
        Route::get('/riwayat', [RiwayatController::class, 'index'])->name('riwayat.index');
        Route::get('/riwayat/{pembayaran}/kwitansi', [RiwayatController::class, 'kwitansi'])->name('riwayat.kwitansi');
        Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
        Route::post('/checkout/va', [CheckoutController::class, 'va'])->name('checkout.va');
        Route::post('/checkout/qris', [CheckoutController::class, 'qris'])->name('checkout.qris');
        Route::post('/checkout/wallet', [CheckoutController::class, 'wallet'])->name('checkout.wallet');
        Route::get('/checkout/va-info', [CheckoutController::class, 'vaInfo'])->name('checkout.va-info');
        Route::post('/checkout/transfer', [CheckoutController::class, 'transfer'])->name('checkout.transfer');
        Route::get('/checkout/{pembayaran}/sukses', [CheckoutController::class, 'sukses'])->name('checkout.sukses');
        Route::get('/checkout/{pembayaran}/menunggu-verifikasi', [CheckoutController::class, 'menungguVerifikasi'])->name('checkout.menunggu-verifikasi');
        Route::get('/checkout/{pembayaran}', [CheckoutController::class, 'show'])->name('checkout.show');
        Route::get('/checkout/{pembayaran}/status', [CheckoutController::class, 'status'])->name('checkout.status');
    });

require __DIR__.'/spmb.php';
require __DIR__.'/portal.php';
require __DIR__.'/sdm.php';
