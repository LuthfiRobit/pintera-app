<?php

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsRaporViewer(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['rapor.view']);

    $viewer = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $viewer->assignRole($role);

    return $viewer;
}

it('denies access without rapor.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.rapor.index'))->assertForbidden();
});

it('displays the rapor recap page for selected class and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::SumatifLingkupMateri,
    ]);

    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    NilaiSiswa::create([
        'asesmen_id' => $asesmen->id,
        'siswa_id' => $siswa->id,
        'komponen_penilaian_id' => $komponen->id,
        'nilai_angka' => 88,
    ]);

    $viewer = actingAsRaporViewer($lembaga);

    $this->actingAs($viewer)
        ->get(route('admin.rapor.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]))
        ->assertOk()
        ->assertSee('88');
});
