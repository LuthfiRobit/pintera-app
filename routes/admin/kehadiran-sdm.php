<?php

use App\Http\Controllers\Admin\AttendanceConfigurationController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendanceQrScanController;
use Illuminate\Support\Facades\Route;

Route::get('kehadiran-sdm', [AttendanceController::class, 'index'])->name('kehadiran-sdm.index');
Route::get('kehadiran-sdm/catat', [AttendanceController::class, 'create'])->name('kehadiran-sdm.create');
Route::post('kehadiran-sdm', [AttendanceController::class, 'store'])->name('kehadiran-sdm.store');
Route::get('kehadiran-sdm/scan', [AttendanceQrScanController::class, 'index'])->name('kehadiran-sdm.scan.index');
Route::post('kehadiran-sdm/scan', [AttendanceQrScanController::class, 'store'])->name('kehadiran-sdm.scan.store');

Route::get('kehadiran-sdm/konfigurasi', [AttendanceConfigurationController::class, 'index'])->name('kehadiran-sdm.konfigurasi.index');
Route::post('kehadiran-sdm/konfigurasi/metode', [AttendanceConfigurationController::class, 'updateMetode'])->name('kehadiran-sdm.konfigurasi.metode');
Route::post('kehadiran-sdm/konfigurasi/titik', [AttendanceConfigurationController::class, 'storeTitik'])->name('kehadiran-sdm.titik.store');
Route::put('kehadiran-sdm/konfigurasi/titik/{titik}', [AttendanceConfigurationController::class, 'updateTitik'])->name('kehadiran-sdm.titik.update');
Route::delete('kehadiran-sdm/konfigurasi/titik/{titik}', [AttendanceConfigurationController::class, 'destroyTitik'])->name('kehadiran-sdm.titik.destroy');

Route::put('kehadiran-sdm/konfigurasi/kalender/hari-kerja', [AttendanceConfigurationController::class, 'updateHariKerja'])->name('kehadiran-sdm.kalender.hari-kerja');
Route::post('kehadiran-sdm/konfigurasi/kalender/entri', [AttendanceConfigurationController::class, 'storeKalenderEntri'])->name('kehadiran-sdm.kalender.entri.store');
Route::put('kehadiran-sdm/konfigurasi/kalender/entri/{entri}', [AttendanceConfigurationController::class, 'updateKalenderEntri'])->name('kehadiran-sdm.kalender.entri.update');
Route::delete('kehadiran-sdm/konfigurasi/kalender/entri/{entri}', [AttendanceConfigurationController::class, 'destroyKalenderEntri'])->name('kehadiran-sdm.kalender.entri.destroy');
Route::get('kehadiran-sdm/konfigurasi/kalender/salin-tersedia', [AttendanceConfigurationController::class, 'kalenderSalinTersedia'])->name('kehadiran-sdm.kalender.salin-tersedia');
Route::post('kehadiran-sdm/konfigurasi/kalender/salin', [AttendanceConfigurationController::class, 'kalenderSalin'])->name('kehadiran-sdm.kalender.salin');

Route::post('kehadiran-sdm/konfigurasi/policy', [AttendanceConfigurationController::class, 'storePolicy'])->name('kehadiran-sdm.policy.store');
Route::put('kehadiran-sdm/konfigurasi/policy/{policy}', [AttendanceConfigurationController::class, 'updatePolicy'])->name('kehadiran-sdm.policy.update');
Route::delete('kehadiran-sdm/konfigurasi/policy/{policy}', [AttendanceConfigurationController::class, 'destroyPolicy'])->name('kehadiran-sdm.policy.destroy');

Route::post('kehadiran-sdm/konfigurasi/jenis-shift', [AttendanceConfigurationController::class, 'storeJenisShift'])->name('kehadiran-sdm.jenis-shift.store');
Route::put('kehadiran-sdm/konfigurasi/jenis-shift/{jenisShift}', [AttendanceConfigurationController::class, 'updateJenisShift'])->name('kehadiran-sdm.jenis-shift.update');
Route::delete('kehadiran-sdm/konfigurasi/jenis-shift/{jenisShift}', [AttendanceConfigurationController::class, 'destroyJenisShift'])->name('kehadiran-sdm.jenis-shift.destroy');

Route::post('kehadiran-sdm/konfigurasi/penugasan-shift', [AttendanceConfigurationController::class, 'storePenugasanShift'])->name('kehadiran-sdm.penugasan-shift.store');
Route::put('kehadiran-sdm/konfigurasi/penugasan-shift/{penugasanShift}', [AttendanceConfigurationController::class, 'updatePenugasanShift'])->name('kehadiran-sdm.penugasan-shift.update');
Route::delete('kehadiran-sdm/konfigurasi/penugasan-shift/{penugasanShift}', [AttendanceConfigurationController::class, 'destroyPenugasanShift'])->name('kehadiran-sdm.penugasan-shift.destroy');

Route::post('kehadiran-sdm/konfigurasi/kuota-cuti', [AttendanceConfigurationController::class, 'storeKuotaCuti'])->name('kehadiran-sdm.kuota-cuti.store');
Route::put('kehadiran-sdm/konfigurasi/kuota-cuti/{kuotaCuti}', [AttendanceConfigurationController::class, 'updateKuotaCuti'])->name('kehadiran-sdm.kuota-cuti.update');
Route::delete('kehadiran-sdm/konfigurasi/kuota-cuti/{kuotaCuti}', [AttendanceConfigurationController::class, 'destroyKuotaCuti'])->name('kehadiran-sdm.kuota-cuti.destroy');

Route::get('kehadiran-sdm/izin-cuti', [\App\Http\Controllers\Admin\ApprovalIzinCutiController::class, 'index'])->name('kehadiran-sdm.izin-cuti.index');
Route::get('kehadiran-sdm/izin-cuti/{izinCuti}', [\App\Http\Controllers\Admin\ApprovalIzinCutiController::class, 'show'])->name('kehadiran-sdm.izin-cuti.show');
Route::post('kehadiran-sdm/izin-cuti/{izinCuti}/keputusan', [\App\Http\Controllers\Admin\ApprovalIzinCutiController::class, 'decision'])->name('kehadiran-sdm.izin-cuti.decision');



