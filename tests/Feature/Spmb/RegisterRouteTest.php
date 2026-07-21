<?php
// tests/Feature/Spmb/RegisterRouteTest.php

use Illuminate\Support\Facades\Route;

it('exposes a spmb.register route pointing at the same controller as portal.register', function () {
    expect(Route::has('spmb.register'))->toBeTrue();

    $response = $this->get(route('spmb.register'));

    $response->assertOk();
    $response->assertViewIs('portal.auth.register');
});

it('redirects the SPMB daftar-jalur action to spmb.register now that the route exists', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $response = $this->post(route('spmb.jalur.daftar', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $response->assertRedirect(route('spmb.register'));
    $response->assertSessionHas('spmb_pilihan.lembaga_id', $lembaga->id);
    $response->assertSessionHas('spmb_pilihan.jalur_id', $jalur->id);
});
