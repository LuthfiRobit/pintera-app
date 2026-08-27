<?php

// tests/Feature/DashboardStatsServiceAssessmentTypeTest.php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reaches 100 percent progress when every numeric komponen is filled, even with narrative komponen present and unfilled', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric',
    ]);
    KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'narrative',
    ]);

    $asesmen = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNumeric->id, 'nilai_angka' => 80]);

    $hasil = app(DashboardStatsService::class)->statistikProgressRaporKelas($kelas);

    expect($hasil['persen'])->toBe(100.0);
});

it('does not count a Formatif nilai as filled progress toward rapor readiness', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponen = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric',
    ]);

    $asesmenFormatif = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id,
        'semester_id' => $semester->id, 'jenis' => 'formatif',
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmenFormatif->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 100]);

    $hasilSebelumSumatif = app(DashboardStatsService::class)->statistikProgressRaporKelas($kelas);

    expect($hasilSebelumSumatif['terisi'])->toBe(0);
    expect($hasilSebelumSumatif['persen'])->toBe(0.0);

    $asesmenSumatif = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id,
        'semester_id' => $semester->id, 'jenis' => 'sumatif_lingkup_materi',
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmenSumatif->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 80]);

    $hasilSetelahSumatif = app(DashboardStatsService::class)->statistikProgressRaporKelas($kelas);

    expect($hasilSetelahSumatif['terisi'])->toBe(1);
    expect($hasilSetelahSumatif['persen'])->toBe(100.0);
});
