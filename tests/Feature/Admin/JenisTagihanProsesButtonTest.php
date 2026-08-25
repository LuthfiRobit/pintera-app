<?php
// tests/Feature/Admin/JenisTagihanProsesButtonTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the Proses Tagihan action for a non-ppdb jenis_tagihan on the index page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp']);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.index'));

    $response->assertOk();
    $response->assertSee('prosesUrl', false);
});

it('renders the Monitoring action for a non-ppdb jenis_tagihan on the index page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp']);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.index'));

    $response->assertOk();
    $response->assertSee('monitoringUrl', false);
});
