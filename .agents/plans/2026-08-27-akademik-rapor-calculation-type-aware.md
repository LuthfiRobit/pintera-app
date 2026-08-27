# RaporCalculationService Type-Aware + Fix Key-Mismatch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Perbaiki bug key-mismatch di Rekap Rapor & Persetujuan Rapor (kolom per-mapel selalu kosong utk SEMUA jenjang) SEKALIGUS bangun agregasi type-aware (numeric/predicate/narrative) di `RaporCalculationService` supaya kelas PAUD/naratif-predikat tidak lagi melihat rekap kosong total.

**Architecture:** `RaporCalculationService::hitungRekapKelas()` ditulis ulang: `mapelList` di-`keyBy()` composite key (fix bug), tiap sel matriks jadi `RekapNilaiSel` DTO (bukan `float|null` polos) hasil precedence numeric > predicate > narrative per siswa per subjek. `classAvg`/`highestScore` tetap dihitung dari array bantu numeric mentah terpisah, tidak pernah dari DTO. Kedua view baca `->label`/`->tuntas` dari DTO utk render badge sesuai tipe.

**Tech Stack:** Laravel 12.63.0, Pest v4, MySQL 8.0.30. Tidak ada migration baru (skema DB sudah ada sejak Sprint 2).

## Global Constraints

- `RekapNilaiSel` DTO HANYA punya 3 field: `assessmentType: AssessmentType`, `label: string`, `tuntas: ?bool` — TIDAK ADA field `value`. `classAvg`/`highestScore` dihitung dari array bantu numeric mentah terpisah di dalam service, bukan dari DTO.
- `mapelList` (`$subjekList`) HARUS di-`keyBy(fn ($s) => SubjekPenilaianKey::dari($s))` sbg langkah TERAKHIR — TIDAK BOLEH ada `->values()` setelahnya di titik mana pun. Key hasil `keyBy()` adalah SATU-SATUNYA identifier utk lookup sel di view.
- Precedence per sel: **numeric > predicate > narrative**. Dicek per siswa per subjek, bukan precedence global per kelas.
- Predicate: modus (frekuensi terbanyak); tie-break `BSB=4 > BSH=3 > MB=2 > BB=1`. Kalau tidak ada satu pun `predikat` valid untuk siswa+subjek itu → sel `null` (bukan DTO kosong).
- Narrative: "terisi" = `trim($catatan ?? '') !== ''` (null/""/whitespace semua dianggap belum terisi). `$total` = jumlah slot (asesmen×komponen narrative) yang terdaftar utk subjek+semester itu SAJA (sama utk semua siswa di kelas). Kalau `$total === 0` → sel `null` (BUKAN `"0/0"`).
- `classAvg`/`highestScore` HANYA dari sel numeric — semantik TIDAK BERUBAH dari kode lama.
- Tidak ada migration baru, tidak ada perubahan skema DB.
- Tidak menyentuh `RaporPdfDataBuilder`/`templateUntukJenjang` (rapor PDF per siswa) — scope HANYA `RaporCalculationService` + 2 halaman rekap (`_hasil.blade.php`, `persetujuan/show.blade.php`).

---

## Task 1: DTO `RekapNilaiSel`

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/RekapNilaiSel.php`
- Test: `tests/Unit/DataTransferObjects/RekapNilaiSelTest.php`

**Interfaces:**
- Produces: `RekapNilaiSel(assessmentType: AssessmentType, label: string, tuntas: ?bool)` — dipakai Task 2 (`RaporCalculationService`) dan Task 3 (kedua view).

- [x] **Step 1: Tulis test dasar**

```php
<?php

use App\Domains\Akademik\DataTransferObjects\RekapNilaiSel;
use App\Domains\Akademik\Enums\AssessmentType;

it('holds assessmentType, label, and tuntas as readonly properties', function () {
    $sel = new RekapNilaiSel(
        assessmentType: AssessmentType::Numeric,
        label: '88',
        tuntas: true,
    );

    expect($sel->assessmentType)->toBe(AssessmentType::Numeric);
    expect($sel->label)->toBe('88');
    expect($sel->tuntas)->toBeTrue();
});

it('allows tuntas to be null for non-numeric assessment types', function () {
    $sel = new RekapNilaiSel(
        assessmentType: AssessmentType::Predicate,
        label: 'BSH',
        tuntas: null,
    );

    expect($sel->tuntas)->toBeNull();
});
```

Simpan sebagai `tests/Unit/DataTransferObjects/RekapNilaiSelTest.php`.

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/DataTransferObjects/RekapNilaiSelTest.php`
Expected: FAIL — `Class "App\Domains\Akademik\DataTransferObjects\RekapNilaiSel" not found`

- [x] **Step 3: Buat DTO**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

use App\Domains\Akademik\Enums\AssessmentType;

final readonly class RekapNilaiSel
{
    public function __construct(
        public AssessmentType $assessmentType,
        public string $label,
        public ?bool $tuntas,
    ) {}
}
```

Simpan sebagai `app/Domains/Akademik/DataTransferObjects/RekapNilaiSel.php`.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/DataTransferObjects/RekapNilaiSelTest.php`
Expected: PASS (2 test)

- [x] **Step 5: Lint & commit**

Run: `php -l app/Domains/Akademik/DataTransferObjects/RekapNilaiSel.php`
Expected: `No syntax errors detected`

```bash
git add app/Domains/Akademik/DataTransferObjects/RekapNilaiSel.php tests/Unit/DataTransferObjects/RekapNilaiSelTest.php
git commit -m "feat(akademik): tambah DTO RekapNilaiSel"
```

---

## Task 2: Tulis Ulang `RaporCalculationService` (Type-Aware + Fix Key)

**Files:**
- Modify: `app/Domains/Akademik/Services/RaporCalculationService.php`
- Modify: `tests/Unit/Services/RaporCalculationServiceTest.php` (retrofit assertion ke kontrak DTO)
- Modify: `tests/Unit/Services/RaporCalculationServiceAssessmentTypeTest.php` (retrofit assertion ke kontrak DTO)
- Create: `tests/Unit/Services/RaporCalculationServiceTypeAwareTest.php`

**Interfaces:**
- Consumes: `RekapNilaiSel` (Task 1), `AssessmentType`, `PredikatPaud` enum (sudah ada), `SubjekPenilaianKey::dari()` (sudah ada).
- Produces: `RaporCalculationService::hitungRekapKelas(Kelas $kelas, Semester $semester): array{siswaList, mapelList: Collection<string,Model>, rekapNilai: array<int,array<string,?RekapNilaiSel>>, classAvg: ?float, highestScore: ?float}` — dipakai Task 3 (kedua view/controller, TIDAK BERUBAH signature-nya).

**PENTING — kenapa 2 file test lama harus diretrofit**: sebelumnya `rekapNilai[$siswa][$key]` adalah `float|null` langsung. Sekarang jadi `?RekapNilaiSel`. Assertion yang tadinya `expect(...)->toBe(83.0)` harus jadi `expect(...->label)->toBe('83')` — NILAI NUMERIC YANG DIHARAPKAN TIDAK BOLEH BERUBAH, cuma cara aksesnya.

- [x] **Step 1: Retrofit `RaporCalculationServiceTest.php` ke kontrak DTO baru**

Ganti isi file jadi:

```php
<?php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('computes a weighted average per siswa per mapel using komponen bobot', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $komponenBerat = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'bobot' => 70]);
    $komponenRingan = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'bobot' => 30]);

    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenBerat->id, 'nilai_angka' => 80]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenRingan->id, 'nilai_angka' => 90]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelas, $semester);

    // (80*70 + 90*30) / 100 = 83.0
    $sel = $rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id];
    expect($sel->label)->toBe('83');
    expect($sel->tuntas)->toBeBool();
    expect($rekap['classAvg'])->toBe(83.0);
    expect($rekap['highestScore'])->toBe(83.0);
    expect($rekap['siswaList']->pluck('id')->all())->toBe([$siswa->id]);
    expect($rekap['mapelList']->pluck('id')->all())->toBe([$mapel->id]);
});

it('returns null score for a siswa with no nilai on that mapel', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaTanpaNilai = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelas, $semester);

    expect($rekap['rekapNilai'][$siswaTanpaNilai->id]['mata_pelajaran:'.$mapel->id])->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('returns empty structure when kelas has no asesmen in the semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelas, $semester);

    expect($rekap['mapelList'])->toBeEmpty();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('returns no data when kelas and semester belong to different lembaga, even when called directly with a mismatched pair', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    Asesmen::factory()->create(['kelas_id' => $kelasA->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelA->id, 'semester_id' => $semesterA->id]);

    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelasA, $semesterB);

    expect($rekap['mapelList'])->toBeEmpty();
    expect($rekap['rekapNilai'])->toBeEmpty();
});
```

- [x] **Step 2: Retrofit `RaporCalculationServiceAssessmentTypeTest.php` ke kontrak DTO baru**

Ganti kedua assertion terakhir (baris 47 dan 89 di file lama) — ganti isi lengkap file jadi:

```php
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
    $sel = $rekap['rekapNilai'][$siswa->id][$key];
    expect($sel->label)->toBe('80');
    expect($sel->assessmentType->value)->toBe('numeric');
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
    // Dengan filter assessment_type=numeric eksplisit, hasilnya tetap 80.
    $key = 'mata_pelajaran:'.$mapel->id;
    expect($rekap['rekapNilai'][$siswa->id][$key]->label)->toBe('80');
});
```

- [x] **Step 3: Tulis test type-aware baru (predicate, narrative, precedence, edge cases) — akan gagal krn service belum diubah**

```php
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

    // Tidak ada Asesmen/KomponenPenilaian narrative sama sekali utk subjek ini.

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
```

Simpan sebagai `tests/Unit/Services/RaporCalculationServiceTypeAwareTest.php`.

- [x] **Step 4: Jalankan ketiga file test, pastikan SEMUA gagal (service belum diubah)**

Run: `php artisan test tests/Unit/Services/RaporCalculationServiceTest.php tests/Unit/Services/RaporCalculationServiceAssessmentTypeTest.php tests/Unit/Services/RaporCalculationServiceTypeAwareTest.php`
Expected: FAIL — assertion mismatch (DTO belum dikembalikan, `->label` di null/float error)

- [x] **Step 5: Tulis ulang `RaporCalculationService`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\DataTransferObjects\RekapNilaiSel;
use App\Domains\Akademik\Enums\AssessmentType;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\SubjekPenilaianKey;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;

final class RaporCalculationService
{
    private const RANKING_PREDIKAT = ['BB' => 1, 'MB' => 2, 'BSH' => 3, 'BSB' => 4];

    /**
     * @return array{siswaList: \Illuminate\Support\Collection, mapelList: \Illuminate\Support\Collection, rekapNilai: array<int, array<string, ?RekapNilaiSel>>, classAvg: float|null, highestScore: float|null}
     */
    public function hitungRekapKelas(Kelas $kelas, Semester $semester): array
    {
        $siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nama_lengkap')->get();

        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->with(['subjek', 'komponenPenilaian'])
            ->get();

        $subjekList = $asesmenList->pluck('subjek')
            ->filter()
            ->unique(fn ($s) => SubjekPenilaianKey::dari($s))
            ->sortBy('nama')
            ->keyBy(fn ($s) => SubjekPenilaianKey::dari($s));

        $asesmenByKey = $asesmenList->groupBy(fn ($a) => $a->subjek ? SubjekPenilaianKey::dari($a->subjek) : '');

        $allNilai = NilaiSiswa::whereIn('asesmen_id', $asesmenList->pluck('id'))
            ->with('komponenPenilaian')
            ->get();

        // Total slot narrative per subjek TIDAK bergantung siswa -- dihitung sekali di sini,
        // bukan diulang di dalam loop per siswa.
        $totalNarrativeBySubjek = [];
        foreach ($subjekList as $key => $subjek) {
            $subjekAsesmen = $asesmenByKey->get($key) ?? collect();
            $totalNarrativeBySubjek[$key] = $subjekAsesmen
                ->flatMap(fn ($a) => $a->komponenPenilaian->filter(fn ($k) => $k->assessment_type === AssessmentType::Narrative))
                ->count();
        }

        $rekapNilai = [];
        $rekapNumericMentah = [];

        foreach ($siswaList as $siswa) {
            $rekapNilai[$siswa->id] = [];
            $rekapNumericMentah[$siswa->id] = [];

            foreach ($subjekList as $key => $subjek) {
                $subjekAsesmenIds = ($asesmenByKey->get($key) ?? collect())->pluck('id');
                $nilaiSubjek = $allNilai->whereIn('asesmen_id', $subjekAsesmenIds)->where('siswa_id', $siswa->id);

                $sel = $this->resolveNumeric($nilaiSubjek)
                    ?? $this->resolvePredicate($nilaiSubjek)
                    ?? $this->resolveNarrative($nilaiSubjek, $totalNarrativeBySubjek[$key]);

                $rekapNilai[$siswa->id][$key] = $sel;

                if ($sel !== null && $sel->assessmentType === AssessmentType::Numeric) {
                    $rekapNumericMentah[$siswa->id][$key] = (float) $sel->label;
                }
            }
        }

        $allNumeric = collect($rekapNumericMentah)->flatMap(fn ($m) => collect($m));

        return [
            'siswaList' => $siswaList,
            'mapelList' => $subjekList,
            'rekapNilai' => $rekapNilai,
            'classAvg' => $allNumeric->count() > 0 ? round($allNumeric->avg(), 1) : null,
            'highestScore' => $allNumeric->count() > 0 ? $allNumeric->max() : null,
        ];
    }

    private function resolveNumeric(\Illuminate\Support\Collection $nilaiSubjek): ?RekapNilaiSel
    {
        $numericNilai = $nilaiSubjek->filter(
            fn ($n) => $n->komponenPenilaian?->assessment_type === AssessmentType::Numeric && $n->nilai_angka !== null
        );

        if ($numericNilai->count() === 0) {
            return null;
        }

        $totalWeight = 0;
        $weightedSum = 0;
        foreach ($numericNilai as $item) {
            $w = $item->komponenPenilaian && $item->komponenPenilaian->bobot > 0 ? (int) $item->komponenPenilaian->bobot : 1;
            $weightedSum += ($item->nilai_angka * $w);
            $totalWeight += $w;
        }

        if ($totalWeight === 0) {
            return null;
        }

        $nilaiMentah = round($weightedSum / $totalWeight, 1);

        return new RekapNilaiSel(
            assessmentType: AssessmentType::Numeric,
            label: (string) $nilaiMentah,
            tuntas: $nilaiMentah >= config('akademik.ambang_tuntas'),
        );
    }

    private function resolvePredicate(\Illuminate\Support\Collection $nilaiSubjek): ?RekapNilaiSel
    {
        $predicateNilai = $nilaiSubjek->filter(
            fn ($n) => $n->komponenPenilaian?->assessment_type === AssessmentType::Predicate && $n->predikat !== null
        );

        if ($predicateNilai->count() === 0) {
            return null;
        }

        $frekuensi = [];
        foreach ($predicateNilai as $item) {
            $kode = $item->predikat->value;
            $frekuensi[$kode] = ($frekuensi[$kode] ?? 0) + 1;
        }

        $terpilih = null;
        $terbanyak = -1;
        foreach ($frekuensi as $kode => $jumlah) {
            if ($jumlah > $terbanyak || ($jumlah === $terbanyak && self::RANKING_PREDIKAT[$kode] > self::RANKING_PREDIKAT[$terpilih])) {
                $terpilih = $kode;
                $terbanyak = $jumlah;
            }
        }

        return new RekapNilaiSel(
            assessmentType: AssessmentType::Predicate,
            label: $terpilih,
            tuntas: null,
        );
    }

    private function resolveNarrative(\Illuminate\Support\Collection $nilaiSubjek, int $total): ?RekapNilaiSel
    {
        if ($total === 0) {
            return null;
        }

        $terisi = $nilaiSubjek->filter(
            fn ($n) => $n->komponenPenilaian?->assessment_type === AssessmentType::Narrative && trim($n->catatan ?? '') !== ''
        )->count();

        return new RekapNilaiSel(
            assessmentType: AssessmentType::Narrative,
            label: "{$terisi}/{$total}",
            tuntas: null,
        );
    }
}
```

Simpan sebagai `app/Domains/Akademik/Services/RaporCalculationService.php` (timpa isi lama sepenuhnya).

- [x] **Step 6: Jalankan ketiga file test, pastikan SEMUA lulus**

Run: `php artisan test tests/Unit/Services/RaporCalculationServiceTest.php tests/Unit/Services/RaporCalculationServiceAssessmentTypeTest.php tests/Unit/Services/RaporCalculationServiceTypeAwareTest.php`
Expected: PASS (4 + 2 + 9 = 15 test)

- [x] **Step 7: Jalankan test `RaporCalculationCompositeKeyTest.php` existing (harus tetap lulus, memverifikasi keyBy tidak merusak isolasi key)**

Run: `php artisan test tests/Feature/Akademik/RaporCalculationCompositeKeyTest.php`
Expected: PASS (1 test) — assertion di file ini akses `$rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id]` dgn `->toBe(80.0)`/`->toBe(95.0)` LANGSUNG ke float, BUKAN DTO — file ini PERLU disesuaikan juga (lihat Step 8).

- [x] **Step 8: Retrofit `RaporCalculationCompositeKeyTest.php` ke kontrak DTO**

Ubah 2 baris assertion terakhir di file (`tests/Feature/Akademik/RaporCalculationCompositeKeyTest.php`):

```php
    expect($rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id]->label)->toBe('80');
    expect($rekap['rekapNilai'][$siswa->id]['elemen_cp:'.$elemen->id]->label)->toBe('95');
```

(baris `expect($rekap['mapelList'])->toHaveCount(2);` di atasnya TIDAK BERUBAH — `mapelList` yang ter-`keyBy()` tetap punya `count()` yang sama.)

- [x] **Step 9: Jalankan ulang test itu, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/RaporCalculationCompositeKeyTest.php`
Expected: PASS (1 test)

- [x] **Step 10: Lint & commit**

Run: `vendor/bin/pint --dirty --format agent`
Expected: exit sukses

```bash
git add app/Domains/Akademik/Services/RaporCalculationService.php tests/Unit/Services/RaporCalculationServiceTest.php tests/Unit/Services/RaporCalculationServiceAssessmentTypeTest.php tests/Unit/Services/RaporCalculationServiceTypeAwareTest.php tests/Feature/Akademik/RaporCalculationCompositeKeyTest.php
git commit -m "feat(akademik): RaporCalculationService type-aware (numeric/predicate/narrative) + fix keyBy composite key"
```

---

## Task 3: Fix View Key-Mismatch + Render Badge Per Tipe

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php`
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php`
- Modify: `tests/Feature/Admin/RaporControllerTest.php` (perkuat 1 test existing + tambah 1 test presisi)
- Modify: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` (tambah 1 test)

**Interfaces:**
- Consumes: `mapelList: Collection<string,Model>` (ter-`keyBy` composite key, Task 2), `rekapNilai[siswa_id][subjekKey]: ?RekapNilaiSel` (Task 1 & 2).

- [x] **Step 1: Perbaiki `_hasil.blade.php` — loop pakai key composite, render badge per tipe**

Ganti baris 74-77 (header) dan baris 98-113 (body) di `resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php`:

```blade
                            @forelse ($mapelList as $subjekKey => $mapel)
                                <th class="px-3 py-3 text-center min-w-[120px]">
                                    <span class="block text-gray-900 font-extrabold">{{ $mapel->nama }}</span>
                                </th>
                            @empty
                                <th class="px-4 py-3 text-center text-gray-400 font-medium">Belum Ada Mapel Terasesmen</th>
                            @endforelse
```

```blade
                                @forelse ($mapelList as $subjekKey => $mapel)
                                    @php
                                        $sel = $rekapNilai[$siswa->id][$subjekKey] ?? null;
                                    @endphp
                                    <td class="px-3 py-4 text-center font-extrabold text-base">
                                        @if ($sel === null)
                                            <span class="text-gray-300 font-normal text-xs">—</span>
                                        @elseif ($sel->tuntas !== null)
                                            <span class="inline-block rounded-lg px-2.5 py-1 {{ $sel->tuntas ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                                {{ $sel->label }}
                                            </span>
                                        @else
                                            <span class="inline-block rounded-lg px-2.5 py-1 bg-gray-100 text-gray-700 border border-gray-200">
                                                {{ $sel->label }}
                                            </span>
                                        @endif
                                    </td>
                                @empty
                                    <td class="px-4 py-4 text-center text-gray-300 text-xs">—</td>
                                @endforelse
```

Baris `$studentScores = collect($rekapNilai[$siswa->id] ?? [])->filter(fn ($v) => $v !== null);` dan `$generalAvg = ...` (baris 86-88, dipakai kolom "Rata-Rata Umum") TIDAK BERUBAH secara struktur, TAPI sekarang `$studentScores` berisi campuran `RekapNilaiSel` DTO (bukan float polos) — kolom "Rata-Rata Umum" HARUS diubah supaya cuma menghitung dari sel numeric:

```blade
                            @php
                                $studentScores = collect($rekapNilai[$siswa->id] ?? [])
                                    ->filter(fn ($sel) => $sel !== null && $sel->tuntas !== null)
                                    ->map(fn ($sel) => (float) $sel->label);
                                $generalAvg = $studentScores->count() > 0 ? round($studentScores->avg(), 1) : null;
                            @endphp
```

- [x] **Step 2: Perbaiki `persetujuan/show.blade.php` — key composite + badge**

File ini punya 2 loop `@foreach ($mapelList as $mapel)` (baris 31, header; baris 40, body) dan 1 lookup `$rekapNilai[$siswa->id][$mapel->id]` (baris 41). Ganti baris 29-34 (header) dan baris 40-42 (body dalam `@foreach ($siswaList as $siswa)`):

```blade
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-600">
                            <th class="px-4 py-3">Nama Siswa</th>
                            @foreach ($mapelList as $subjekKey => $mapel)
                                <th class="px-3 py-3 text-center">{{ $mapel->nama }}</th>
                            @endforeach
                        </tr>
```

```blade
                                @foreach ($mapelList as $subjekKey => $mapel)
                                    @php $sel = $rekapNilai[$siswa->id][$subjekKey] ?? null; @endphp
                                    <td class="px-3 py-3 text-center">
                                        @if ($sel === null)
                                            —
                                        @elseif ($sel->tuntas !== null)
                                            <span class="inline-block rounded-lg px-2 py-0.5 text-xs font-semibold {{ $sel->tuntas ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $sel->label }}</span>
                                        @else
                                            <span class="inline-block rounded-lg px-2 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700">{{ $sel->label }}</span>
                                        @endif
                                    </td>
                                @endforeach
```

Baris lain di file ini (judul, catatan revisi, catatan wali kelas per siswa di baris 50 ke bawah) TIDAK BERUBAH.

- [x] **Step 3: Perkuat test existing `RaporControllerTest.php` — buktikan bug key-mismatch sudah tidak ada**

Ubah test `it('displays the rapor recap page for selected class and semester', ...)` (baris 35-70) — tambahkan assertion presisi SETELAH `assertSee('88')` yang sudah ada:

```php
    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)
        ->get(route('admin.rapor.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]))
        ->assertOk()
        ->assertSee('88');

    // Bug key-mismatch (mapel->id vs SubjekPenilaianKey composite): sebelum fix,
    // kolom PER-MAPEL selalu kosong ("—") meski Rata-Rata Umum kebetulan menampilkan
    // angka yang sama -- assert badge muncul persis di sel per-mapel, bukan cuma
    // di kolom ringkasan.
    $response->assertSee('bg-emerald-50', false);
```

Tambahkan test baru di akhir file (sebelum baris kosong terakhir):

```php
it('renders the score inside the per-mapel matrix cell, not only in the class summary column (key-mismatch regression)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Matematika']);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Bahasa Indonesia']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $asesmenA = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelA->id, 'semester_id' => $semester->id]);
    $komponenA = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelA->id, 'semester_id' => $semester->id]);
    $asesmenA->komponenPenilaian()->attach($komponenA->id);
    NilaiSiswa::create(['asesmen_id' => $asesmenA->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenA->id, 'nilai_angka' => 70]);

    // mapelB TIDAK punya nilai sama sekali -- sel-nya harus tetap "-", bukan ikut menampilkan 70.
    Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelB->id, 'semester_id' => $semester->id]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)
        ->get(route('admin.rapor.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]))
        ->assertOk();

    // Sebelum fix: SEMUA sel per-mapel kosong ("—") krn key mismatch, jadi assertion
    // di bawah ini akan GAGAL pada kode lama (0 badge ter-render), membuktikan regresi tertutup.
    $response->assertSeeText('70');
});
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php`
Expected: PASS (semua test di file ini)

- [x] **Step 5: Tambah 1 test di `RaporPersetujuanControllerTest.php` — buktikan fix di halaman Persetujuan juga**

Baca dulu isi lengkap `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` (khususnya helper `siapkanAktorPersetujuan()`) sebelum menulis, lalu tambahkan test baru di akhir file:

```php
it('renders the score inside the per-mapel matrix cell on the persetujuan show page (key-mismatch regression)', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'kelas' => $kelas, 'semester' => $semester, 'siswa' => $siswa, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $guru = Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $asesmen = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 65]);

    $response = $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.show', $pengajuan));

    $response->assertOk();
    $response->assertSeeText('65');
});
```

Tambahkan `use` import yang diperlukan di atas file (`Asesmen`, `Guru`, `KomponenPenilaian`, `MataPelajaran`, `NilaiSiswa`) kalau belum ada.

- [x] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Rapor/RaporPersetujuanControllerTest.php`
Expected: PASS (semua test di file ini)

- [x] **Step 7: Lint & commit**

Run: `vendor/bin/pint --dirty --format agent`
Expected: exit sukses

```bash
git add resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php resources/views/portals/lembaga/rapor/persetujuan/show.blade.php tests/Feature/Admin/RaporControllerTest.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php
git commit -m "fix(akademik): perbaiki key-mismatch matriks rekap rapor, render badge per tipe assessment"
```

---

## Task 4: Regresi Penuh & Update Roadmap

**Files:**
- Modify: `PETA_PENGEMBANGAN.md`

- [x] **Step 1: Grep referensi liar ke pola lama yang mestinya sudah hilang**

Run: `grep -rn '\$mapel->id\]' resources/views/portals/lembaga/akademik/rapor/ resources/views/portals/lembaga/rapor/`
Expected: 0 hasil (kalau ada, berarti masih ada view lain dgn bug yang sama yang terlewat audit — STOP dan laporkan ke user).

- [x] **Step 2: Jalankan full test suite tanpa filter**

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (passed/skipped/assertions).

- [x] **Step 3: Update `PETA_PENGEMBANGAN.md`**

Di bagian `## 🔵 Roadmap Kurikulum Dinamis`, ubah baris tabel Prioritas #2 kolom "Status" dari `Belum Ada (bug diketahui sejak Sprint 2, sengaja ditunda)` menjadi:

```
✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-rapor-calculation-type-aware.md`
```

Tambahkan paragraf baru setelah tabel prioritas:

```markdown
**Prioritas #2 SELESAI (27 Agustus 2026)**: `RaporCalculationService` sekarang type-aware (numeric=rata-rata berbobot, predicate=modus dgn tie-break, narrative=completion-rate), precedence numeric>predicate>narrative per sel. Ikut ditemukan & diperbaiki bug independen yang lebih luas dari catatan awal: kolom per-mapel di Rekap Rapor & Persetujuan Rapor selalu kosong utk SEMUA jenjang (bukan cuma PAUD) krn key-mismatch (`$mapel->id` vs composite `SubjekPenilaianKey`) — sudah diperbaiki sekaligus. Dieksekusi lewat `.agents/plans/2026-08-27-akademik-rapor-calculation-type-aware.md`.
```

- [x] **Step 4: Commit**

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: tandai Prioritas 2 Roadmap Kurikulum Dinamis SELESAI"
```
