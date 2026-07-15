<?php
// routes/portal.php

use App\Http\Controllers\Portal\Auth\RegisteredAkunController;
use App\Http\Controllers\Portal\Auth\VerifikasiOtpController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:portal')->group(function () {
        Route::get('register', [RegisteredAkunController::class, 'create'])->name('register');
        Route::post('register', [RegisteredAkunController::class, 'store'])->middleware('throttle:6,1');
    });

    Route::get('verifikasi-otp', [VerifikasiOtpController::class, 'create'])->name('verifikasi-otp');
    Route::post('verifikasi-otp', [VerifikasiOtpController::class, 'store'])
        ->middleware('throttle:6,1')->name('verifikasi-otp.store');

    // Placeholder: Task 4 replaces this with a real DashboardController + view.
    // Needed now because VerifikasiOtpController::store() redirects here after a
    // successful verification, and this task's own test asserts that redirect.
    Route::get('dashboard', fn () => response('OK'))
        ->middleware('auth:portal')->name('dashboard');
});
