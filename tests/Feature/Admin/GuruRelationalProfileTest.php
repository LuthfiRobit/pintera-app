<?php

namespace Tests\Feature\Admin;

use App\Models\Guru;
use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\Lembaga;
use App\Models\RiwayatPendidikanGuru;
use App\Models\Role;
use App\Models\SertifikasiGuru;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuruRelationalProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Lembaga $lembaga;
    private Guru $guru;

    protected function setUp(): void
    {
        parent::setUp();
        $yayasan = Yayasan::factory()->create();
        $this->lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        
        Permission::firstOrCreate(['name' => 'guru.edit', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin_sekolah', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
        $role->givePermissionTo('guru.edit');
        
        $this->admin = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $this->admin->assignRole($role);
        
        $this->guru = Guru::factory()->create(['lembaga_id' => $this->lembaga->id]);
    }

    public function test_can_add_riwayat_pendidikan_to_guru(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.guru.riwayat-pendidikan.store', $this->guru), [
            'jenjang_pendidikan' => 'S1',
            'gelar_akademik' => 'S.Pd.',
            'sekolah_formal' => 'Universitas Negeri Malang',
            'bidang_studi' => 'Pendidikan Matematika',
            'tahun_lulus' => 2015,
        ]);

        $response->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('riwayat_pendidikan_guru', [
            'guru_id' => $this->guru->id,
            'jenjang_pendidikan' => 'S1',
            'sekolah_formal' => 'Universitas Negeri Malang',
        ]);
    }

    public function test_can_delete_sertifikasi_guru(): void
    {
        $sertifikasi = SertifikasiGuru::create([
            'guru_id' => $this->guru->id,
            'jenis_sertifikasi' => 'Sertifikat Pendidik',
            'nomor_sertifikat' => '123456789',
            'tahun_sertifikasi' => 2018,
            'bidang_studi_sertifikasi' => 'Matematika',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.guru.sertifikasi.destroy', [$this->guru, $sertifikasi]));

        $response->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('sertifikasi_guru', ['id' => $sertifikasi->id]);
    }

    public function test_can_add_and_remove_jabatan_tambahan(): void
    {
        $master = JabatanTambahanMaster::create([
            'nama' => 'Wakil Kepala Sekolah Bidang Kurikulum',
            'kelompok' => 'struktural',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.guru.jabatan-tambahan.store', $this->guru), [
            'jabatan_tambahan_master_id' => $master->id,
            'mulai_periode' => '2025-07-01',
            'no_sk' => '421/01/SK/2025',
        ]);

        $response->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('guru_jabatan_tambahan', [
            'guru_id' => $this->guru->id,
            'jabatan_tambahan_master_id' => $master->id,
            'no_sk' => '421/01/SK/2025',
        ]);

        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.guru.jabatan-tambahan.destroy', [$this->guru, $master->id]));
        $deleteResponse->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('guru_jabatan_tambahan', [
            'guru_id' => $this->guru->id,
            'jabatan_tambahan_master_id' => $master->id,
        ]);
    }

    public function test_cannot_modify_guru_relations_from_different_lembaga(): void
    {
        $otherLembaga = Lembaga::factory()->create();
        $otherGuru = Guru::factory()->create(['lembaga_id' => $otherLembaga->id]);

        $response = $this->actingAs($this->admin)->post(route('admin.guru.riwayat-pendidikan.store', $otherGuru), [
            'jenjang_pendidikan' => 'S2',
            'sekolah_formal' => 'Universitas Indonesia',
            'tahun_lulus' => 2020,
        ]);

        $response->assertStatus(404);
    }
}
