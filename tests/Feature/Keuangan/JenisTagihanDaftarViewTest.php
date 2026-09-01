<?php

// tests/Feature/Keuangan/JenisTagihanDaftarViewTest.php

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

it('jenis tagihan list shows the correct kategori column and action links per row', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $ppdb = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'pendaftaran']);
    $nonPpdb = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'spp']);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.index'));

    $response->assertOk();
    $response->assertSee('Pendaftaran');
    $response->assertSee('SPP');

    // PPDB row shows "Kelola Nominal", not the proses/monitoring links.
    $response->assertSee(route('admin.jenis-tagihan.nominal', $ppdb->id), false);
    $response->assertSee('Kelola Nominal');

    // Non-PPDB row shows the proses/monitoring links, not "Kelola Nominal".
    $response->assertSee(route('admin.jenis-tagihan.monitoring.index', $nonPpdb->id), false);
    $response->assertSee('Proses Tagihan');
    $response->assertSee('Monitoring');
});
