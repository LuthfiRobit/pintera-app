<?php
// tests/Unit/Services/RaporCalculationServiceAssessmentTypeTest.php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('excludes non-numeric komponen from the weighted average entirely, keeping the numeric-only result unchanged', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric', 'bobot' => 100,
    ]);
    $komponenNarrative = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'narrative', 'bobot' => 100,
    ]);

    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmen->komponenPenilaian()->attach([$komponenNumeric->id, $komponenNarrative->id]);

    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNumeric->id, 'nilai_angka' => 80]);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNarrative->id, 'nilai_angka' => null, 'catatan' => 'Deskripsi perkembangan']);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);

    $key = 'mata_pelajaran:'.$mapel->id;
    expect($rekap['rekapNilai'][$siswa->id][$key])->toBe(80.0);
});

it('still excludes a narrative komponen from the weighted average even if its nilai_angka was set by dirty/legacy data (defense-in-depth, not reliant on SimpanNilaiSiswaAction invariant alone)', function () {
    // Skenario ini SENGAJA membuat data "kotor" langsung lewat NilaiSiswa::create()
    // (bukan lewat SimpanNilaiSiswaAction yang biasanya menjaga invariant) --
    // membuktikan RaporCalculationService sendiri tidak boleh bergantung pada
    // whereNotNull('nilai_angka') sbg proxy "ini komponen numeric", karena proxy
    // itu rapuh terhadap data lama/import yang tidak lewat Action.
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric', 'bobot' => 100,
    ]);
    $komponenNarrative = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'narrative', 'bobot' => 100,
    ]);

    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmen->komponenPenilaian()->attach([$komponenNumeric->id, $komponenNarrative->id]);

    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNumeric->id, 'nilai_angka' => 80]);
    // Data kotor: komponen narrative tapi nilai_angka terisi (mis. sisa import lama).
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNarrative->id, 'nilai_angka' => 20]);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);

    // Kalau filter masih pakai whereNotNull('nilai_angka') murni, hasilnya akan
    // jadi (80*100 + 20*100) / 200 = 50.0 -- salah, karena komponen narrative
    // ikut dihitung sbg numeric hanya krn nilai_angka-nya kebetulan terisi.
    // Dengan filter assessment_type=numeric eksplisit, hasilnya tetap 80.0.
    $key = 'mata_pelajaran:'.$mapel->id;
    expect($rekap['rekapNilai'][$siswa->id][$key])->toBe(80.0);
});
