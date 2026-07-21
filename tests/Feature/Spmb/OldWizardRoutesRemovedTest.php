<?php
// tests/Feature/Spmb/OldWizardRoutesRemovedTest.php

use Illuminate\Support\Facades\Route;

it('confirms every old anonymous wizard route name is gone after the account-first migration', function () {
    $oldRouteNames = [
        'spmb.mulai',
        'spmb.mulai.store',
        'spmb.verifikasi-otp',
        'spmb.verifikasi-otp.store',
        'spmb.data-diri',
        'spmb.data-diri.cek-nik',
        'spmb.data-diri.store',
        'spmb.formulir-tambahan',
        'spmb.formulir-tambahan.store',
        'spmb.dokumen',
        'spmb.dokumen.store',
        'spmb.review',
        'spmb.submit',
        'spmb.berhasil',
    ];

    foreach ($oldRouteNames as $name) {
        expect(Route::has($name))->toBeFalse("Expected route [{$name}] to no longer be registered.");
    }
});

it('confirms every new authenticated wizard route name is registered', function () {
    $newRouteNames = [
        'portal.wizard.data-diri',
        'portal.wizard.data-diri.cek-nik',
        'portal.wizard.data-diri.store',
        'portal.wizard.formulir-tambahan',
        'portal.wizard.formulir-tambahan.store',
        'portal.wizard.dokumen',
        'portal.wizard.dokumen.store',
        'portal.wizard.review',
        'portal.wizard.submit',
        'portal.wizard.berhasil',
    ];

    foreach ($newRouteNames as $name) {
        expect(Route::has($name))->toBeTrue("Expected route [{$name}] to be registered.");
    }
});
