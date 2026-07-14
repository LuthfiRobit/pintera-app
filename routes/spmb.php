<?php

use App\Http\Controllers\Spmb\PortalController;
use App\Http\Controllers\Spmb\VerifikasiEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('spmb')->name('spmb.')->group(function () {
    Route::get('{lembagaSlug}', [PortalController::class, 'index'])->name('index');
    Route::get('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'create'])->name('mulai');
    Route::post('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'store'])
        ->middleware('throttle:6,1')->name('mulai.store');
    Route::get('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'edit'])->name('verifikasi-otp');
    Route::post('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'update'])->name('verifikasi-otp.store');

    // Placeholders: named routes referenced by this task's views/redirects but implemented by
    // later tasks in the M2 wizard plan (data-diri form step, "check status" lookup form).
    // Registered here only so `route()`/`redirect()->route()` resolve without throwing;
    // a later task should replace these with real controllers/views.
    Route::get('{lembagaSlug}/{jalur}/data-diri', fn () => abort(404))->name('data-diri');
    Route::get('{lembagaSlug}/status', fn () => abort(404))->name('status.form');
});
