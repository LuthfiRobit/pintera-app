<?php

use App\Http\Controllers\Admin\NilaiAnakController;
use Illuminate\Support\Facades\Route;

Route::get('nilai-anak', [NilaiAnakController::class, 'index'])->name('nilai-anak.index');
Route::get('nilai-anak/{siswa}/unduh-rapor', [NilaiAnakController::class, 'unduhRapor'])->name('nilai-anak.unduh-rapor');
