<?php
// tests/Feature/Admin/KategoriKeringananTest.php

use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('lets bendahara_lembaga create a kategori keringanan inline, scoped to their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->postJson(route('admin.kategori-keringanan.store'), [
        'nama' => 'Yatim Piatu',
        'keterangan' => 'Anak yatim piatu terdaftar',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.nama', 'Yatim Piatu');
    $kategori = KategoriKeringanan::where('nama', 'Yatim Piatu')->firstOrFail();
    expect($kategori->lembaga_id)->toBe($lembaga->id);
});

it('denies kategori keringanan creation without jenis-tagihan.create permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->postJson(route('admin.kategori-keringanan.store'), ['nama' => 'X'])
        ->assertForbidden();
});

it('rejects a duplicate kategori keringanan name within the same lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $this->actingAs($user)->postJson(route('admin.kategori-keringanan.store'), ['nama' => 'Yatim Piatu'])
        ->assertStatus(422);
});
