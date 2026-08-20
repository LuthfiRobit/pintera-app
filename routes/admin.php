<?php

use App\Http\Controllers\Guru\AsesmenController;
use App\Http\Controllers\Guru\Akademik\JurnalKbmController;
use App\Http\Controllers\Guru\Akademik\RekapKehadiranController;
use App\Http\Controllers\Guru\RaporController as GuruRaporController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {

    require base_path('routes/admin/roles.php');
    require base_path('routes/admin/lembaga.php');
    require base_path('routes/admin/guru-data.php');
    require base_path('routes/admin/whatsapp-template.php');
    require base_path('routes/admin/akademik-master.php');
    require base_path('routes/admin/siswa.php');
    require base_path('routes/admin/spmb.php');
    require base_path('routes/admin/keuangan.php');
    require base_path('routes/admin/rpp.php');
    require base_path('routes/admin/penilaian-rapor.php');
    require base_path('routes/admin/kasus-admin.php');

    require base_path('routes/admin/sarpras.php');

    require base_path('routes/admin/pengadaan.php');
});

Route::middleware(['auth', 'verified'])->prefix('guru')->name('guru.')->group(function () {
    Route::get('jurnal-kbm', [JurnalKbmController::class, 'index'])->name('jurnal-kbm.index');
    Route::get('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'show'])->name('jurnal-kbm.show');
    Route::put('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'update'])->name('jurnal-kbm.update');
    Route::get('jurnal-kbm-rekap', [RekapKehadiranController::class, 'index'])->name('jurnal-kbm.rekap');

    Route::get('asesmen', [AsesmenController::class, 'index'])->name('asesmen.index');
    Route::get('asesmen/create', [AsesmenController::class, 'create'])->name('asesmen.create');
    Route::post('asesmen', [AsesmenController::class, 'store'])->name('asesmen.store');
    Route::get('asesmen/{asesmen}', [AsesmenController::class, 'show'])->name('asesmen.show');
    Route::put('asesmen/{asesmen}/nilai', [AsesmenController::class, 'updateNilai'])->name('asesmen.update-nilai');

    Route::get('komponen-penilaian', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'index'])->name('komponen-penilaian.index');
    Route::get('komponen-penilaian/create', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'create'])->name('komponen-penilaian.create');
    Route::post('komponen-penilaian', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');
    Route::get('komponen-penilaian/{komponenPenilaian}/edit', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
    Route::put('komponen-penilaian/{komponenPenilaian}', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
    Route::delete('komponen-penilaian/{komponenPenilaian}', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');

    Route::get('rapor', [GuruRaporController::class, 'index'])->name('rapor.catatan.index');
    Route::get('rapor/siswa/{siswa}', [GuruRaporController::class, 'edit'])->name('rapor.catatan.edit');
    Route::put('rapor/siswa/{siswa}', [GuruRaporController::class, 'update'])->name('rapor.catatan.update');
    Route::post('rapor/generate-narasi/{siswa}', [GuruRaporController::class, 'generateNarasi'])->name('rapor.catatan.generate-narasi');
    Route::post('rapor/ajukan', [GuruRaporController::class, 'ajukan'])->name('rapor.pengajuan.submit');
    Route::get('rapor/cetak/{siswa}', [GuruRaporController::class, 'cetak'])->name('rapor.cetak');
});
