<?php

namespace Tests\Feature\Sarpras;

use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GedungRuanganControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sarpras_can_create_gedung_and_ruangan(): void
    {
        $this->seed(PermissionSeeder::class);

        $yayasan = Yayasan::create(['nama' => 'Yayasan']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT', 'jenjang' => 'SD', 'npsn' => '123', 'status_aktif' => true]);

        $role = Role::firstOrCreate(['name' => 'admin_sarpras', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);
        $user->givePermissionTo(['sarpras.gedung.view', 'sarpras.gedung.manage', 'sarpras.ruangan.view', 'sarpras.ruangan.manage']);

        // Create Gedung
        $responseGedung = $this->actingAs($user)->post(route('admin.sarpras.gedung.store'), [
            'kode_gedung' => 'GD-A',
            'nama_gedung' => 'Gedung Utama',
            'jumlah_lantai' => 3,
        ]);

        $responseGedung->assertRedirect(route('admin.sarpras.gedung.index'));
        $this->assertDatabaseHas('gedung', [
            'kode_gedung' => 'GD-A',
            'lembaga_id' => $lembaga->id,
        ]);

        $gedung = Gedung::where('kode_gedung', 'GD-A')->first();

        // Create Ruangan
        $responseRuangan = $this->actingAs($user)->post(route('admin.sarpras.ruangan.store'), [
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-101',
            'nama_ruangan' => 'Ruang 101',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::KelasTeori->value,
            'kapasitas_siswa' => 36,
        ]);

        $responseRuangan->assertRedirect(route('admin.sarpras.ruangan.index'));
        $this->assertDatabaseHas('ruangan', [
            'kode_ruangan' => 'R-101',
            'gedung_id' => $gedung->id,
            'lembaga_id' => $lembaga->id,
        ]);
    }
}
