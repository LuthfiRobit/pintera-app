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
    $user->assignRole('admin_keuangan');
    KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // The 2026-08-11 "gold standard" UI rework renamed this section from "4. Keringanan" to
    // "Keringanan Tagihan" (see .agents/logs/2026-08-11-jenis-tagihan-ui-ux.md).
    $response->assertSee('Keringanan Tagihan');
    $response->assertSee('Yatim Piatu');
});

it('pre-fills existing keringanan rules on the edit page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
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
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee(":placeholder=\"rule.tipe_potongan === 'persen' ? 'Contoh: 20 (%)' : 'Contoh: 50000 (Rupiah)'\"", false);
});
