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
use Database\Seeders\SarprasPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KirPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_stream_kir_pdf_for_room(): void
    {
        $this->seed(SarprasPermissionSeeder::class);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan Utama']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT Al-Hikmah', 'jenjang' => 'SD', 'npsn' => '123', 'status_aktif' => true]);

        $role = Role::firstOrCreate(['name' => 'admin_sarpras', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);
        $user->givePermissionTo(['sarpras.kir.export', 'sarpras.ruangan.view']);

        $gedung = Gedung::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_gedung' => 'GD-1',
            'nama_gedung' => 'Gedung A',
        ]);

        $ruangan = Ruangan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-101',
            'nama_ruangan' => 'Ruang 101',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::KelasTeori,
        ]);

        $kategori = KategoriAset::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_kategori' => 'MEB',
            'nama_kategori' => 'Mebel',
        ]);

        AsetBarang::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kategori_aset_id' => $kategori->id,
            'ruangan_id' => $ruangan->id,
            'kode_inventaris' => 'INV/MEB/001',
            'nama_barang' => 'Kursi Siswa',
            'tipe_pencatatan' => TipePencatatanAset::Batch,
            'qty' => 36,
            'kondisi' => KondisiAset::Baik,
            'sumber_perolehan' => SumberPerolehanAset::BeliLembaga,
        ]);

        $response = $this->actingAs($user)->get(route('admin.sarpras.kir.export', $ruangan));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }
}
