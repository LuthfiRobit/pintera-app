# Fondasi Akademik Multi-Jenjang — Sprint 4 (Academic Profile Service) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambahkan `AcademicProfile` — satu immutable value object yang mengembalikan `learningMode` (reuse `ModePembelajaran`) dan `reportTemplate` (key abstrak baru) dari `bentuk_pendidikan`, sebagai platform default/preset untuk pre-fill UX — bukan tenant policy, bukan config tersimpan.

**Architecture:** Satu class statis (`private` constructor + factory `fromBentukPendidikan()`), murni derivasi `match()`, tanpa DB/migration, tanpa consumer refactor. Scope sengaja kecil (1 file + 1 test file) — sudah dipangkas dari outline awal 4 field jadi 2 lewat diskusi (`defaultAssessmentType` tergantikan Sprint 2, `subjectRequired` di-drop krn belum ada consumer).

**Tech Stack:** Laravel 12.63.0, Pest, PHP 8.x (readonly properties, enum reuse).

**Bergantung pada:** Tidak bergantung teknis pada Sprint 1-3. Reuse `App\Domains\Akademik\Enums\ModePembelajaran` (existing).

**Spec:** `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint4.md`

## Global Constraints

- `AcademicProfile` adalah platform default/preset, BUKAN tenant policy — docblock class WAJIB menyatakan ini eksplisit (lihat Step 2).
- `learningMode` HARUS reuse `ModePembelajaran::fromBentukPendidikan()` yang sudah ada — DILARANG menulis ulang logic `SesiMapel`/`Tematik` sendiri.
- `reportTemplate` tetap `string` polos — DILARANG membuat `enum ReportTemplate` di Sprint ini (itu concern Sprint 5).
- `bentuk_pendidikan` di luar whitelist 9 nilai (`KB`,`TPA`,`SPS`,`TK`,`SD`,`SMP`,`SMA`,`SMK`,`SLB`) WAJIB melempar `InvalidArgumentException` — DILARANG silent fallback ke `'sd'` atau nilai default lain.
- SLB → `'sd'` adalah baris eksplisit sendiri dengan komentar compatibility, BUKAN tercampur ke branch `default`.
- TIDAK ADA refactor consumer existing (`GenerateSesiHarianAction` dkk tetap pakai `ModePembelajaran` langsung) — jangan tergoda "biar kelihatan terpakai".
- TIDAK ADA test runtime untuk membuktikan constructor `private` gagal dipanggil dari luar — cukup diverifikasi lewat pembacaan source saat code review.
- Non-goal (JANGAN dikerjakan): field `defaultAssessmentType`/`subjectRequired`, refactor `RaporPdfDataBuilder::templateUntukJenjang()`, `ReportEngine`/`ReportBuilder` interface, tabel/migration/config apa pun.

---

### Task 1: `AcademicProfile` Value Object

**Files:**
- Create: `app/Domains/Akademik/Support/AcademicProfile.php`
- Test: `tests/Unit/Support/AcademicProfileTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Enums\ModePembelajaran::fromBentukPendidikan(string): ModePembelajaran` (existing, tidak diubah).
- Produces: `AcademicProfile::fromBentukPendidikan(string $bentukPendidikan): self` dengan property readonly `learningMode` (`ModePembelajaran`) dan `reportTemplate` (`string`). Tidak dikonsumsi task/sprint lain di dalam Sprint 4 ini (sengaja, lihat Global Constraints) — Sprint 5 nanti yang akan mengonsumsi `reportTemplate`.

- [ ] **Step 1: Tulis test table-driven (RED dulu — class belum ada)**

```php
<?php
// tests/Unit/Support/AcademicProfileTest.php

use App\Domains\Akademik\Enums\ModePembelajaran;
use App\Domains\Akademik\Support\AcademicProfile;

it('derives the correct learningMode and reportTemplate for every known bentuk_pendidikan', function (string $bentukPendidikan, string $expectedMode, string $expectedTemplate) {
    $profile = AcademicProfile::fromBentukPendidikan($bentukPendidikan);

    expect($profile->learningMode->name)->toBe($expectedMode);
    expect($profile->reportTemplate)->toBe($expectedTemplate);
})->with([
    ['KB', 'Tematik', 'paud'],
    ['TPA', 'Tematik', 'paud'],
    ['SPS', 'Tematik', 'paud'],
    ['TK', 'Tematik', 'paud'],
    ['SD', 'Tematik', 'sd'],
    ['SMP', 'SesiMapel', 'smp-sma'],
    ['SMA', 'SesiMapel', 'smp-sma'],
    ['SMK', 'SesiMapel', 'smk'],
    ['SLB', 'Tematik', 'sd'],
]);

it('keeps learningMode identical to calling ModePembelajaran::fromBentukPendidikan() directly, for every known bentuk_pendidikan', function (string $bentukPendidikan) {
    $profile = AcademicProfile::fromBentukPendidikan($bentukPendidikan);

    expect($profile->learningMode)->toBe(ModePembelajaran::fromBentukPendidikan($bentukPendidikan));
})->with(['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB']);

it('throws for an unknown bentuk_pendidikan instead of silently falling back', function () {
    expect(fn () => AcademicProfile::fromBentukPendidikan('XYZ'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported bentuk_pendidikan: XYZ');
});

it('throws for an empty string bentuk_pendidikan', function () {
    expect(fn () => AcademicProfile::fromBentukPendidikan(''))
        ->toThrow(InvalidArgumentException::class);
});
```

Run: `php artisan test --filter=AcademicProfileTest`
Expected: FAIL — `Class "App\Domains\Akademik\Support\AcademicProfile" not found`.

- [ ] **Step 2: Implementasi `AcademicProfile`**

```php
<?php
// app/Domains/Akademik/Support/AcademicProfile.php

namespace App\Domains\Akademik\Support;

use App\Domains\Akademik\Enums\ModePembelajaran;
use InvalidArgumentException;

/**
 * Immutable value object yang menyediakan platform defaults untuk pre-fill
 * UX (mis. mode pembelajaran, kunci template rapor). BUKAN sumber kebenaran
 * konfigurasi tenant dan TIDAK BOLEH dipakai untuk menimpa nilai yang sudah
 * dipilih/disimpan admin -- lihat spec Sprint 4 §Keputusan Desain poin 1.
 */
final class AcademicProfile
{
    private function __construct(
        public readonly ModePembelajaran $learningMode,
        public readonly string $reportTemplate,
    ) {}

    public static function fromBentukPendidikan(string $bentukPendidikan): self
    {
        return new self(
            learningMode: ModePembelajaran::fromBentukPendidikan($bentukPendidikan),
            reportTemplate: match (true) {
                in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true) => 'paud',
                $bentukPendidikan === 'SMK' => 'smk',
                in_array($bentukPendidikan, ['SMP', 'SMA'], true) => 'smp-sma',
                $bentukPendidikan === 'SD' => 'sd',
                // SLB tidak punya cabang eksplisit di RaporPdfDataBuilder::templateUntukJenjang()
                // production saat ini -- jatuh ke default yang sama dgn SD. Baris ini SENGAJA
                // meniru compatibility behavior itu, BUKAN klaim kebutuhan pelaporan SLB identik
                // dgn SD. Re-evaluasi/pemisahan template SLB didorong ke desain Sprint 5.
                $bentukPendidikan === 'SLB' => 'sd',
                default => throw new InvalidArgumentException("Unsupported bentuk_pendidikan: {$bentukPendidikan}"),
            },
        );
    }
}
```

- [ ] **Step 3: Jalankan test lagi (harus PASS setelah implementasi Step 2)**

Run: `php artisan test --filter=AcademicProfileTest`
Expected: PASS — Pest melaporkan tiap baris `->with()` sbg test terpisah, jadi 4 `it()` di atas (9 baris + 9 baris + 1 + 1) akan tampak sbg 20 test individual, semua PASS.

- [ ] **Step 4: Verifikasi manual encapsulation (bukan test runtime — sesuai Global Constraints)**

Baca ulang `app/Domains/Akademik/Support/AcademicProfile.php` yang baru ditulis, konfirmasi:
- `__construct` bertanda `private` (bukan `public`/tanpa modifier).
- Class bertanda `final`.
- Kedua property bertanda `readonly`.

Ini bukan langkah otomatis — cukup baca file dan centang manual, TIDAK PERLU menulis test Pest yang mencoba `new AcademicProfile(...)` dari luar class untuk membuktikan itu gagal (sesuai keputusan eksplisit di spec §2).

- [ ] **Step 5: `php -l` dan commit**

```bash
php -l app/Domains/Akademik/Support/AcademicProfile.php
php -l tests/Unit/Support/AcademicProfileTest.php
git add app/Domains/Akademik/Support/AcademicProfile.php tests/Unit/Support/AcademicProfileTest.php
git commit -m "feat(akademik): tambah AcademicProfile - platform default preset (learningMode + reportTemplate)"
```

---

### Task 2: Regresi Penuh

**Files:** Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan full test suite (TANPA filter), sekali, foreground, tidak ada proses `php artisan test`/`migrate` lain berjalan bersamaan**

Run: `php artisan test`
Expected: 0 failed. Catat jumlah pass persis (bukan "tampak hijau") — baseline sebelum Sprint 4 adalah 2201 passed, 4 skipped (dari Sprint 3 setelah fix review). Task 1 menambah 20 test baru (lihat Step 3 Task 1), jadi ekspektasi akhir kira-kira 2221 passed, 4 skipped — TAPI jangan asumsikan angka pasti ini benar tanpa menjalankan; laporkan angka NYATA dari output.

- [ ] **Step 2: Laporkan hasil final ke user**

Ringkasan: jumlah test pass/fail (angka pasti dari run nyata), commit hash Task 1, konfirmasi tidak ada file lain yang tersentuh selain 2 file di Task 1.

## Self-Review

- Cakupan spec: §1 (implementasi) → Task 1 Step 2; §2 (test matrix: 9 input valid, konsistensi ModePembelajaran, unknown throws, SLB→sd) → Task 1 Step 1/3, seluruh 4 skenario test matrix punya `it()` yang bisa ditelusuri balik; Keputusan Desain poin 1 (docblock platform-default) → Task 1 Step 2 (docblock persis dikutip); poin 6 (throw bukan silent fallback) → test "unknown throws" + "empty string throws"; Global Constraints (no enum, no refactor consumer, no runtime private-ctor test) → tidak dilanggar di task manapun (dicek eksplisit tidak ada task yang menambah enum/menyentuh consumer lain/menulis test constructor).
- Placeholder scan: tidak ada "TBD"/"implement later". Test di Step 1 sudah kode lengkap dan benar sejak awal (tidak ada draft-kasar-lalu-diperbaiki-nanti).
- Konsistensi tipe: `AcademicProfile::fromBentukPendidikan(string $bentukPendidikan): self` dgn property `readonly ModePembelajaran $learningMode` dan `readonly string $reportTemplate` konsisten di Step 1 (test) dan Step 2 (implementasi).
