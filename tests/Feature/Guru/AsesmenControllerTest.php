<?php

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\PolaJam;
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

it('allows guru to create an asesmen and grade students', function () {
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

    // Jadwal assignment
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

    // Input grades
    $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswa1->id => ['skor' => '85.5', 'catatan' => 'Baik sekali'],
            $siswa2->id => ['skor' => '90', 'catatan' => 'Sempurna'],
        ],
    ])->assertRedirect(route('guru.asesmen.show', $asesmen));

    expect(NilaiSiswa::where('asesmen_id', $asesmen->id)->where('siswa_id', $siswa1->id)->value('skor'))->toEqual(85.5);
    expect(NilaiSiswa::where('asesmen_id', $asesmen->id)->where('siswa_id', $siswa2->id)->value('skor'))->toEqual(90.0);
});

it('prevents guru from accessing asesmen belonging to another guru', function () {
    $guruOwner = Guru::factory()->create();
    $guruOther = Guru::factory()->create();
    $asesmen = Asesmen::factory()->create(['guru_id' => $guruOwner->id]);

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

    $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelasLain->id,
        'mata_pelajaran_id' => $mapelLain->id,
        'semester_id' => $semesterSaya->id,
        'jenis' => JenisAsesmen::SumatifLingkupMateri->value,
        'judul' => 'Coba Asesmen Kelas Lain',
        'tanggal' => now()->toDateString(),
    ])->assertForbidden();

    expect(Asesmen::where('judul', 'Coba Asesmen Kelas Lain')->exists())->toBeFalse();
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
