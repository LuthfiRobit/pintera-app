<?php

use App\Http\Controllers\EmployeeQrCodeController;
use App\Http\Controllers\PengajuanIzinCutiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('sdm')->name('sdm.')->group(function () {
    Route::get('qr-saya', [EmployeeQrCodeController::class, 'show'])->name('qr-saya');
    Route::post('qr-saya/generate', [EmployeeQrCodeController::class, 'generate'])->name('qr-saya.generate');

    Route::get('izin-cuti', [PengajuanIzinCutiController::class, 'index'])->name('izin-cuti.index');
    Route::get('izin-cuti/ajukan', [PengajuanIzinCutiController::class, 'create'])->name('izin-cuti.create');
    Route::post('izin-cuti', [PengajuanIzinCutiController::class, 'store'])->name('izin-cuti.store');
    Route::delete('izin-cuti/{izinCuti}', [PengajuanIzinCutiController::class, 'destroy'])->name('izin-cuti.destroy');
});
