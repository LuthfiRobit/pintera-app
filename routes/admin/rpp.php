<?php

use App\Http\Controllers\Admin\RppController;
use Illuminate\Support\Facades\Route;

// Perangkat Mengajar (RPP / Modul Ajar)
Route::get('rpp', [RppController::class, 'index'])->name('rpp.index');
Route::post('rpp', [RppController::class, 'store'])->name('rpp.store');
Route::get('rpp/{rpp}/download', [RppController::class, 'download'])->name('rpp.download');
Route::put('rpp/{rpp}', [RppController::class, 'update'])->name('rpp.update');
Route::delete('rpp/{rpp}', [RppController::class, 'destroy'])->name('rpp.destroy');
Route::post('rpp/{rpp}/submit', [RppController::class, 'submit'])->name('rpp.submit');
Route::post('rpp/{rpp}/verify', [RppController::class, 'verify'])->name('rpp.verify');
