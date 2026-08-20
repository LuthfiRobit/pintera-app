<?php

use App\Http\Controllers\Admin\KasusAksesLogController;
use App\Http\Controllers\Admin\KasusController as AdminKasusController;
use App\Http\Controllers\Admin\KasusTerhapusController;
use Illuminate\Support\Facades\Route;

Route::get('kasus', [AdminKasusController::class, 'index'])->name('kasus.index');
Route::get('kasus/{kasus}/triase', [AdminKasusController::class, 'triase'])->name('kasus.triase');
Route::post('kasus/{kasus}/assign-konselor', [AdminKasusController::class, 'assignKonselor'])->name('kasus.assign-konselor');
Route::delete('kasus/{kasus}', [AdminKasusController::class, 'destroy'])->name('kasus.destroy');
Route::post('kasus/{kasus}/pulihkan', [AdminKasusController::class, 'restore'])->name('kasus.restore');
Route::get('kasus-log-akses', [KasusAksesLogController::class, 'index'])->name('kasus.log-akses');
Route::get('kasus-terhapus', [KasusTerhapusController::class, 'index'])->name('kasus.terhapus');
