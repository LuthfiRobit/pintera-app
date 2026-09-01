<?php

use App\Domains\Keuangan\Models\JenisTagihan;
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

it('renders the keringanan section with existing kategori keringanan options', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // The 2026-09-01 Tipe Penjadwalan UI polish pass renamed this section from
    // "Keringanan Tagihan" to "Keringanan & Potongan Biaya" (more descriptive).
    $response->assertSee('Keringanan & Potongan Biaya', false);
    $response->assertSee('Yatim Piatu');
});

it('pre-fills existing keringanan rules on the edit page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false]);
    $kategori = KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);
    $jenisTagihan->keringananRules()->create(['kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'persen', 'nilai' => 50]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee((string) $kategori->id, false);
});

it('shows a reactive placeholder on nilai potongan indicating rupiah vs percent', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee(":placeholder=\"rule.tipe_potongan === 'persen' ? '0-100' : '0'\"", false);
});
