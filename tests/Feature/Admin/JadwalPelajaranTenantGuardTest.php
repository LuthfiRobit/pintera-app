<?php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsJadwalGuardManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jadwal-pelajaran.kelola']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('menolak store Jadwal Pelajaran untuk Kelas lembaga lain', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalGuardManager($lembaga);

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kelasLembagaLain = Kelas::factory()->create([
        'lembaga_id' => $lembagaLain->id,
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'pola_jam_id' => $polaLain->id,
    ]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id, 'is_pelajaran' => true]);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasLembagaLain->id,
        'jam_pelajaran_id' => [$jamLain->id],
        'guru_id' => $guruLain->id,
        'semester_id' => $semesterLain->id,
    ]);

    $response->assertStatus(404);
});
