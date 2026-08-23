<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsGuruAsesmen(Guru $guru): User
{
    Permission::firstOrCreate(['name' => 'asesmen.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['asesmen.kelola']);

    $user = User::factory()->create([
        'lembaga_id' => $guru->lembaga_id,
        'email' => $guru->email ?: 'guru'.random_int(1000, 9999).'@test.com',
    ]);
    $guru->update(['user_id' => $user->id]);
    $user->assignRole($role);

    return $user;
}

it('allows guru to view their asesmen list and create form', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);

    $this->actingAs($user)->get(route('guru.asesmen.index'))->assertOk();
    $this->actingAs($user)->get(route('guru.asesmen.create'))->assertOk();
});

it('allows guru to create an asesmen and grade students per komponen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jam->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    $komponen = KomponenPenilaian::factory()->create([
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
    ]);

    $siswa1 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $siswa2 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $response = $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::SumatifLingkupMateri->value,
        'judul' => 'Ulangan Bab 1',
        'tanggal' => now()->toDateString(),
        'komponen_id' => [$komponen->id],
    ]);

    $asesmen = Asesmen::first();
    expect($asesmen)->not->toBeNull();
    $response->assertRedirect(route('guru.asesmen.show', $asesmen));

    $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswa1->id => [$komponen->id => ['nilai_angka' => '85', 'catatan' => 'Baik sekali']],
            $siswa2->id => [$komponen->id => ['nilai_angka' => '90', 'catatan' => 'Sempurna']],
        ],
    ])->assertRedirect(route('guru.asesmen.show', $asesmen));

    expect(NilaiSiswa::where('asesmen_id', $asesmen->id)->where('siswa_id', $siswa1->id)->where('komponen_penilaian_id', $komponen->id)->value('nilai_angka'))->toBe(85);
    expect(NilaiSiswa::where('asesmen_id', $asesmen->id)->where('siswa_id', $siswa2->id)->where('komponen_penilaian_id', $komponen->id)->value('nilai_angka'))->toBe(90);
});

it('ignores a nilai submitted for a komponen not attached to the asesmen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id]);
    $komponenAsing = KomponenPenilaian::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswa->id => [$komponenAsing->id => ['nilai_angka' => '99']],
        ],
    ])->assertRedirect(route('guru.asesmen.show', $asesmen));

    expect(NilaiSiswa::where('komponen_penilaian_id', $komponenAsing->id)->exists())->toBeFalse();
});

it('ignores a nilai submitted for a siswa not enrolled in the asesmen kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create();
    $asesmen->komponenPenilaian()->attach($komponen->id);

    // Belongs to a completely different kelas/lembaga, not enrolled in $asesmen->kelas.
    $siswaLuar = Siswa::factory()->create();

    $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswaLuar->id => [$komponen->id => ['nilai_angka' => '99']],
        ],
    ])->assertRedirect(route('guru.asesmen.show', $asesmen));

    expect(NilaiSiswa::where('siswa_id', $siswaLuar->id)->exists())->toBeFalse();
});

it('prevents guru from accessing asesmen belonging to another guru', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruOwner = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruOther = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruOwner->id]);

    $userOther = actingAsGuruAsesmen($guruOther);

    $this->actingAs($userOther)->get(route('guru.asesmen.show', $asesmen))->assertForbidden();
});

it('rejects creating an asesmen for a kelas/mapel/semester combination the guru does not teach', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $tahunAjaranSaya = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $semesterSaya = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranSaya->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user = actingAsGuruAsesmen($guru);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $komponenLain = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelLain->id]);

    $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelasLain->id,
        'mata_pelajaran_id' => $mapelLain->id,
        'semester_id' => $semesterSaya->id,
        'jenis' => JenisAsesmen::SumatifLingkupMateri->value,
        'judul' => 'Coba Asesmen Kelas Lain',
        'tanggal' => now()->toDateString(),
        'komponen_id' => [$komponenLain->id],
    ])->assertForbidden();

    expect(Asesmen::where('judul', 'Coba Asesmen Kelas Lain')->exists())->toBeFalse();
});

it('rejects creating an asesmen with no komponen_id selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jam->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::SumatifLingkupMateri->value,
        'judul' => 'Asesmen Tanpa TP',
        'tanggal' => now()->toDateString(),
    ])->assertSessionHasErrors('komponen_id');

    expect(Asesmen::where('judul', 'Asesmen Tanpa TP')->exists())->toBeFalse();
});

it('rejects a jenis outside the v1-supported sumatif options', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::Formatif->value,
        'judul' => 'Coba Jenis Formatif',
        'tanggal' => now()->toDateString(),
    ])->assertSessionHasErrors('jenis');

    expect(Asesmen::where('judul', 'Coba Jenis Formatif')->exists())->toBeFalse();
});
