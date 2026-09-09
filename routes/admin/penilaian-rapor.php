<?php

use App\Http\Controllers\Admin\KenaikanKelasController;
use App\Http\Controllers\Admin\KomponenPenilaianController;
use App\Http\Controllers\Admin\RaporController;
use App\Http\Controllers\Lembaga\Rapor\PersetujuanController;
use Illuminate\Support\Facades\Route;

Route::get('komponen-penilaian', [KomponenPenilaianController::class, 'index'])->name('komponen-penilaian.index');
Route::get('komponen-penilaian/create', [KomponenPenilaianController::class, 'create'])->name('komponen-penilaian.create');
Route::post('komponen-penilaian', [KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');
Route::get('komponen-penilaian/opsi', [KomponenPenilaianController::class, 'opsi'])->name('komponen-penilaian.opsi');
Route::get('komponen-penilaian/{komponenPenilaian}/edit', [KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
Route::put('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
Route::delete('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');
Route::get('rapor', [RaporController::class, 'index'])->name('rapor.index');
Route::get('rapor/opsi', [RaporController::class, 'opsi'])->name('rapor.opsi');
Route::get('rapor/cetak', [RaporController::class, 'cetak'])->name('rapor.cetak');
Route::get('rapor/persetujuan', [PersetujuanController::class, 'index'])->name('rapor.persetujuan.index');
Route::get('rapor/persetujuan/{pengajuanRapor}', [PersetujuanController::class, 'show'])->name('rapor.persetujuan.show');
Route::post('rapor/persetujuan/{pengajuanRapor}/keputusan', [PersetujuanController::class, 'decision'])->name('rapor.persetujuan.decision');
Route::get('rapor/persetujuan/{pengajuanRapor}/cetak/{siswa}', [PersetujuanController::class, 'cetak'])->name('rapor.persetujuan.cetak');

Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas.index');
Route::post('kenaikan-kelas', [KenaikanKelasController::class, 'store'])->name('kenaikan-kelas.store');
