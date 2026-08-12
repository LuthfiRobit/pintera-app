<?php
// tests/Feature/Admin/JenisTagihanFormPageTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the create page with the kategori select and mode toggle', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // The 2026-08-11 "gold standard" UI rework replaced the page's H1 ("Tambah Jenis Tagihan")
    // with a breadcrumb-only "Tambah"/"Edit" label — assert on that specific breadcrumb markup
    // instead of the removed heading (see .agents/logs/2026-08-11-jenis-tagihan-ui-ux.md).
    $response->assertSee('<b class="font-semibold text-gray-900">Tambah</b>', false);
    $response->assertSee('name="kategori"', false);
});

it('renders the edit page pre-filled with the existing jenis tagihan nama', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee('value="SPP Bulanan"', false);
});
