<?php

use Illuminate\Support\Facades\Route;

// Sarana & Prasarana (Sarpras)
Route::prefix('sarpras')->name('sarpras.')->group(function () {
    Route::resource('gedung', \App\Http\Controllers\Lembaga\Sarpras\GedungController::class)->except(['show']);
    Route::resource('ruangan', \App\Http\Controllers\Lembaga\Sarpras\RuanganController::class);
    Route::resource('kategori', \App\Http\Controllers\Lembaga\Sarpras\KategoriAsetController::class)->only(['index', 'store', 'destroy']);
    Route::resource('aset', \App\Http\Controllers\Lembaga\Sarpras\AsetBarangController::class);
    Route::get('mutasi', [\App\Http\Controllers\Lembaga\Sarpras\MutasiAsetController::class, 'index'])->name('mutasi.index');
    Route::post('mutasi', [\App\Http\Controllers\Lembaga\Sarpras\MutasiAsetController::class, 'store'])->name('mutasi.store');
    Route::get('kir/{ruangan}', [\App\Http\Controllers\Lembaga\Sarpras\KirController::class, 'show'])->name('kir.show');
    Route::get('kir/{ruangan}/export-pdf', [\App\Http\Controllers\Lembaga\Sarpras\KirController::class, 'exportPdf'])->name('kir.export');
    Route::get('rekap-global', [\App\Http\Controllers\Yayasan\Sarpras\RekapAsetGlobalController::class, 'index'])->name('rekap-global');
});
