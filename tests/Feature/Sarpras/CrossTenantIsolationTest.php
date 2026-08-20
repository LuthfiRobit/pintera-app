<?php

namespace Tests\Feature\Sarpras;

use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Enums\KondisiAset;
use App\Domains\Sarpras\Enums\SumberPerolehanAset;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userLembagaLain;

    private Gedung $gedung;

    private Ruangan $ruangan;

    private KategoriAset $kategori;

    private AsetBarang $aset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Permata']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT Permata', 'jenjang' => 'SD', 'npsn' => '111', 'status_aktif' => true]);
        $lembagaLain = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'SMPIT Permata', 'jenjang' => 'SMP', 'npsn' => '222', 'status_aktif' => true]);

        $role = Role::firstOrCreate(['name' => 'admin_sarpras', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo([
            'sarpras.gedung.view', 'sarpras.gedung.manage',
            'sarpras.ruangan.view', 'sarpras.ruangan.manage',
            'sarpras.kategori.view', 'sarpras.kategori.manage',
            'sarpras.aset.view', 'sarpras.aset.manage',
        ]);

        $this->userLembagaLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
        $this->userLembagaLain->assignRole($role);

        $this->gedung = Gedung::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_gedung' => 'GD-RAHASIA',
            'nama_gedung' => 'Gedung Rahasia Lembaga 1',
            'jumlah_lantai' => 2,
            'is_aktif' => true,
        ]);

        $this->ruangan = Ruangan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'gedung_id' => $this->gedung->id,
            'kode_ruangan' => 'R-RAHASIA',
            'nama_ruangan' => 'Ruang Rahasia Lembaga 1',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::KelasTeori,
            'is_shared' => false,
            'is_aktif' => true,
        ]);

        $this->kategori = KategoriAset::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_kategori' => 'KAT-RAHASIA',
            'nama_kategori' => 'Kategori Rahasia Lembaga 1',
        ]);

        $this->aset = AsetBarang::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kategori_aset_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'kode_inventaris' => 'INV-RAHASIA',
            'nama_barang' => 'Aset Rahasia Lembaga 1',
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'qty' => 1,
            'satuan' => 'unit',
            'kondisi' => KondisiAset::Baik,
            'sumber_perolehan' => SumberPerolehanAset::BeliLembaga,
        ]);
    }

    public function test_user_lembaga_lain_tidak_bisa_akses_ruangan_single_resource(): void
    {
        $this->actingAs($this->userLembagaLain)->get(route('admin.sarpras.ruangan.show', $this->ruangan))->assertNotFound();
        $this->actingAs($this->userLembagaLain)->get(route('admin.sarpras.ruangan.edit', $this->ruangan))->assertNotFound();
        $this->actingAs($this->userLembagaLain)->put(route('admin.sarpras.ruangan.update', $this->ruangan), [
            'gedung_id' => $this->gedung->id,
            'kode_ruangan' => 'DIUBAH',
            'nama_ruangan' => 'Diubah Paksa',
            'jenis_ruangan' => JenisRuangan::KelasTeori->value,
        ])->assertNotFound();
        $this->actingAs($this->userLembagaLain)->delete(route('admin.sarpras.ruangan.destroy', $this->ruangan))->assertNotFound();

        $this->assertDatabaseHas('ruangan', ['id' => $this->ruangan->id, 'kode_ruangan' => 'R-RAHASIA']);
    }

    public function test_user_lembaga_lain_tidak_bisa_akses_gedung_single_resource(): void
    {
        $this->actingAs($this->userLembagaLain)->get(route('admin.sarpras.gedung.edit', $this->gedung))->assertNotFound();
        $this->actingAs($this->userLembagaLain)->put(route('admin.sarpras.gedung.update', $this->gedung), [
            'kode_gedung' => 'DIUBAH',
            'nama_gedung' => 'Diubah Paksa',
        ])->assertNotFound();
        $this->actingAs($this->userLembagaLain)->delete(route('admin.sarpras.gedung.destroy', $this->gedung))->assertNotFound();

        $this->assertDatabaseHas('gedung', ['id' => $this->gedung->id, 'kode_gedung' => 'GD-RAHASIA']);
    }

    public function test_user_lembaga_lain_tidak_bisa_akses_aset_single_resource(): void
    {
        $this->actingAs($this->userLembagaLain)->get(route('admin.sarpras.aset.show', $this->aset))->assertNotFound();
        $this->actingAs($this->userLembagaLain)->get(route('admin.sarpras.aset.edit', $this->aset))->assertNotFound();
        $this->actingAs($this->userLembagaLain)->put(route('admin.sarpras.aset.update', $this->aset), [
            'kategori_aset_id' => $this->kategori->id,
            'ruangan_id' => $this->ruangan->id,
            'kode_inventaris' => 'DIUBAH',
            'nama_barang' => 'Diubah Paksa',
            'tipe_pencatatan' => TipePencatatanAset::Unit->value,
            'qty' => 1,
            'satuan' => 'unit',
            'kondisi' => KondisiAset::Baik->value,
            'sumber_perolehan' => SumberPerolehanAset::BeliLembaga->value,
        ])->assertNotFound();
        $this->actingAs($this->userLembagaLain)->delete(route('admin.sarpras.aset.destroy', $this->aset))->assertNotFound();

        $this->assertDatabaseHas('aset_barang', ['id' => $this->aset->id, 'kode_inventaris' => 'INV-RAHASIA']);
    }

    public function test_user_lembaga_lain_tidak_bisa_menghapus_kategori_aset(): void
    {
        $this->actingAs($this->userLembagaLain)->delete(route('admin.sarpras.kategori.destroy', $this->kategori))->assertNotFound();

        $this->assertDatabaseHas('kategori_aset', ['id' => $this->kategori->id, 'kode_kategori' => 'KAT-RAHASIA']);
    }
}
