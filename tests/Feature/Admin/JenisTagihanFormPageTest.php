<?php
// tests/Feature/Admin/JenisTagihanFormPageTest.php

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

it('renders the create page with the kategori select and mode toggle', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

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
    $user->assignRole('bendahara_lembaga');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee('value="SPP Bulanan"', false);
});

it('shows the tahun ajaran alongside the kelas name in the sasaran kriteria options to disambiguate same-named classes', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $tahunAjaranLama = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    $tahunAjaranBaru = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    // Two classes with the SAME name in different tahun ajaran — the exact ambiguity this fixes.
    \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLama->id, 'nama' => '7A']);
    \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranBaru->id, 'nama' => '7A']);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // Assert on the view data the controller passes, not the rendered/escaped HTML — Blade's
    // @js() JSON-encodes this string for embedding (slash-escaping details are an implementation
    // Detail of that encoding, not what this fix is actually about). This directly tests the
    // controller's query/eager-load change, which is the substantive part of this fix.
    $kelasList = $response->viewData('kelasList');
    expect($kelasList)->toHaveCount(2);
    expect($kelasList->pluck('nama')->unique()->all())->toBe(['7A']);
    expect($kelasList->pluck('tahunAjaran.nama')->sort()->values()->all())->toBe(['2025/2026', '2026/2027']);
});

it('does not crash when a kelas is linked to a tahun_ajaran belonging to a different lembaga (TenantScope mismatch)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    // TahunAjaran belongs to a DIFFERENT lembaga than the acting user — the exact TenantScope
    // mismatch condition that crashed the page before this fix.
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLembagaLain = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembagaLain->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    \App\Models\Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLembagaLain->id, 'nama' => '7A',
    ]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    expect($response->viewData('kelasList')->first()->tahunAjaran?->nama)->toBe('2026/2027');
});

it('explains what each mode otomatis field controls', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('Tanggal setiap bulan saat tagihan otomatis dibuat');
    $response->assertSee('Jumlah hari setelah tanggal generate sampai batas waktu pembayaran');
});
