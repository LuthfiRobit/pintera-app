<?php
// routes/portal.php

use App\Http\Controllers\Portal\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Portal\Auth\NewPasswordController;
use App\Http\Controllers\Portal\Auth\PasswordResetLinkController;
use App\Http\Controllers\Portal\Auth\RegisteredAkunController;
use App\Http\Controllers\Portal\Auth\VerifikasiOtpController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:portal')->group(function () {
        Route::get('register', [RegisteredAkunController::class, 'create'])->name('register');
        Route::post('register', [RegisteredAkunController::class, 'store'])->middleware('throttle:6,1');

        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:6,1')->name('password.email');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    });

    Route::get('verifikasi-otp', [VerifikasiOtpController::class, 'create'])->name('verifikasi-otp');
    Route::post('verifikasi-otp', [VerifikasiOtpController::class, 'store'])
        ->middleware('throttle:6,1')->name('verifikasi-otp.store');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:portal')->name('logout');

    // Placeholder from Task 2, carried forward as-is: Task 4 replaces this with a real
    // DashboardController + view. Needed here because AuthenticatedSessionController::store()
    // (this task) and VerifikasiOtpController::store() (Task 2) both redirect to it.
    Route::get('dashboard', fn () => response('OK'))
        ->middleware('auth:portal')->name('dashboard');
});
