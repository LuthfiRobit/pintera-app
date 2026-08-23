<?php

use App\Http\Controllers\Admin\JadwalPelajaranController;
use App\Http\Controllers\Admin\JamPelajaranController;
use App\Http\Controllers\Admin\KalenderAkademikController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Lembaga\Akademik\MataPelajaranController;
use App\Http\Controllers\Admin\PengaturanAkademikController;
use App\Http\Controllers\Admin\PolaJamController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\TahunAjaranController;
use Illuminate\Support\Facades\Route;

Route::resource('mata-pelajaran', MataPelajaranController::class)->except(['show', 'destroy']);
Route::resource('kelas', KelasController::class)->parameters(['kelas' => 'kelas'])->except(['show', 'destroy']);

Route::post('kalender-akademik', [KalenderAkademikController::class, 'store'])->name('kalender-akademik.store');
Route::put('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'update'])->name('kalender-akademik.update');
Route::delete('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'destroy'])->name('kalender-akademik.destroy');
Route::get('pengaturan/akademik', [PengaturanAkademikController::class, 'index'])->name('pengaturan.akademik.index');
Route::put('pengaturan/akademik/hari-aktif', [PengaturanAkademikController::class, 'updateHariAktif'])->name('pengaturan.akademik.hari-aktif');

Route::get('tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
Route::get('tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('tahun-ajaran.create');
Route::post('tahun-ajaran', [TahunAjaranController::class, 'store'])->name('tahun-ajaran.store');
Route::put('tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'update'])->name('tahun-ajaran.update');
Route::patch('tahun-ajaran/{tahunAjaran}/activate', [TahunAjaranController::class, 'activate'])->name('tahun-ajaran.activate');

Route::post('semester', [SemesterController::class, 'store'])->name('semester.store');
Route::patch('semester/{semester}/activate', [SemesterController::class, 'activate'])->name('semester.activate');

Route::get('pola-jam', [PolaJamController::class, 'index'])->name('pola-jam.index');
Route::get('pola-jam/create', [PolaJamController::class, 'create'])->name('pola-jam.create');
Route::post('pola-jam', [PolaJamController::class, 'store'])->name('pola-jam.store');
Route::get('pola-jam/{polaJam}/edit', [PolaJamController::class, 'edit'])->name('pola-jam.edit');
Route::put('pola-jam/{polaJam}', [PolaJamController::class, 'update'])->name('pola-jam.update');
Route::delete('pola-jam/{polaJam}', [PolaJamController::class, 'destroy'])->name('pola-jam.destroy');
Route::put('pola-jam/{polaJam}/assign-kelas', [PolaJamController::class, 'assignKelas'])->name('pola-jam.assign-kelas');
Route::post('pola-jam/{polaJam}/duplicate', [PolaJamController::class, 'duplicate'])->name('pola-jam.duplicate');
Route::post('jam-pelajaran', [JamPelajaranController::class, 'store'])->name('jam-pelajaran.store');
Route::get('jam-pelajaran/{jamPelajaran}/edit', [JamPelajaranController::class, 'edit'])->name('jam-pelajaran.edit');
Route::put('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'update'])->name('jam-pelajaran.update');
Route::delete('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'destroy'])->name('jam-pelajaran.destroy');

Route::get('jadwal-pelajaran', [JadwalPelajaranController::class, 'index'])->name('jadwal-pelajaran.index');
Route::get('jadwal-pelajaran/opsi', [JadwalPelajaranController::class, 'opsi'])->name('jadwal-pelajaran.opsi');
Route::get('jadwal-pelajaran/create', [JadwalPelajaranController::class, 'create'])->name('jadwal-pelajaran.create');
Route::post('jadwal-pelajaran', [JadwalPelajaranController::class, 'store'])->name('jadwal-pelajaran.store');
Route::get('jadwal-pelajaran/{jadwalPelajaran}/edit', [JadwalPelajaranController::class, 'edit'])->name('jadwal-pelajaran.edit');
Route::put('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'update'])->name('jadwal-pelajaran.update');
Route::delete('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('jadwal-pelajaran.destroy');
Route::post('jadwal-pelajaran/duplicate', [JadwalPelajaranController::class, 'duplicate'])->name('jadwal-pelajaran.duplicate');
