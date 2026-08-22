<?php

use App\Http\Controllers\Admin\AttendanceConfigurationController;
use App\Http\Controllers\Admin\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::get('kehadiran-sdm', [AttendanceController::class, 'index'])->name('kehadiran-sdm.index');
Route::get('kehadiran-sdm/catat', [AttendanceController::class, 'create'])->name('kehadiran-sdm.create');
Route::post('kehadiran-sdm', [AttendanceController::class, 'store'])->name('kehadiran-sdm.store');

Route::get('kehadiran-sdm/konfigurasi', [AttendanceConfigurationController::class, 'index'])->name('kehadiran-sdm.konfigurasi.index');
Route::post('kehadiran-sdm/konfigurasi/metode', [AttendanceConfigurationController::class, 'updateMetode'])->name('kehadiran-sdm.konfigurasi.metode');
Route::post('kehadiran-sdm/konfigurasi/titik', [AttendanceConfigurationController::class, 'storeTitik'])->name('kehadiran-sdm.titik.store');
Route::put('kehadiran-sdm/konfigurasi/titik/{titik}', [AttendanceConfigurationController::class, 'updateTitik'])->name('kehadiran-sdm.titik.update');
Route::delete('kehadiran-sdm/konfigurasi/titik/{titik}', [AttendanceConfigurationController::class, 'destroyTitik'])->name('kehadiran-sdm.titik.destroy');
