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

it('wraps the Bisa Dicicil field in a kategoriPpdb-only Alpine template on the create page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // The 2026-09-02 UI restriction hides "Bisa Dicicil" for every kategori except PPDB
    // (pendaftaran/daftar_ulang) -- backend (bisa_dicicil column, PembayaranService, the
    // recalc engine's cicilan guard) stays generic per the deliberate decision to leave
    // the backend as-is and restrict only the client-side form.
    $response->assertSeeInOrder(['x-if="kategoriPpdb"', 'name="bisa_dicicil"'], false);
});

it('wraps the Bisa Dicicil field in the same kategoriPpdb-only template on the edit page for an existing non-ppdb jenis tagihan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSeeInOrder(['x-if="kategoriPpdb"', 'name="bisa_dicicil"'], false);
});
