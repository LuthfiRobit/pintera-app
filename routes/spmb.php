<?php

use App\Http\Controllers\Portal\Auth\RegisteredAkunController;
use App\Http\Controllers\Spmb\CekStatusController;
use App\Http\Controllers\Spmb\PortalController;
use App\Http\Controllers\Spmb\ReviewSubmitController;
use App\Http\Controllers\Spmb\VerifikasiEmailController;
use App\Http\Controllers\Spmb\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::prefix('spmb')->name('spmb.')->group(function () {
    Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

    Route::middleware('guest:portal')->group(function () {
        Route::get('register', [RegisteredAkunController::class, 'create'])->name('register');
        Route::post('register', [RegisteredAkunController::class, 'store'])->middleware('throttle:6,1');
        Route::post('register/ganti-jalur/{jalur}', [RegisteredAkunController::class, 'gantiJalur'])->name('register.ganti-jalur');
    });
    Route::get('{lembagaSlug}', [PortalController::class, 'index'])->name('index');
    Route::post('{lembagaSlug}/jalur/{jalur}/daftar', [PortalController::class, 'daftarJalur'])->name('jalur.daftar');
    Route::get('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'create'])->name('mulai');
    Route::post('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'store'])
        ->middleware('throttle:6,1')->name('mulai.store');
    Route::get('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'edit'])->name('verifikasi-otp');
    Route::post('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'update'])->name('verifikasi-otp.store');
    Route::get('{lembagaSlug}/berhasil/{kodePendaftaran}', [ReviewSubmitController::class, 'berhasil'])
        ->middleware('throttle:10,1')->name('berhasil');

    Route::get('{lembagaSlug}/status', [CekStatusController::class, 'create'])->name('status.form');
    Route::post('{lembagaSlug}/status', [CekStatusController::class, 'show'])
        ->middleware('throttle:10,1')->name('status.show');
    Route::get('{lembagaSlug}/bukti/{kodePendaftaran}', [CekStatusController::class, 'unduhBukti'])
        ->middleware('throttle:10,1')->name('bukti');
});
