<?php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;

it('derives lembaga_id from kelas on create for sesi_pembelajaran, jadwal_pelajaran, and asesmen', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guru->id]);
    $jadwal = JadwalPelajaran::factory()->create([
        'kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id, 'mata_pelajaran_id' => $mapel->id,
    ]);
    $asesmen = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id, 'mata_pelajaran_id' => $mapel->id,
    ]);

    expect($sesi->lembaga_id)->toBe($lembaga->id)
        ->and($jadwal->lembaga_id)->toBe($lembaga->id)
        ->and($asesmen->lembaga_id)->toBe($lembaga->id);
});

it('derives lembaga_id from mata_pelajaran on create for komponen_penilaian', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id]);

    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    expect($komponen->lembaga_id)->toBe($lembaga->id);
});

it('derives lembaga_id from siswa on create for nilai_siswa', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $asesmen = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id, 'mata_pelajaran_id' => $mapel->id,
    ]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $nilai = NilaiSiswa::factory()->create([
        'asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id,
    ]);

    expect($nilai->lembaga_id)->toBe($lembaga->id);
});

it('never resolves sesi_pembelajaran, jadwal_pelajaran, asesmen, komponen_penilaian, or nilai_siswa rows from another lembaga by id', function () {
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);

    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id, 'kelas_id' => $kelasLain->id]);

    $sesiLain = SesiPembelajaran::factory()->create(['kelas_id' => $kelasLain->id, 'guru_id' => $guruLain->id]);
    $jadwalLain = JadwalPelajaran::factory()->create([
        'kelas_id' => $kelasLain->id, 'guru_id' => $guruLain->id, 'semester_id' => $semesterLain->id, 'mata_pelajaran_id' => $mapelLain->id,
    ]);
    $asesmenLain = Asesmen::factory()->create([
        'kelas_id' => $kelasLain->id, 'guru_id' => $guruLain->id, 'semester_id' => $semesterLain->id, 'mata_pelajaran_id' => $mapelLain->id,
    ]);
    $komponenLain = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelLain->id, 'semester_id' => $semesterLain->id]);
    $nilaiLain = NilaiSiswa::factory()->create([
        'asesmen_id' => $asesmenLain->id, 'siswa_id' => $siswaLain->id, 'komponen_penilaian_id' => $komponenLain->id,
    ]);

    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user->assignRole('admin_administrasi');
    $this->actingAs($user);

    expect(SesiPembelajaran::find($sesiLain->id))->toBeNull()
        ->and(JadwalPelajaran::find($jadwalLain->id))->toBeNull()
        ->and(Asesmen::find($asesmenLain->id))->toBeNull()
        ->and(KomponenPenilaian::find($komponenLain->id))->toBeNull()
        ->and(NilaiSiswa::find($nilaiLain->id))->toBeNull();
});
