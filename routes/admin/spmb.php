<?php

use App\Http\Controllers\Admin\DokumenSyaratController;
use App\Http\Controllers\Admin\FormulirFieldController;
use App\Http\Controllers\Admin\GelombangPpdbController;
use App\Http\Controllers\Admin\JalurPpdbController;
use App\Http\Controllers\Admin\JenisTesMasterController;
use App\Http\Controllers\Admin\PendaftaranAdminController;
use App\Http\Controllers\Admin\SeleksiController;
use App\Http\Controllers\Admin\SkPpdbController;
use App\Http\Controllers\Admin\SpmbKonfigurasiController;
use App\Http\Controllers\Admin\TagihanController;
use Illuminate\Support\Facades\Route;

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
