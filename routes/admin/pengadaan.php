<?php

use Illuminate\Support\Facades\Route;

// Pengadaan & LPJ Sarpras
Route::prefix('pengadaan')->name('pengadaan.')->group(function () {
    // Portal Lembaga
    Route::resource('proposal', \App\Http\Controllers\Lembaga\Pengadaan\PengajuanPengadaanController::class);
    Route::post('proposal/{proposal}/submit', [\App\Http\Controllers\Lembaga\Pengadaan\PengajuanPengadaanController::class, 'submit'])->name('proposal.submit');
    Route::get('lpj/{proposal}/create', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'create'])->name('lpj.create');
    Route::post('lpj/{proposal}', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'store'])->name('lpj.store');
    Route::get('lpj/{lpj}/staging-inventory', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'stagingInventory'])->name('lpj.staging-inventory');
    Route::post('lpj/{lpj}/convert-inventory', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'convertInventory'])->name('lpj.convert-inventory');

    // Portal Yayasan & Approval
    Route::get('inbox', [\App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController::class, 'index'])->name('inbox.index');
    Route::get('inbox/{proposal}', [\App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController::class, 'review'])->name('inbox.review');
    Route::post('inbox/{proposal}/decision', [\App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController::class, 'decision'])->name('inbox.decision');
    Route::get('disbursement', [\App\Http\Controllers\Yayasan\Pengadaan\DisbursementPengadaanController::class, 'index'])->name('disbursement.index');
    Route::post('disbursement/{proposal}', [\App\Http\Controllers\Yayasan\Pengadaan\DisbursementPengadaanController::class, 'store'])->name('disbursement.store');
    Route::get('audit-lpj', [\App\Http\Controllers\Yayasan\Pengadaan\AuditLpjController::class, 'index'])->name('audit-lpj.index');
    Route::get('audit-lpj/{lpj}', [\App\Http\Controllers\Yayasan\Pengadaan\AuditLpjController::class, 'show'])->name('audit-lpj.show');
    Route::post('audit-lpj/{lpj}/verify', [\App\Http\Controllers\Yayasan\Pengadaan\AuditLpjController::class, 'verify'])->name('audit-lpj.verify');
});
