<?php

use App\Http\Controllers\Admin\DokumenSyaratController;
use App\Http\Controllers\Admin\FormulirFieldController;
use App\Http\Controllers\Admin\GelombangPpdbController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\JalurPpdbController;
use App\Http\Controllers\Admin\JenisTagihanController;
use App\Http\Controllers\Admin\JenisTesMasterController;
use App\Http\Controllers\Admin\KalenderAkademikController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\LembagaController;
use App\Http\Controllers\Admin\MataPelajaranController;
use App\Http\Controllers\Admin\PembayaranController;
use App\Http\Controllers\Admin\PendaftaranAdminController;
use App\Http\Controllers\Admin\PendaftaranSiswaController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SeleksiController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\SiswaController;
use App\Http\Controllers\Admin\SiswaImportController;
use App\Http\Controllers\Admin\SkPpdbController;
use App\Http\Controllers\Admin\SpmbKonfigurasiController;
use App\Http\Controllers\Admin\TagihanController;
use App\Http\Controllers\Admin\TahunAjaranController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');
    Route::get('roles/permissions-catalog', [RoleController::class, 'permissionsCatalog'])->name('roles.permissions-catalog');
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::resource('lembaga', LembagaController::class)->except(['show', 'destroy']);
    Route::resource('guru', GuruController::class)->except(['show', 'destroy']);
    Route::resource('mata-pelajaran', MataPelajaranController::class)->except(['show', 'destroy']);
    Route::resource('kelas', KelasController::class)->parameters(['kelas' => 'kelas'])->except(['show', 'destroy']);
    Route::resource('siswa', SiswaController::class)->except(['show', 'destroy']);
    Route::resource('kalender-akademik', KalenderAkademikController::class)->except(['show', 'destroy']);
    Route::get('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'index'])->name('siswa.spmb-daftar.index');
    Route::post('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'store'])->name('siswa.spmb-daftar.store');
    Route::get('siswa-import', [SiswaImportController::class, 'index'])->name('siswa.import.index');
    Route::get('siswa-import/template', [SiswaImportController::class, 'template'])->name('siswa.import.template');
    Route::post('siswa-import/preview', [SiswaImportController::class, 'preview'])->name('siswa.import.preview');
    Route::post('siswa-import/confirm', [SiswaImportController::class, 'confirm'])->name('siswa.import.confirm');

    Route::get('tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
    Route::get('tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('tahun-ajaran.create');
    Route::post('tahun-ajaran', [TahunAjaranController::class, 'store'])->name('tahun-ajaran.store');
    Route::patch('tahun-ajaran/{tahunAjaran}/activate', [TahunAjaranController::class, 'activate'])->name('tahun-ajaran.activate');

    Route::post('semester', [SemesterController::class, 'store'])->name('semester.store');
    Route::patch('semester/{semester}/activate', [SemesterController::class, 'activate'])->name('semester.activate');

    Route::get('jenis-tes', [JenisTesMasterController::class, 'index'])->name('jenis-tes.index');
    Route::post('jenis-tes', [JenisTesMasterController::class, 'store'])->name('jenis-tes.store');
    Route::put('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'update'])->name('jenis-tes.update');
    Route::delete('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'destroy'])->name('jenis-tes.destroy');

    Route::resource('gelombang-ppdb', GelombangPpdbController::class)->except(['show', 'destroy']);

    Route::resource('jalur-ppdb', JalurPpdbController::class)->except(['show', 'destroy']);

    Route::post('formulir-field', [FormulirFieldController::class, 'store'])->name('formulir-field.store');
    Route::delete('formulir-field/{formulirField}', [FormulirFieldController::class, 'destroy'])->name('formulir-field.destroy');

    Route::post('dokumen-syarat', [DokumenSyaratController::class, 'store'])->name('dokumen-syarat.store');
    Route::delete('dokumen-syarat/{dokumenSyarat}', [DokumenSyaratController::class, 'destroy'])->name('dokumen-syarat.destroy');

    Route::post('seleksi', [SeleksiController::class, 'store'])->name('seleksi.store');
    Route::delete('seleksi/{seleksi}', [SeleksiController::class, 'destroy'])->name('seleksi.destroy');

    Route::post('spmb-konfigurasi/duplikasi', [SpmbKonfigurasiController::class, 'duplikasi'])->name('spmb-konfigurasi.duplikasi');

    Route::get('spmb-pendaftaran', [PendaftaranAdminController::class, 'index'])->name('spmb-pendaftaran.index');
    Route::get('spmb-pendaftaran/data', [PendaftaranAdminController::class, 'data'])->name('spmb-pendaftaran.data');
    Route::get('spmb-pendaftaran/{pendaftaran}', [PendaftaranAdminController::class, 'show'])->name('spmb-pendaftaran.show');
    Route::post('spmb-pendaftaran/{pendaftaran}/dokumen/{dokumen}', [PendaftaranAdminController::class, 'verifikasiDokumen'])->name('spmb-pendaftaran.verifikasi-dokumen');
    Route::post('spmb-pendaftaran/{pendaftaran}/nilai', [PendaftaranAdminController::class, 'simpanNilai'])->name('spmb-pendaftaran.nilai');
    Route::post('spmb-pendaftaran/{pendaftaran}/keputusan', [PendaftaranAdminController::class, 'tetapkanKeputusan'])->name('spmb-pendaftaran.keputusan');
    Route::post('spmb-pendaftaran/{pendaftaran}/tagihan-susulan', [TagihanController::class, 'buatSusulan'])->name('tagihan.susulan');
    Route::get('spmb-pendaftaran-nilai-massal', [PendaftaranAdminController::class, 'nilaiMassal'])->name('spmb-pendaftaran.nilai-massal');
    Route::post('spmb-pendaftaran-nilai-massal', [PendaftaranAdminController::class, 'simpanNilaiMassal'])->name('spmb-pendaftaran.nilai-massal.store');

    Route::get('sk-ppdb/create', [SkPpdbController::class, 'create'])->name('sk-ppdb.create');
    Route::post('sk-ppdb', [SkPpdbController::class, 'store'])->name('sk-ppdb.store');

    Route::get('jenis-tagihan', [JenisTagihanController::class, 'index'])->name('jenis-tagihan.index');
    Route::post('jenis-tagihan', [JenisTagihanController::class, 'store'])->name('jenis-tagihan.store');
    Route::put('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'update'])->name('jenis-tagihan.update');
    Route::delete('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'destroy'])->name('jenis-tagihan.destroy');
    Route::get('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'nominal'])->name('jenis-tagihan.nominal');
    Route::post('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'simpanNominal'])->name('jenis-tagihan.nominal.store');

    Route::get('tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
    Route::get('tagihan/data', [TagihanController::class, 'data'])->name('tagihan.data');
    Route::post('tagihan/{tagihan}/skema-cicilan', [TagihanController::class, 'buatSkemaCicilan'])->name('tagihan.skema-cicilan.store');
    Route::post('skema-cicilan/{skemaCicilan}/nominal', [TagihanController::class, 'simpanNominalCicilan'])->name('skema-cicilan.nominal.store');
    Route::post('tagihan/{tagihan}/catat-manual', [TagihanController::class, 'catatManualTagihan'])->name('tagihan.catat-manual');
    Route::post('cicilan/{cicilan}/catat-manual', [TagihanController::class, 'catatManualCicilan'])->name('cicilan.catat-manual');

    Route::get('pembayaran', [PembayaranController::class, 'index'])->name('pembayaran.index');
    Route::get('pembayaran/data', [PembayaranController::class, 'data'])->name('pembayaran.data');
    Route::post('pembayaran/{pembayaran}/verifikasi', [PembayaranController::class, 'verifikasi'])->name('pembayaran.verifikasi');
});
