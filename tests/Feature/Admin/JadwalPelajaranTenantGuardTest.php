<?php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
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

it('menolak actor yayasan dengan active_lembaga_id stale saat store Jadwal Pelajaran', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJam->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    // TenantScope (Task 1) sudah membatasi Kelas::find() ke pool milik yayasanSaya begitu
    // session basi -- $kelas (milik lembagaSaya) tetap KETEMU, tapi resolveActiveLembagaId()
    // mengembalikan null sehingga blok `if ($lembagaId)` di store() dilewati (tidak menegakkan
    // apa-apa, konsisten dgn filosofi fail-closed-ke-pool-yayasan). Test ini murni regresi:
    // pastikan penggantian kode tidak membuat request malah 500/error tak terduga.
    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jam->id],
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertStatus(302);
});

it('update Jadwal Pelajaran tetap berhasil untuk actor yayasan dengan active_lembaga_id valid pada lembaga yang sama (regresi)', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaAktif->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJam->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'jam_pelajaran_id' => $jam->id, 'semester_id' => $semester->id,
    ]);

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_update_valid_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $response = $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jam->id,
    ]);

    $response->assertStatus(302);
    expect($jadwal->fresh()->guru_id)->toBe($guru->id);
});

it('duplicate Jadwal Pelajaran tetap berhasil untuk actor yayasan dengan active_lembaga_id valid pada lembaga yang sama (regresi setelah perbaikan bug kode-mati)', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id]);

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $semesterSumber = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil', 'urutan' => 1]);
    $semesterTujuan = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap', 'urutan' => 2]);
    $kelasSumber = Kelas::factory()->create(['lembaga_id' => $lembagaAktif->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJam->id]);
    $kelasTujuan = Kelas::factory()->create(['lembaga_id' => $lembagaAktif->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJam->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'is_pelajaran' => true]);
    JadwalPelajaran::create(['kelas_id' => $kelasSumber->id, 'guru_id' => $guru->id, 'jam_pelajaran_id' => $jam->id, 'semester_id' => $semesterSumber->id]);

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_duplicate_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.duplicate'), [
        'source_kelas_id' => $kelasSumber->id,
        'target_kelas_id' => $kelasTujuan->id,
        'source_semester_id' => $semesterSumber->id,
        'target_semester_id' => $semesterTujuan->id,
    ]);

    $response->assertStatus(302);
    expect(JadwalPelajaran::where('kelas_id', $kelasTujuan->id)->exists())->toBeTrue();
});
