<?php

// tests/Feature/Keuangan/SiswaKeringananControllerTest.php

use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

// TenantScope filters {siswa}/{siswaKeringanan} route-model-binding by the acting user's
// own lembaga_id, so the manager must share the target siswa's lembaga_id or binding 404s
// before the controller ever runs.
function actingAsSiswaKeringananManager(?int $lembagaId = null): User
{
    $admin = User::factory()->create(['lembaga_id' => $lembagaId]);
    $admin->assignRole('bendahara_lembaga');

    return $admin;
}

it('admin can assign a keringanan category to a siswa', function () {
    $siswa = Siswa::factory()->create();
    $admin = actingAsSiswaKeringananManager($siswa->lembaga_id);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->toDateString(),
    ])->assertRedirect();

    $this->assertDatabaseHas('siswa_keringanan', [
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
    ]);
});

it('rejects a kategori keringanan belonging to a different lembaga', function () {
    $siswa = Siswa::factory()->create();
    $admin = actingAsSiswaKeringananManager($siswa->lembaga_id);
    $kategoriLembagaLain = KategoriKeringanan::factory()->create(); // different lembaga_id

    $this->actingAs($admin)->postJson(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategoriLembagaLain->id,
        'berlaku_dari' => now()->toDateString(),
    ])->assertStatus(422);
});

it('rejects berlaku_sampai before berlaku_dari', function () {
    $siswa = Siswa::factory()->create();
    $admin = actingAsSiswaKeringananManager($siswa->lembaga_id);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);

    $this->actingAs($admin)->postJson(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->toDateString(),
        'berlaku_sampai' => now()->subDay()->toDateString(),
    ])->assertStatus(422);
});

it('admin can revoke an assigned keringanan (hard delete)', function () {
    $siswaKeringanan = SiswaKeringanan::factory()->create();
    $admin = actingAsSiswaKeringananManager($siswaKeringanan->siswa->lembaga_id);

    $this->actingAs($admin)->delete(route('admin.siswa-keringanan.destroy', $siswaKeringanan))->assertRedirect();

    $this->assertDatabaseMissing('siswa_keringanan', ['id' => $siswaKeringanan->id]);
});
