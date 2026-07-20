<?php

use App\Http\Controllers\Spmb\CekStatusController;
use App\Http\Controllers\Spmb\DataDiriController;
use App\Http\Controllers\Spmb\FormulirTambahanController;
use App\Http\Controllers\Spmb\PortalController;
use App\Http\Controllers\Spmb\ReviewSubmitController;
use App\Http\Controllers\Spmb\UploadDokumenController;
use App\Http\Controllers\Spmb\VerifikasiEmailController;
use App\Http\Controllers\Spmb\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::prefix('spmb')->name('spmb.')->group(function () {
    Route::get('/', [WelcomeController::class, 'index'])->name('welcome');
    Route::get('{lembagaSlug}', [PortalController::class, 'index'])->name('index');
    Route::post('{lembagaSlug}/jalur/{jalur}/daftar', [PortalController::class, 'daftarJalur'])->name('jalur.daftar');
    Route::get('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'create'])->name('mulai');
    Route::post('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'store'])
        ->middleware('throttle:6,1')->name('mulai.store');
    Route::get('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'edit'])->name('verifikasi-otp');
    Route::post('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'update'])->name('verifikasi-otp.store');
    Route::get('{lembagaSlug}/{jalur}/data-diri', [DataDiriController::class, 'create'])->name('data-diri');
    Route::post('{lembagaSlug}/{jalur}/data-diri/cek-nik', [DataDiriController::class, 'cekNik'])->name('data-diri.cek-nik');
    Route::post('{lembagaSlug}/{jalur}/data-diri', [DataDiriController::class, 'store'])->name('data-diri.store');
    Route::get('{lembagaSlug}/{jalur}/formulir-tambahan', [FormulirTambahanController::class, 'create'])->name('formulir-tambahan');
    Route::post('{lembagaSlug}/{jalur}/formulir-tambahan', [FormulirTambahanController::class, 'store'])->name('formulir-tambahan.store');
    Route::get('{lembagaSlug}/{jalur}/dokumen', [UploadDokumenController::class, 'create'])->name('dokumen');
    Route::post('{lembagaSlug}/{jalur}/dokumen', [UploadDokumenController::class, 'store'])->name('dokumen.store');
    Route::get('{lembagaSlug}/{jalur}/review', [ReviewSubmitController::class, 'show'])->name('review');
    Route::post('{lembagaSlug}/{jalur}/submit', [ReviewSubmitController::class, 'submit'])
        ->middleware('throttle:10,1')->name('submit');
    Route::get('{lembagaSlug}/berhasil/{kodePendaftaran}', [ReviewSubmitController::class, 'berhasil'])
        ->middleware('throttle:10,1')->name('berhasil');

    Route::get('{lembagaSlug}/status', [CekStatusController::class, 'create'])->name('status.form');
    Route::post('{lembagaSlug}/status', [CekStatusController::class, 'show'])
        ->middleware('throttle:10,1')->name('status.show');
    Route::get('{lembagaSlug}/bukti/{kodePendaftaran}', [CekStatusController::class, 'unduhBukti'])
        ->middleware('throttle:10,1')->name('bukti');
});
