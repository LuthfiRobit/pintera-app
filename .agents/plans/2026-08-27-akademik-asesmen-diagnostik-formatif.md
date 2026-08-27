# Asesmen Diagnostik & Formatif Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Buka 3 jenis Asesmen yang belum pernah bisa dibuat guru (Diagnostik Kognitif, Diagnostik Non-Kognitif, Formatif) sambil memastikan `RaporCalculationService` tidak pernah mengagregasi ketiganya ke rapor.

**Architecture:** `JenisAsesmen::v1Didukung()` di-retire, diganti `JenisAsesmen::cases()` (form/validasi guru, semua 6 jenis) dan `JenisAsesmen::masukRapor()` (filter `RaporCalculationService`, tetap 3 Sumatif). Satu baris `whereIn('jenis', ...)` ditambahkan ke query `Asesmen` di `hitungRekapKelas()` — satu-satunya titik query di method itu, jadi filter otomatis berlaku ke seluruh downstream.

**Tech Stack:** Laravel 12.63.0, Pest v4, MySQL 8.0.30. Tidak ada migration, tidak ada perubahan skema.

## Global Constraints

- `komponen_id` tetap wajib ≥1 utk SEMUA jenis Asesmen (termasuk Diagnostik/Formatif) — TIDAK ADA jalur Asesmen tanpa TP/subjek.
- `jenis` dan `assessment_type` TETAP ortogonal — TIDAK ADA constraint silang jenis×assessment_type di FormRequest mana pun.
- `v1Didukung()` DIHAPUS TOTAL (bukan dipertahankan sbg alias/deprecated) — diganti `JenisAsesmen::cases()` (form/validasi) dan `JenisAsesmen::masukRapor()` (filter rapor, SATU-SATUNYA sumber kebenaran soal "jenis apa yang masuk rapor").
- `RaporCalculationService::hitungRekapKelas()` filter `whereIn('jenis', JenisAsesmen::masukRapor())` adalah BLOCKER wajib — tanpa ini Diagnostik/Formatif mencemari rapor begitu dibuka ke guru.
- Tidak ada halaman rekap/ringkasan/analytics baru — `AsesmenController::show()`/`updateNilai()` TIDAK BERUBAH, direuse apa adanya utk semua jenis.
- Tidak ada migration baru, tidak ada perubahan skema DB.

---

## Task 1: Retire `v1Didukung()`, Tambah `masukRapor()`

**Files:**
- Modify: `app/Domains/Akademik/Enums/JenisAsesmen.php`
- Modify: `tests/Unit/Enums/JenisAsesmenTest.php`

**Interfaces:**
- Produces: `JenisAsesmen::masukRapor(): array` (3 case Sumatif) — dipakai Task 3 (`RaporCalculationService`). `JenisAsesmen::cases()` (bawaan PHP enum, 6 case) — dipakai Task 2.

- [x] **Step 1: Retrofit test enum ke kontrak baru (akan gagal — `masukRapor()` belum ada, `v1Didukung()` masih ada)**

Ganti isi `tests/Unit/Enums/JenisAsesmenTest.php` jadi:

```php
<?php

use App\Domains\Akademik\Enums\JenisAsesmen;

it('defines all 6 cases from the design spec', function () {
    expect(array_column(JenisAsesmen::cases(), 'value'))->toBe([
        'diagnostik_kognitif',
        'diagnostik_non_kognitif',
        'formatif',
        'sumatif_lingkup_materi',
        'sumatif_akhir_semester',
        'sumatif_akhir_jenjang',
    ]);
});

it('exposes exactly the 3 sumatif cases as sources of rapor calculation', function () {
    expect(JenisAsesmen::masukRapor())->toBe([
        JenisAsesmen::SumatifLingkupMateri,
        JenisAsesmen::SumatifAkhirSemester,
        JenisAsesmen::SumatifAkhirJenjang,
    ]);
});

it('no longer has the retired v1Didukung() method', function () {
    expect(method_exists(JenisAsesmen::class, 'v1Didukung'))->toBeFalse();
});

it('returns correct Indonesian labels for all 6 cases', function () {
    expect(JenisAsesmen::DiagnostikKognitif->label())->toBe('Diagnostik Kognitif');
    expect(JenisAsesmen::DiagnostikNonKognitif->label())->toBe('Diagnostik Non-Kognitif');
    expect(JenisAsesmen::Formatif->label())->toBe('Formatif');
    expect(JenisAsesmen::SumatifLingkupMateri->label())->toBe('Sumatif Lingkup Materi');
    expect(JenisAsesmen::SumatifAkhirSemester->label())->toBe('Sumatif Akhir Semester');
    expect(JenisAsesmen::SumatifAkhirJenjang->label())->toBe('Sumatif Akhir Jenjang');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Enums/JenisAsesmenTest.php`
Expected: FAIL — `masukRapor()` tidak ditemukan (test 2), `v1Didukung()` masih ada (test 3 gagal krn `method_exists` masih `true`)

- [x] **Step 3: Retire `v1Didukung()`, tambah `masukRapor()`**

Ganti isi `app/Domains/Akademik/Enums/JenisAsesmen.php` jadi:

```php
<?php

namespace App\Domains\Akademik\Enums;

enum JenisAsesmen: string
{
    case DiagnostikKognitif = 'diagnostik_kognitif';
    case DiagnostikNonKognitif = 'diagnostik_non_kognitif';
    case Formatif = 'formatif';
    case SumatifLingkupMateri = 'sumatif_lingkup_materi';
    case SumatifAkhirSemester = 'sumatif_akhir_semester';
    case SumatifAkhirJenjang = 'sumatif_akhir_jenjang';

    public function label(): string
    {
        return match ($this) {
            self::DiagnostikKognitif => 'Diagnostik Kognitif',
            self::DiagnostikNonKognitif => 'Diagnostik Non-Kognitif',
            self::Formatif => 'Formatif',
            self::SumatifLingkupMateri => 'Sumatif Lingkup Materi',
            self::SumatifAkhirSemester => 'Sumatif Akhir Semester',
            self::SumatifAkhirJenjang => 'Sumatif Akhir Jenjang',
        };
    }

    /**
     * Jenis asesmen yang secara semantik merupakan SUMBER PERHITUNGAN RAPOR
     * (dipakai RaporCalculationService::hitungRekapKelas() sebagai satu-satunya
     * filter). Diagnostik dan Formatif SENGAJA tidak termasuk -- keduanya
     * asesmen untuk proses pembelajaran (pemetaan kesiapan belajar, penyesuaian
     * metode ajar), bukan komponen nilai rapor.
     *
     * Kalau menambah case baru ke enum ini di masa depan, WAJIB secara sadar
     * memutuskan apakah case itu masuk daftar ini atau tidak -- jangan
     * dibiarkan default masuk/keluar tanpa keputusan eksplisit.
     *
     * @return array<int, self>
     */
    public static function masukRapor(): array
    {
        return [
            self::SumatifLingkupMateri,
            self::SumatifAkhirSemester,
            self::SumatifAkhirJenjang,
        ];
    }
}
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Enums/JenisAsesmenTest.php`
Expected: PASS (4 test)

- [x] **Step 5: Lint & commit**

Run: `php -l app/Domains/Akademik/Enums/JenisAsesmen.php`
Expected: `No syntax errors detected`

```bash
git add app/Domains/Akademik/Enums/JenisAsesmen.php tests/Unit/Enums/JenisAsesmenTest.php
git commit -m "feat(akademik): retire JenisAsesmen::v1Didukung(), tambah masukRapor()"
```

---

## Task 2: Buka 6 Jenis ke Form Guru + Validasi Ikut Enum

**Files:**
- Modify: `app/Http/Controllers/Guru/AsesmenController.php:70`
- Modify: `app/Http/Requests/Akademik/StoreAsesmenRequest.php`
- Modify: `tests/Feature/Guru/AsesmenControllerTest.php`

**Interfaces:**
- Consumes: `JenisAsesmen::cases()` (Task 1, bawaan PHP enum).
- Produces: tidak ada interface baru — `AsesmenController::create()`/`store()` signature TIDAK BERUBAH.

**PENTING**: `Rule::enum(JenisAsesmen::class)` menerima SEMUA 6 case begitu diterapkan — ini SATU-SATUNYA perubahan validasi yang dibutuhkan, tidak perlu daftar hardcode baru menyebut 6 nilai.

- [x] **Step 1: Retrofit test guru — ganti test penolakan Formatif jadi 3 test sukses (akan gagal — form masih terbatas 3 Sumatif)**

Baca dulu `tests/Feature/Guru/AsesmenControllerTest.php` baris 226-254 (test `'rejects a jenis outside the v1-supported sumatif options'`) — HAPUS test itu, ganti dengan 3 test baru di posisi yang sama:

```php
it('allows creating an asesmen with jenis Diagnostik Kognitif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::DiagnostikKognitif->value,
        'judul' => 'Diagnostik Awal Semester',
        'tanggal' => now()->toDateString(),
        'komponen_id' => [$komponen->id],
    ])->assertRedirect();

    expect(Asesmen::where('judul', 'Diagnostik Awal Semester')->where('jenis', JenisAsesmen::DiagnostikKognitif)->exists())->toBeTrue();
});

it('allows creating an asesmen with jenis Diagnostik Non-Kognitif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::DiagnostikNonKognitif->value,
        'judul' => 'Survei Minat Belajar',
        'tanggal' => now()->toDateString(),
        'komponen_id' => [$komponen->id],
    ])->assertRedirect();

    expect(Asesmen::where('judul', 'Survei Minat Belajar')->where('jenis', JenisAsesmen::DiagnostikNonKognitif)->exists())->toBeTrue();
});

it('allows creating an asesmen with jenis Formatif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruAsesmen($guru);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::Formatif->value,
        'judul' => 'Latihan Formatif Bab 1',
        'tanggal' => now()->toDateString(),
        'komponen_id' => [$komponen->id],
    ])->assertRedirect();

    expect(Asesmen::where('judul', 'Latihan Formatif Bab 1')->where('jenis', JenisAsesmen::Formatif)->exists())->toBeTrue();
});
```

- [x] **Step 2: Jalankan test, pastikan 3 test baru gagal (form masih tolak jenis selain Sumatif)**

Run: `php artisan test tests/Feature/Guru/AsesmenControllerTest.php --filter="Diagnostik|Formatif"`
Expected: FAIL — `assertRedirect()` gagal krn `StoreAsesmenRequest` masih menolak `jenis` selain 3 Sumatif (redirect balik dgn session error, bukan ke `guru.asesmen.show`)

- [x] **Step 3: Buka form guru ke semua 6 jenis**

Ubah `app/Http/Controllers/Guru/AsesmenController.php` baris 70:

```php
            'jenisAsesmenList' => JenisAsesmen::cases(),
```

- [x] **Step 4: Validasi ikut enum, bukan hardcode**

Ubah `app/Http/Requests/Akademik/StoreAsesmenRequest.php` — tambahkan import:

```php
use App\Domains\Akademik\Enums\JenisAsesmen;
```

lalu ganti baris `'jenis' => ['required', 'in:sumatif_lingkup_materi,sumatif_akhir_semester,sumatif_akhir_jenjang'],` menjadi:

```php
            'jenis' => ['required', Rule::enum(JenisAsesmen::class)],
```

`komponen_id` (baris `'komponen_id' => ['required', 'array', 'min:1']`) TIDAK BERUBAH.

- [x] **Step 5: Jalankan test, pastikan semua lulus**

Run: `php artisan test tests/Feature/Guru/AsesmenControllerTest.php`
Expected: PASS (semua test di file, termasuk 3 test baru dan test lain yang sudah ada — `'rejects creating an asesmen with no komponen_id selected'` dkk TIDAK terpengaruh krn masih pakai `JenisAsesmen::SumatifLingkupMateri`)

- [x] **Step 6: Lint & commit**

Run: `vendor/bin/pint --dirty --format agent`
Expected: exit sukses

```bash
git add app/Http/Controllers/Guru/AsesmenController.php app/Http/Requests/Akademik/StoreAsesmenRequest.php tests/Feature/Guru/AsesmenControllerTest.php
git commit -m "feat(akademik): buka 6 jenis asesmen ke form guru, validasi ikut enum"
```

---

## Task 3: Filter `RaporCalculationService` — Blocker Wajib

**Files:**
- Modify: `app/Domains/Akademik/Services/RaporCalculationService.php:5-31`
- Test: `tests/Feature/Akademik/RaporCalculationJenisAsesmenTest.php`

**Interfaces:**
- Consumes: `JenisAsesmen::masukRapor()` (Task 1).
- Produces: `RaporCalculationService::hitungRekapKelas()` — signature TIDAK BERUBAH, hanya perilaku internal (Asesmen berjenis Diagnostik/Formatif tidak pernah diagregasi).

- [x] **Step 1: Tulis test exclusion (akan gagal — filter belum ada)**

```php
<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
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

function siapkanSubjekJenisAsesmen(): array
{
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    return compact('mapel', 'lembaga', 'semester', 'kelas', 'guru', 'siswa');
}

function buatAsesmenDenganNilai(string $jenis, array $ctx, int $nilaiAngka): Asesmen
{
    $komponen = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $ctx['mapel']->id, 'semester_id' => $ctx['semester']->id,
        'lembaga_id' => $ctx['lembaga'], 'assessment_type' => 'numeric',
    ]);
    $asesmen = Asesmen::factory()->create([
        'guru_id' => $ctx['guru']->id, 'kelas_id' => $ctx['kelas']->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $ctx['mapel']->id, 'semester_id' => $ctx['semester']->id, 'jenis' => $jenis,
    ]);
    $asesmen->komponenPenilaian()->attach($komponen->id);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $ctx['siswa']->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => $nilaiAngka]);

    return $asesmen;
}

it('excludes DiagnostikKognitif entirely from rekap rapor', function () {
    $ctx = siapkanSubjekJenisAsesmen();
    buatAsesmenDenganNilai(JenisAsesmen::DiagnostikKognitif->value, $ctx, 90);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($ctx['kelas'], $ctx['semester']);

    expect($rekap['rekapNilai'][$ctx['siswa']->id]['mata_pelajaran:'.$ctx['mapel']->id])->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('excludes DiagnostikNonKognitif entirely from rekap rapor', function () {
    $ctx = siapkanSubjekJenisAsesmen();
    buatAsesmenDenganNilai(JenisAsesmen::DiagnostikNonKognitif->value, $ctx, 90);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($ctx['kelas'], $ctx['semester']);

    expect($rekap['rekapNilai'][$ctx['siswa']->id]['mata_pelajaran:'.$ctx['mapel']->id])->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('excludes Formatif entirely from rekap rapor', function () {
    $ctx = siapkanSubjekJenisAsesmen();
    buatAsesmenDenganNilai(JenisAsesmen::Formatif->value, $ctx, 90);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($ctx['kelas'], $ctx['semester']);

    expect($rekap['rekapNilai'][$ctx['siswa']->id]['mata_pelajaran:'.$ctx['mapel']->id])->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('keeps rekap at the Sumatif value even when Formatif and Diagnostik nilai exist for the same siswa+subjek+semester', function () {
    $ctx = siapkanSubjekJenisAsesmen();

    $asesmenSumatif = buatAsesmenDenganNilai(JenisAsesmen::SumatifLingkupMateri->value, $ctx, 88);
    $asesmenFormatif = buatAsesmenDenganNilai(JenisAsesmen::Formatif->value, $ctx, 100);
    $asesmenDiagnostik = buatAsesmenDenganNilai(JenisAsesmen::DiagnostikKognitif->value, $ctx, 100);

    // WAJIB dibuktikan dulu: data non-rapor benar-benar tersimpan dgn nilai yang benar --
    // supaya exclusion di bawah ini terbukti krn FILTER, bukan krn datanya gagal dibuat.
    expect(Asesmen::where('id', $asesmenFormatif->id)->where('jenis', JenisAsesmen::Formatif)->exists())->toBeTrue();
    expect(NilaiSiswa::where('asesmen_id', $asesmenFormatif->id)->where('siswa_id', $ctx['siswa']->id)->first()->nilai_angka)->toBe(100);
    expect(Asesmen::where('id', $asesmenDiagnostik->id)->where('jenis', JenisAsesmen::DiagnostikKognitif)->exists())->toBeTrue();
    expect(NilaiSiswa::where('asesmen_id', $asesmenDiagnostik->id)->where('siswa_id', $ctx['siswa']->id)->first()->nilai_angka)->toBe(100);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($ctx['kelas'], $ctx['semester']);
    $sel = $rekap['rekapNilai'][$ctx['siswa']->id]['mata_pelajaran:'.$ctx['mapel']->id];

    expect($sel->label)->toBe('88');
    expect($rekap['classAvg'])->toBe(88.0);
});
```

Simpan sebagai `tests/Feature/Akademik/RaporCalculationJenisAsesmenTest.php`.

- [x] **Step 2: Jalankan test, pastikan 3 test exclusion pertama gagal (belum ada filter), test ke-4 gagal juga (nilai jadi campuran, bukan 88)**

Run: `php artisan test tests/Feature/Akademik/RaporCalculationJenisAsesmenTest.php`
Expected: FAIL pada 4 test (semua Asesmen apa pun jenisnya ikut teragregasi saat ini)

- [x] **Step 3: Tambah filter ke `RaporCalculationService`**

Tambahkan import di `app/Domains/Akademik/Services/RaporCalculationService.php` (setelah `use App\Domains\Akademik\Enums\AssessmentType;`):

```php
use App\Domains\Akademik\Enums\JenisAsesmen;
```

Ganti query `$asesmenList` (baris 28-31):

```php
        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->whereIn('jenis', JenisAsesmen::masukRapor())
            ->with(['subjek', 'komponenPenilaian'])
            ->get();
```

Tidak ada baris lain di file ini yang berubah.

- [x] **Step 4: Jalankan test, pastikan semua lulus**

Run: `php artisan test tests/Feature/Akademik/RaporCalculationJenisAsesmenTest.php`
Expected: PASS (4 test)

- [x] **Step 5: Jalankan seluruh test `RaporCalculationService` existing — pastikan tidak ada regresi (Sumatif tetap masuk seperti biasa)**

Run: `php artisan test tests/Unit/Services/RaporCalculationServiceTest.php tests/Unit/Services/RaporCalculationServiceAssessmentTypeTest.php tests/Unit/Services/RaporCalculationServiceTypeAwareTest.php tests/Feature/Akademik/RaporCalculationCompositeKeyTest.php tests/Feature/Admin/RaporControllerTest.php`
Expected: PASS (semua test — `database/factories/AsesmenFactory.php:26` sudah diverifikasi default `jenis` ke `JenisAsesmen::SumatifLingkupMateri`, termasuk `masukRapor()`, jadi test-test existing yang pakai `Asesmen::factory()` tanpa `jenis` eksplisit TIDAK terpengaruh filter baru ini)

- [x] **Step 6: Lint & commit**

Run: `php -l app/Domains/Akademik/Services/RaporCalculationService.php`
Expected: `No syntax errors detected`

```bash
git add app/Domains/Akademik/Services/RaporCalculationService.php tests/Feature/Akademik/RaporCalculationJenisAsesmenTest.php
git commit -m "fix(akademik): RaporCalculationService kecualikan Diagnostik & Formatif dari rekap rapor"
```

---

## Task 4: Test Usability End-to-End (Create → Input Nilai → Show → Exclusion)

**Files:**
- Test: `tests/Feature/Guru/AsesmenDiagnostikFormatifUsabilityTest.php`

**Interfaces:**
- Consumes: `AsesmenController::store()`/`show()`/`updateNilai()` (TIDAK BERUBAH, Task 2), `RaporCalculationService::hitungRekapKelas()` (Task 3).

Task ini murni test — TIDAK ADA perubahan kode produksi. Tujuannya membuktikan siklus penuh, bukan cuma satu tahap (create SAJA, atau filter rapor SAJA).

- [x] **Step 1: Tulis test siklus penuh**

```php
<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Services\RaporCalculationService;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('lets a guru create, fill, and read back a Formatif asesmen through the existing flow, while it stays excluded from rapor', function () {
    Permission::firstOrCreate(['name' => 'asesmen.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['asesmen.kelola']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'assessment_type' => 'numeric']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru->update(['user_id' => $user->id]);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    // 1. Guru membuat Asesmen Formatif.
    $storeResponse = $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::Formatif->value,
        'judul' => 'Latihan Formatif Usability',
        'tanggal' => now()->toDateString(),
        'komponen_id' => [$komponen->id],
    ]);
    $storeResponse->assertRedirect();
    $asesmen = Asesmen::where('judul', 'Latihan Formatif Usability')->firstOrFail();

    // 2. Guru buka halaman show -- harus tampil normal (reuse halaman existing).
    $this->actingAs($user)->get(route('guru.asesmen.show', $asesmen))->assertOk();

    // 3. Guru input nilai siswa.
    $updateResponse = $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswa->id => [
                $komponen->id => ['nilai_angka' => 100],
            ],
        ],
    ]);
    $updateResponse->assertRedirect(route('guru.asesmen.show', $asesmen));

    // 4. Guru buka kembali halaman show -- nilai yang tadi diisi harus TERLIHAT.
    $showAgain = $this->actingAs($user)->get(route('guru.asesmen.show', $asesmen));
    $showAgain->assertOk();
    $showAgain->assertSee('100');

    // 5. Nilai Formatif ini TIDAK PERNAH masuk rekap rapor.
    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);
    expect($rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id])->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
});
```

Simpan sebagai `tests/Feature/Guru/AsesmenDiagnostikFormatifUsabilityTest.php`.

Route `guru.asesmen.update-nilai` sudah diverifikasi persis di `routes/guru.php:19` (`Route::put('asesmen/{asesmen}/nilai', ...)->name('asesmen.update-nilai')`, di dalam group `->name('guru.')`) — nama di atas sudah benar, tidak perlu dicek ulang.

- [x] **Step 2: Jalankan test, pastikan lulus (semua kode produksi sudah benar dari Task 1-3, task ini murni verifikasi)**

Run: `php artisan test tests/Feature/Guru/AsesmenDiagnostikFormatifUsabilityTest.php`
Expected: PASS (1 test)

- [x] **Step 3: Lint & commit**

Run: `vendor/bin/pint --dirty --format agent`
Expected: exit sukses

```bash
git add tests/Feature/Guru/AsesmenDiagnostikFormatifUsabilityTest.php
git commit -m "test(akademik): buktikan siklus penuh Asesmen Formatif (create-input-show-exclusion)"
```

---

## Task 5: Regresi Penuh & Update Roadmap

**Files:**
- Modify: `PETA_PENGEMBANGAN.md`

- [x] **Step 1: Grep referensi liar ke `v1Didukung`**

Run: `grep -rn "v1Didukung" app/ resources/ tests/ --include="*.php" --include="*.blade.php"`
Expected: 0 hasil (kalau ada sisa, STOP dan laporkan ke user)

- [x] **Step 2: Jalankan full test suite tanpa filter**

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (passed/skipped/assertions).

- [x] **Step 3: Update `PETA_PENGEMBANGAN.md`**

Di bagian `## 🔵 Roadmap Kurikulum Dinamis`, ubah baris tabel Prioritas #6 kolom "Status" dari `Belum Ada` menjadi:

```
✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-asesmen-diagnostik-formatif.md`
```

Tambahkan paragraf baru setelah tabel prioritas:

```markdown
**Prioritas #6 SELESAI (27 Agustus 2026)**: Guru sekarang bisa membuat Asesmen Diagnostik Kognitif, Diagnostik Non-Kognitif, dan Formatif (sebelumnya cuma 3 varian Sumatif). `JenisAsesmen::v1Didukung()` di-retire, diganti `cases()` (form/validasi, semua 6 jenis) dan `masukRapor()` (filter rapor, tetap 3 Sumatif). Ikut ditemukan & diperbaiki blocker kritis: `RaporCalculationService::hitungRekapKelas()` sebelumnya sama sekali tidak memfilter `jenis` Asesmen — sudah diperbaiki sekaligus supaya Diagnostik/Formatif tidak pernah mencemari rapor. Dieksekusi lewat `.agents/plans/2026-08-27-akademik-asesmen-diagnostik-formatif.md`.
```

- [x] **Step 4: Commit**

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: tandai Prioritas 6 Roadmap Kurikulum Dinamis SELESAI"
```
