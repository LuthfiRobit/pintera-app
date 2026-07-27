<?php

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('never lists a kelas from another lembaga even via a raw jadwal_pelajaran row bypassing tenant checks', function () {
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_lintas_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);

    $guruUser = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaSaya->id, 'user_id' => $guruUser->id]);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id, 'status_aktif' => true]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id, 'pola_jam_id' => $polaLain->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);

    // Simulate a raw/legacy row where this guru is (incorrectly) attached to a foreign lembaga's jadwal.
    JadwalPelajaran::withoutGlobalScopes()->create([
        'kelas_id' => $kelasLain->id, 'jam_pelajaran_id' => $jamLain->id, 'mata_pelajaran_id' => $mapelLain->id,
        'guru_id' => $guru->id, 'semester_id' => $semesterLain->id,
    ]);

    $response = $this->actingAs($guruUser)->get(route('guru.sesi.index'));

    $response->assertOk();
    $response->assertDontSee($kelasLain->nama);
});

it('never lists a kelas from another lembaga when guru is wali_kelas_guru_id of that foreign kelas', function () {
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_lintas_test2', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);

    $guruUser = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaSaya->id, 'user_id' => $guruUser->id]);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);

    // Create a foreign kelas and set this guru as wali_kelas (triggering the orWhere branch)
    $kelasLain = Kelas::factory()->create([
        'lembaga_id' => $lembagaLain->id,
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'pola_jam_id' => $polaLain->id,
    ]);
    // Bypass the tenant scope when setting wali_kelas_guru_id on the foreign kelas
    Kelas::withoutGlobalScopes()->where('id', $kelasLain->id)->update(['wali_kelas_guru_id' => $guru->id]);

    $response = $this->actingAs($guruUser)->get(route('guru.sesi.index'));

    $response->assertOk();
    $response->assertDontSee($kelasLain->nama);
});
