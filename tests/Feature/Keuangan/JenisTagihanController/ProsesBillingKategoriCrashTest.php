<?php

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

it('returns 422, not a fatal error, when processing billing for a PPDB-category jenis tagihan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'pendaftaran']);

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.proses', $jenisTagihan));

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Jenis tagihan berkategori Pendaftaran tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB.']);
});
