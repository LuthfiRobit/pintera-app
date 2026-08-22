<?php

use App\Http\Controllers\EmployeeQrCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('sdm')->name('sdm.')->group(function () {
    Route::get('qr-saya', [EmployeeQrCodeController::class, 'show'])->name('qr-saya');
    Route::post('qr-saya/generate', [EmployeeQrCodeController::class, 'generate'])->name('qr-saya.generate');
});
