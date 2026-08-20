<?php

use App\Http\Controllers\Admin\Guru\JabatanTambahanController as GuruJabatanTambahanController;
use App\Http\Controllers\Admin\Guru\RiwayatPendidikanController as GuruRiwayatPendidikanController;
use App\Http\Controllers\Admin\Guru\SertifikasiController as GuruSertifikasiController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\JabatanTambahanMasterController;
use App\Http\Controllers\Admin\JenisKaryawanMasterController;
use Illuminate\Support\Facades\Route;

Route::resource('guru', GuruController::class)->except(['show', 'destroy']);
Route::patch('guru/{guru}/status', [GuruController::class, 'updateStatus'])->name('guru.update-status');

Route::post('guru/{guru}/riwayat-pendidikan', [GuruRiwayatPendidikanController::class, 'store'])->name('guru.riwayat-pendidikan.store');
Route::put('guru/{guru}/riwayat-pendidikan/{riwayatPendidikan}', [GuruRiwayatPendidikanController::class, 'update'])->name('guru.riwayat-pendidikan.update');
Route::delete('guru/{guru}/riwayat-pendidikan/{riwayatPendidikan}', [GuruRiwayatPendidikanController::class, 'destroy'])->name('guru.riwayat-pendidikan.destroy');

Route::post('guru/{guru}/sertifikasi', [GuruSertifikasiController::class, 'store'])->name('guru.sertifikasi.store');
Route::put('guru/{guru}/sertifikasi/{sertifikasi}', [GuruSertifikasiController::class, 'update'])->name('guru.sertifikasi.update');
Route::delete('guru/{guru}/sertifikasi/{sertifikasi}', [GuruSertifikasiController::class, 'destroy'])->name('guru.sertifikasi.destroy');

Route::post('guru/{guru}/jabatan-tambahan', [GuruJabatanTambahanController::class, 'store'])->name('guru.jabatan-tambahan.store');
Route::delete('guru/{guru}/jabatan-tambahan/{jabatanMasterId}', [GuruJabatanTambahanController::class, 'destroy'])->name('guru.jabatan-tambahan.destroy');
Route::get('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'index'])->name('jabatan-tambahan-master.index');
Route::post('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'store'])->name('jabatan-tambahan-master.store');
Route::put('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'update'])->name('jabatan-tambahan-master.update');
Route::delete('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'destroy'])->name('jabatan-tambahan-master.destroy');

Route::get('jenis-karyawan-master', [JenisKaryawanMasterController::class, 'index'])->name('jenis-karyawan-master.index');
Route::post('jenis-karyawan-master', [JenisKaryawanMasterController::class, 'store'])->name('jenis-karyawan-master.store');
Route::put('jenis-karyawan-master/{jenisKaryawanMaster}', [JenisKaryawanMasterController::class, 'update'])->name('jenis-karyawan-master.update');
Route::delete('jenis-karyawan-master/{jenisKaryawanMaster}', [JenisKaryawanMasterController::class, 'destroy'])->name('jenis-karyawan-master.destroy');
