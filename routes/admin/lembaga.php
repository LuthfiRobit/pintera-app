<?php

use App\Http\Controllers\Admin\Lembaga\DataPeriodikController as LembagaDataPeriodikController;
use App\Http\Controllers\Admin\Lembaga\EkstrakurikulerController as LembagaEkstrakurikulerController;
use App\Http\Controllers\Admin\Lembaga\LayananKhususController as LembagaLayananKhususController;
use App\Http\Controllers\Admin\Lembaga\ProgramInklusiController as LembagaProgramInklusiController;
use App\Http\Controllers\Admin\LembagaController;
use Illuminate\Support\Facades\Route;

Route::resource('lembaga', LembagaController::class)->except(['show', 'destroy']);
Route::get('pengaturan-yayasan', [\App\Http\Controllers\Admin\YayasanSettingController::class, 'edit'])->name('yayasan.edit');
Route::put('pengaturan-yayasan', [\App\Http\Controllers\Admin\YayasanSettingController::class, 'update'])->name('yayasan.update');
Route::prefix('lembaga/{lembaga}')->name('lembaga.')->group(function () {
    Route::post('data-periodik', [LembagaDataPeriodikController::class, 'store'])->name('data-periodik.store');
    Route::put('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'update'])->name('data-periodik.update');
    Route::delete('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'destroy'])->name('data-periodik.destroy');

    Route::post('ekstrakurikuler', [LembagaEkstrakurikulerController::class, 'store'])->name('ekstrakurikuler.store');
    Route::put('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'update'])->name('ekstrakurikuler.update');
    Route::delete('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'destroy'])->name('ekstrakurikuler.destroy');

    Route::post('layanan-khusus', [LembagaLayananKhususController::class, 'store'])->name('layanan-khusus.store');
    Route::put('layanan-khusus/{layananKhusus}', [LembagaLayananKhususController::class, 'update'])->name('layanan-khusus.update');
    Route::delete('layanan-khusus/{layananKhusus}', [LembagaLayananKhususController::class, 'destroy'])->name('layanan-khusus.destroy');

    Route::post('program-inklusi', [LembagaProgramInklusiController::class, 'store'])->name('program-inklusi.store');
    Route::put('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'update'])->name('program-inklusi.update');
    Route::delete('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'destroy'])->name('program-inklusi.destroy');
});
