<?php

// tests/Feature/Keuangan/SiswaKeringananTabViewTest.php

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

// bendahara_lembaga has siswa-keringanan.kelola but not siswa.edit.
function actingAsSiswaKeringananTabManager(?int $lembagaId = null): User
{
    $admin = User::factory()->create(['lembaga_id' => $lembagaId]);
    $admin->assignRole('bendahara_lembaga');

    return $admin;
}

// operator_akademik has both siswa.edit (siswa detail page) and siswa-keringanan.kelola.
function actingAsSiswaDetailAdmin(?int $lembagaId = null): User
{
    $admin = User::factory()->create(['lembaga_id' => $lembagaId]);
    $admin->assignRole('operator_akademik');

    return $admin;
}

it('siswa keringanan tab shows active keringanan and an assign form via the keringanan index route', function () {
    $siswa = Siswa::factory()->create();
    $admin = actingAsSiswaKeringananTabManager($siswa->lembaga_id);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $active = SiswaKeringanan::factory()->create([
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subMonth(),
        'berlaku_sampai' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.siswa.keringanan.index', $siswa));

    $response->assertOk();
    $response->assertSee($active->kategoriKeringanan->nama);
    $response->assertSee('kategori_keringanan_id', escape: false);
});

it('renders the keringanan tab on the siswa detail page', function () {
    $siswa = Siswa::factory()->create();
    $admin = actingAsSiswaDetailAdmin($siswa->lembaga_id);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $active = SiswaKeringanan::factory()->create([
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subMonth(),
        'berlaku_sampai' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.siswa.edit', $siswa));

    $response->assertOk();
    $response->assertSee('Keringanan', escape: false);
    $response->assertSee($active->kategoriKeringanan->nama);
    $response->assertSee('kategori_keringanan_id', escape: false);
});
