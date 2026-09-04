<?php

use App\Http\Controllers\Admin\JadwalPelajaranSiswaController;
use App\Http\Controllers\Admin\NilaiRaporSiswaController;
use Illuminate\Support\Facades\Route;

Route::get('nilai-rapor-saya', [NilaiRaporSiswaController::class, 'index'])->name('nilai-rapor-saya.index');
Route::get('nilai-rapor-saya/unduh-rapor', [NilaiRaporSiswaController::class, 'unduhRapor'])->name('nilai-rapor-saya.unduh-rapor');
Route::get('jadwal-pelajaran-saya', [JadwalPelajaranSiswaController::class, 'index'])->name('jadwal-pelajaran-saya.index');
