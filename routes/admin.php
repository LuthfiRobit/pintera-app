<?php

use App\Http\Controllers\Admin\GelombangPpdbController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\JalurPpdbController;
use App\Http\Controllers\Admin\JenisTesMasterController;
use App\Http\Controllers\Admin\LembagaController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\TahunAjaranController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::resource('lembaga', LembagaController::class)->except(['show', 'destroy']);
    Route::resource('guru', GuruController::class)->except(['show', 'destroy']);

    Route::get('tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
    Route::get('tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('tahun-ajaran.create');
    Route::post('tahun-ajaran', [TahunAjaranController::class, 'store'])->name('tahun-ajaran.store');
    Route::patch('tahun-ajaran/{tahunAjaran}/activate', [TahunAjaranController::class, 'activate'])->name('tahun-ajaran.activate');

    Route::post('semester', [SemesterController::class, 'store'])->name('semester.store');
    Route::patch('semester/{semester}/activate', [SemesterController::class, 'activate'])->name('semester.activate');

    Route::get('jenis-tes', [JenisTesMasterController::class, 'index'])->name('jenis-tes.index');
    Route::post('jenis-tes', [JenisTesMasterController::class, 'store'])->name('jenis-tes.store');
    Route::delete('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'destroy'])->name('jenis-tes.destroy');

    Route::resource('gelombang-ppdb', GelombangPpdbController::class)->except(['show', 'destroy']);

    Route::resource('jalur-ppdb', JalurPpdbController::class)->except(['show', 'destroy']);
});
