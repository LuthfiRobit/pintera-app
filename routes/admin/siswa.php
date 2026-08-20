<?php

use App\Http\Controllers\Admin\KaryawanController;
use App\Http\Controllers\Admin\OrangTuaController;
use App\Http\Controllers\Admin\PendaftaranSiswaController;
use App\Http\Controllers\Admin\SiswaController;
use App\Http\Controllers\Admin\SiswaImportController;
use App\Http\Controllers\Admin\SiswaOrangTuaController;
use Illuminate\Support\Facades\Route;

Route::post('siswa/generate-akun-massal', [SiswaController::class, 'generateAkunMassal'])->name('siswa.generate-akun-massal');
Route::post('siswa/{siswa}/generate-akun', [SiswaController::class, 'generateAkun'])->name('siswa.generate-akun');
Route::resource('siswa', SiswaController::class)->except(['show', 'destroy']);
Route::get('siswa/{siswa}/orang-tua/cari', [SiswaOrangTuaController::class, 'cari'])->name('siswa.orang-tua.cari');
Route::post('siswa/{siswa}/orang-tua', [SiswaOrangTuaController::class, 'store'])->name('siswa.orang-tua.store');
Route::patch('siswa/{siswa}/orang-tua/{orangTua}/kontak-utama', [SiswaOrangTuaController::class, 'updateKontakUtama'])->name('siswa.orang-tua.kontak-utama');
Route::delete('siswa/{siswa}/orang-tua/{orangTua}', [SiswaOrangTuaController::class, 'destroy'])->name('siswa.orang-tua.destroy');
Route::resource('orang-tua', OrangTuaController::class)->except(['show', 'destroy']);
Route::patch('orang-tua/{orangTua}/status', [OrangTuaController::class, 'updateStatus'])->name('orang-tua.update-status');
Route::resource('karyawan', KaryawanController::class)->except(['show', 'destroy']);
Route::patch('karyawan/{karyawan}/status', [KaryawanController::class, 'updateStatus'])->name('karyawan.update-status');
Route::patch('siswa/{siswa}/status', [SiswaController::class, 'updateStatus'])->name('siswa.update-status');
Route::patch('siswa/{siswa}/reset-password', [SiswaController::class, 'resetPassword'])->name('siswa.reset-password');
Route::get('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'index'])->name('siswa.spmb-daftar.index');
Route::post('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'store'])->name('siswa.spmb-daftar.store');
Route::get('siswa-import', [SiswaImportController::class, 'index'])->name('siswa.import.index');
Route::get('siswa-import/template', [SiswaImportController::class, 'template'])->name('siswa.import.template');
Route::post('siswa-import/preview', [SiswaImportController::class, 'preview'])->name('siswa.import.preview');
Route::post('siswa-import/confirm', [SiswaImportController::class, 'confirm'])->name('siswa.import.confirm');
