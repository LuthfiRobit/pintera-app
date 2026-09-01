<?php

// tests/Feature/Keuangan/KeringananUiDiscountRegressionTest.php
//
// Closes the spec's explicit gap: before this branch, 0 tests exercised
// SiswaKeringanan created through a production code path (only via factory).
// This proves the new UI's writes (SiswaKeringananController::store, Task 22)
// actually feed the existing, unmodified discount engine
// (TagihanNominalResolver::resolve()) correctly, end to end.

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('a keringanan assigned through the new UI is correctly applied by TagihanNominalResolver', function () {
    $siswa = Siswa::factory()->create();

    // TenantScope filters {siswa} route-model-binding by the acting user's own
    // lembaga_id, so the manager must share the target siswa's lembaga_id
    // (see SiswaKeringananControllerTest for the original discovery of this gotcha).
    $admin = User::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $admin->assignRole('bendahara_lembaga');

    $jenisTagihan = JenisTagihan::factory()->create([
        'kategori' => 'spp',
        'default_amount' => 350000,
    ]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);

    JenisTagihanKeringanan::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'kategori_keringanan_id' => $kategori->id,
        'tipe_potongan' => 'fixed',
        'nilai' => 50000,
    ]);

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subDay()->toDateString(),
    ])->assertRedirect();

    $this->assertDatabaseHas('siswa_keringanan', [
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
    ]);

    $result = app(TagihanNominalResolver::class)->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(50000.0)
        ->and($result['discount_type'])->toBe('fixed')
        ->and($result['nominal'])->toBe(350000.0);
});
