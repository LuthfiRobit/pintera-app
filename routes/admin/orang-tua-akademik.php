<?php

use App\Http\Controllers\Admin\JadwalAnakController;
use App\Http\Controllers\Admin\NilaiAnakController;
use App\Http\Controllers\Admin\RiwayatIzinSakitAnakController;
use Illuminate\Support\Facades\Route;

Route::get('nilai-anak', [NilaiAnakController::class, 'index'])->name('nilai-anak.index');
Route::get('nilai-anak/{siswa}/unduh-rapor', [NilaiAnakController::class, 'unduhRapor'])->name('nilai-anak.unduh-rapor');
Route::get('jadwal-anak', [JadwalAnakController::class, 'index'])->name('jadwal-anak.index');
Route::get('riwayat-izin-sakit-anak', [RiwayatIzinSakitAnakController::class, 'index'])->name('riwayat-izin-sakit-anak.index');
