<?php

use App\Domains\Akademik\Enums\AssessmentType;
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

function siapkanSubjekTypeAware(): array
{
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    return compact('mapel', 'lembaga', 'semester', 'kelas', 'guru', 'siswa');
}

it('picks the most frequent predikat (modus) across multiple asesmen for the same siswa and subjek', function () {
    ['mapel' => $mapel, 'lembaga' => $lembaga, 'semester' => $semester, 'kelas' => $kelas, 'guru' => $guru, 'siswa' => $siswa] = siapkanSubjekTypeAware();

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga, 'assessment_type' => 'predicate']);

    foreach (['BSH', 'BSH', 'MB'] as $predikat) {
        $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
        $asesmen->komponenPenilaian()->attach($komponen->id);
        NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'predikat' => $predikat]);
    }

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);
    $sel = $rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id];

    expect($sel->assessmentType)->toBe(AssessmentType::Predicate);
    expect($sel->label)->toBe('BSH');
    expect($sel->tuntas)->toBeNull();
});

it('breaks a predikat frequency tie using the ranking BSB=4 > BSH=3 > MB=2 > BB=1', function () {
    ['mapel' => $mapel, 'lembaga' => $lembaga, 'semester' => $semester, 'kelas' => $kelas, 'guru' => $guru, 'siswa' => $siswa] = siapkanSubjekTypeAware();

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga, 'assessment_type' => 'predicate']);

    foreach (['BSH', 'BSH', 'BSB', 'BSB'] as $predikat) {
        $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
        $asesmen->komponenPenilaian()->attach($komponen->id);
        NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'predikat' => $predikat]);
    }

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);
    $sel = $rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id];

    expect($sel->label)->toBe('BSB');
});

it('returns null when subjek has a predicate komponen but siswa has no valid predikat filled in', function () {
    ['mapel' => $mapel, 'lembaga' => $lembaga, 'semester' => $semester, 'kelas' => $kelas, 'guru' => $guru, 'siswa' => $siswa] = siapkanSubjekTypeAware();

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga, 'assessment_type' => 'predicate']);
    $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'predikat' => null]);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);

    expect($rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id])->toBeNull();
});

it('computes a completion-rate label for narrative komponen, counting only catatan that is non-empty after trim', function () {
    ['mapel' => $mapel, 'lembaga' => $lembaga, 'semester' => $semester, 'kelas' => $kelas, 'guru' => $guru, 'siswa' => $siswa] = siapkanSubjekTypeAware();

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga, 'assessment_type' => 'narrative']);

    $catatanList = ['Catatan valid satu', 'Catatan valid dua', null, '   '];
    foreach ($catatanList as $catatan) {
        $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
        $asesmen->komponenPenilaian()->attach($komponen->id);
        NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'catatan' => $catatan]);
    }

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);
    $sel = $rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id];

    expect($sel->assessmentType)->toBe(AssessmentType::Narrative);
    expect($sel->label)->toBe('2/4');
    expect($sel->tuntas)->toBeNull();
});

it('returns null when subjek has no narrative komponen registered for the semester at all', function () {
    ['mapel' => $mapel, 'lembaga' => $lembaga, 'semester' => $semester, 'kelas' => $kelas, 'siswa' => $siswa] = siapkanSubjekTypeAware();

    // Asesmen ada utk subjek ini, tapi TIDAK ada KomponenPenilaian narrative sama sekali.
    Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);

    expect($rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id])->toBeNull();
});

it('prefers numeric over predicate when a subjek has both komponen types for the same siswa', function () {
    ['mapel' => $mapel, 'lembaga' => $lembaga, 'semester' => $semester, 'kelas' => $kelas, 'guru' => $guru, 'siswa' => $siswa] = siapkanSubjekTypeAware();

    $komponenNumeric = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga, 'assessment_type' => 'numeric']);
    $komponenPredicate = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga, 'assessment_type' => 'predicate']);

    $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach([$komponenNumeric->id, $komponenPredicate->id]);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNumeric->id, 'nilai_angka' => 75]);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenPredicate->id, 'predikat' => 'BSH']);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);
    $sel = $rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id];

    expect($sel->assessmentType)->toBe(AssessmentType::Numeric);
    expect($sel->label)->toBe('75');
});

it('prefers predicate over narrative when a subjek has both komponen types for the same siswa (no numeric present)', function () {
    ['mapel' => $mapel, 'lembaga' => $lembaga, 'semester' => $semester, 'kelas' => $kelas, 'guru' => $guru, 'siswa' => $siswa] = siapkanSubjekTypeAware();

    $komponenPredicate = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga, 'assessment_type' => 'predicate']);
    $komponenNarrative = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga, 'assessment_type' => 'narrative']);

    $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach([$komponenPredicate->id, $komponenNarrative->id]);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenPredicate->id, 'predikat' => 'BSH']);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNarrative->id, 'catatan' => 'Terisi lengkap']);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);
    $sel = $rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id];

    expect($sel->assessmentType)->toBe(AssessmentType::Predicate);
    expect($sel->label)->toBe('BSH');
});

it('keeps classAvg and highestScore null for a class with only predicate/narrative komponen (pure PAUD-style class)', function () {
    ['mapel' => $mapel, 'lembaga' => $lembaga, 'semester' => $semester, 'kelas' => $kelas, 'guru' => $guru, 'siswa' => $siswa] = siapkanSubjekTypeAware();

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga, 'assessment_type' => 'predicate']);
    $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'predikat' => 'BSH']);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);

    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});
