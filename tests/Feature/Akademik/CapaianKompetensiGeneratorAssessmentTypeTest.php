<?php
// tests/Feature/Akademik/CapaianKompetensiGeneratorAssessmentTypeTest.php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\CapaianKompetensiGenerator;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('only ranks numeric-type komponen when generating narasi tertinggi/terendah, even if a narrative komponen has dirty nilai_angka data', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric', 'kktp_minimal' => 75, 'deskripsi' => 'Numerik A',
    ]);
    $komponenNarrative = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'narrative', 'kktp_minimal' => 75, 'deskripsi' => 'Naratif B',
    ]);

    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmen->komponenPenilaian()->attach([$komponenNumeric->id, $komponenNarrative->id]);

    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNumeric->id, 'nilai_angka' => 90]);
    // Data kotor: komponen narrative tapi nilai_angka terisi lebih rendah (mis. sisa import lama) --
    // kalau CapaianKompetensiGenerator tidak filter assessment_type, "Naratif B" bisa ikut dianggap
    // skor terendah dan tampil di narasi "perlu bimbingan", padahal komponen ini bukan numeric sama sekali.
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNarrative->id, 'nilai_angka' => 40]);

    $narasi = app(CapaianKompetensiGenerator::class)->generateNarasi($siswa, $mapel, $semester);

    expect($narasi['tertinggi'])->toContain('Numerik A');
    // Hanya ada 1 komponen numeric (90, di atas ambang KKTP 75) setelah difilter --
    // "terendah" harus null krn tidak ada komponen numeric lain yang berada di
    // bawah ambang. Kalau "Naratif B" (nilai kotor 40) ikut lolos filter, "terendah"
    // TIDAK akan null dan akan berisi "Naratif B".
    expect($narasi['terendah'])->toBeNull();
});
