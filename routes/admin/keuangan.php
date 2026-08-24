<?php

use App\Http\Controllers\Lembaga\Keuangan\JenisTagihanController;
use App\Http\Controllers\Lembaga\Keuangan\JenisTagihanMonitoringController;
use App\Http\Controllers\Lembaga\Keuangan\KategoriKeringananController;
use App\Http\Controllers\Lembaga\Keuangan\ManualPaymentController;
use App\Http\Controllers\Lembaga\Keuangan\PembayaranController;
use App\Http\Controllers\Lembaga\Keuangan\TagihanController;
use App\Http\Controllers\Lembaga\Keuangan\VirtualAccountController;
use Illuminate\Support\Facades\Route;

Route::get('jenis-tagihan/create', [JenisTagihanController::class, 'create'])->name('jenis-tagihan.create');
Route::get('jenis-tagihan', [JenisTagihanController::class, 'index'])->name('jenis-tagihan.index');
Route::post('jenis-tagihan', [JenisTagihanController::class, 'store'])->name('jenis-tagihan.store');
Route::get('jenis-tagihan/{jenisTagihan}/edit', [JenisTagihanController::class, 'edit'])->name('jenis-tagihan.edit');
Route::put('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'update'])->name('jenis-tagihan.update');
Route::delete('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'destroy'])->name('jenis-tagihan.destroy');
Route::post('jenis-tagihan/{jenisTagihan}/proses', [JenisTagihanController::class, 'prosesTagihan'])->name('jenis-tagihan.proses');
Route::get('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'nominal'])->name('jenis-tagihan.nominal');
Route::post('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'simpanNominal'])->name('jenis-tagihan.nominal.store');

Route::get('jenis-tagihan/{jenisTagihan}/monitoring', [JenisTagihanMonitoringController::class, 'index'])->name('jenis-tagihan.monitoring.index');
Route::post('jenis-tagihan/{jenisTagihan}/batal-tagihan/{tagihan}', [JenisTagihanMonitoringController::class, 'batalTagihan'])->name('jenis-tagihan.monitoring.batal');

Route::post('kategori-keringanan', [KategoriKeringananController::class, 'store'])->name('kategori-keringanan.store');

Route::get('tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
Route::get('tagihan/data', [TagihanController::class, 'data'])->name('tagihan.data');
Route::post('tagihan/{tagihan}/skema-cicilan', [TagihanController::class, 'buatSkemaCicilan'])->name('tagihan.skema-cicilan.store');
Route::post('skema-cicilan/{skemaCicilan}/nominal', [TagihanController::class, 'simpanNominalCicilan'])->name('skema-cicilan.nominal.store');
Route::post('tagihan/{tagihan}/catat-manual', [TagihanController::class, 'catatManualTagihan'])->name('tagihan.catat-manual');
Route::post('cicilan/{cicilan}/catat-manual', [TagihanController::class, 'catatManualCicilan'])->name('cicilan.catat-manual');

Route::get('pembayaran', [PembayaranController::class, 'index'])->name('pembayaran.index');
Route::get('pembayaran/data', [PembayaranController::class, 'data'])->name('pembayaran.data');
Route::post('pembayaran/{pembayaran}/verifikasi', [PembayaranController::class, 'verifikasi'])->name('pembayaran.verifikasi');

Route::get('manual-payment', [ManualPaymentController::class, 'index'])->name('manual-payment.index');
Route::post('manual-payment/{manualPaymentRequest}/approve', [ManualPaymentController::class, 'approve'])->name('manual-payment.approve');
Route::post('manual-payment/{manualPaymentRequest}/reject', [ManualPaymentController::class, 'reject'])->name('manual-payment.reject');

Route::get('virtual-account', [VirtualAccountController::class, 'index'])->name('virtual-account.index');
Route::get('virtual-account/{siswa}/riwayat', [VirtualAccountController::class, 'riwayat'])->name('virtual-account.riwayat');
Route::get('virtual-account/calon', [VirtualAccountController::class, 'calonGenerate'])->name('virtual-account.calon');
Route::post('virtual-account/generate', [VirtualAccountController::class, 'generate'])->name('virtual-account.generate');
Route::get('virtual-account/export', [VirtualAccountController::class, 'export'])->name('virtual-account.export');
