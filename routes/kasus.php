<?php

use App\Http\Controllers\KasusConsentController;
use App\Http\Controllers\KasusController;
use App\Http\Controllers\KasusEvaluasiController;
use App\Http\Controllers\KasusSesiController;
use App\Http\Controllers\KasusTugasBatchPreviewController;
use App\Http\Controllers\KasusTugasController;
use App\Http\Controllers\KasusTugasSubmissionController;
use Illuminate\Support\Facades\Route;

// Orang tua accounts have no lembaga_id of their own, so implicit route-model binding's
// default TenantScope-applied lookup would 404 on {kasus} before the controller's own
// isSubmitter/isKontakUtama/kasus.triase authorization logic ever runs. Bind explicitly,
// bypassing the tenant scope; real authorization stays inside each controller action.
Route::bind('kasus', function ($value) {
    return \App\Domains\Kasus\Models\Kasus::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
        ->withTrashed()
        ->findOrFail($value);
});

Route::middleware(['auth', 'verified'])->prefix('kasus')->name('kasus.')->group(function () {
    Route::get('/', [KasusController::class, 'index'])->name('index');
    Route::get('ajukan', [KasusController::class, 'create'])->name('create');
    Route::post('/', [KasusController::class, 'store'])->name('store');
    Route::get('{kasus}', [KasusController::class, 'show'])->name('show');
    Route::patch('{kasus}/consent/{kasusConsent}', [KasusConsentController::class, 'approve'])->name('consent.approve');
    Route::post('{kasus}/sesi', [KasusSesiController::class, 'store'])->name('sesi.store');
    Route::patch('{kasus}/sesi/{kasusSesi}', [KasusSesiController::class, 'updateStatus'])->name('sesi.update-status');
    Route::post('{kasus}/tugas', [KasusTugasController::class, 'store'])->name('tugas.store');
    Route::post('{kasus}/tugas/preview', [KasusTugasBatchPreviewController::class, 'preview'])->name('tugas.preview');
    Route::post('{kasus}/tugas/{kasusTugas}/submission', [KasusTugasSubmissionController::class, 'store'])->name('tugas.submission.store');
    Route::patch('{kasus}/tugas/{kasusTugas}/submission/{kasusTugasSubmission}/review', [KasusTugasSubmissionController::class, 'review'])->name('tugas.submission.review');
    Route::get('{kasus}/tugas/{kasusTugas}/submission/{kasusTugasSubmission}/lampiran', [KasusTugasSubmissionController::class, 'download'])->name('tugas.submission.lampiran');
    Route::patch('{kasus}/tugas/{kasusTugas}/selesai', [KasusTugasController::class, 'markSelesai'])->name('tugas.selesai');
    Route::post('{kasus}/evaluasi', [KasusEvaluasiController::class, 'store'])->name('evaluasi.store');
});
