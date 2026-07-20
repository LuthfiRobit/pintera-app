<?php
// tests/Feature/Spmb/WelcomeControllerTest.php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('lists every lembaga with a jenjang badge and status badge', function () {
    [$lembagaBuka] = buatLembagaDenganGelombangBuka();

    $yayasan = Yayasan::factory()->create();
    $lembagaTutup = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMA Contoh Tertutup', 'bentuk_pendidikan' => 'SMA']);

    $response = $this->get('/spmb');

    $response->assertOk();
    $response->assertSee($lembagaBuka->nama);
    $response->assertSee('Dibuka');
    $response->assertSee($lembagaTutup->nama);
    $response->assertSee('Ditutup');
});

it('does not hide a lembaga with no open gelombang', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Tanpa Gelombang']);

    $this->get('/spmb')->assertOk()->assertSee('SMP Tanpa Gelombang');
});

it('computes real summary counts in the hero panel, not hardcoded numbers', function () {
    [$lembagaSatu] = buatLembagaDenganGelombangBuka();
    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $response = $this->get('/spmb');

    $response->assertOk();
    $response->assertViewHas('jumlahLembaga', Lembaga::count());
    $response->assertViewHas('jumlahSedangBuka', 1);
});

it('renders the mobile nav toggle and both nav actions', function () {
    $this->get('/spmb')
        ->assertOk()
        ->assertSee('Masuk')
        ->assertSee('Daftar Akun');
});

it('renders a filter chip for every distinct jenjang plus a Semua chip', function () {
    [$lembagaSatu] = buatLembagaDenganGelombangBuka();
    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SMP']);

    $response = $this->get('/spmb');

    $response->assertOk();
    $response->assertSee('Semua');
    $response->assertSee($lembagaSatu->bentuk_pendidikan);
    $response->assertSee('SMP');
});

it('shows the nearest-closing open gelombang in the hero panel', function () {
    [$lembaga, , , $gelombang] = buatLembagaDenganGelombangBuka();

    $this->get('/spmb')
        ->assertOk()
        ->assertSee($lembaga->nama)
        ->assertSee($gelombang->nama);
});
