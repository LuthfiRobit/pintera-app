<?php

use App\Http\Controllers\Spmb\DataDiriController;
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
    Route::get('{lembagaSlug}/{jalur}/data-diri', [DataDiriController::class, 'create'])->name('data-diri');
    Route::post('{lembagaSlug}/{jalur}/data-diri/cek-nik', [DataDiriController::class, 'cekNik'])->name('data-diri.cek-nik');
    Route::post('{lembagaSlug}/{jalur}/data-diri', [DataDiriController::class, 'store'])->name('data-diri.store');

    // Placeholders: named routes referenced by this task's redirects/views but implemented by
    // later tasks in the M2 wizard plan (formulir tambahan step, "check status" lookup form).
    // Registered here only so `route()`/`redirect()->route()` resolve without throwing;
    // a later task should replace these with real controllers/views.
    Route::get('{lembagaSlug}/{jalur}/formulir-tambahan', fn () => abort(404))->name('formulir-tambahan');
    Route::get('{lembagaSlug}/status', fn () => abort(404))->name('status.form');
});
