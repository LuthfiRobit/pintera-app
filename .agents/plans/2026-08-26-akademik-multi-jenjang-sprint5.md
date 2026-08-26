# Fondasi Akademik Multi-Jenjang — Sprint 5 (Konsolidasi Derivasi Kategori Jenjang Rapor) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hilangkan duplikasi derivasi "kategori jenjang rapor" — `RaporPdfDataBuilder::templateUntukJenjang()` delegasikan penentuan kategori ke `AcademicProfile::fromBentukPendidikan()->reportTemplate` (Sprint 4), bukan punya logic `if`/`in_array` sendiri, dengan fail-fast eksplisit menggantikan silent-fallback-ke-SD yang lama.

**Architecture:** Ubah 1 method di 1 file existing (`RaporPdfDataBuilder::templateUntukJenjang()`). Tidak ada file baru, tidak ada class baru, tidak ada migration. `AcademicProfile` menentukan kategori, `RaporPdfDataBuilder` tetap yang menentukan lokasi Blade view — pemisahan tanggung jawab ini dipertahankan, bukan digabung jadi satu.

**Tech Stack:** Laravel 12.63.0, Pest, PHP 8.x.

**Bergantung pada:** Sprint 4 (SELESAI — `AcademicProfile::fromBentukPendidikan()` live).

**Spec:** `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint5.md`

## Global Constraints

- TIDAK ADA `ReportBuilder` interface, `ReportEngine`, atau builder per-jenjang (`DikdasReportBuilder` dkk) — sudah di-drop total dari scope, jangan dibangun kembali dgn alasan apa pun.
- `RaporPdfDataBuilder::build()` TIDAK DISENTUH — hanya `templateUntukJenjang()` yang berubah.
- Blade template (`paud.blade.php`, `sd.blade.php`, `smp-sma.blade.php`, `smk.blade.php`) TIDAK DISENTUH.
- `isTingkatAkhir()` (method privat lain di file yang sama) TIDAK DISENTUH — domain derivasinya beda (tingkat akhir per jenjang, bukan kategori template), jangan disatukan tanpa kebutuhan nyata.
- TIDAK ADA DTO/Action/FormRequest baru — ini refactor internal derivasi, bukan boundary HTTP baru.
- Fail-fast 2 lapis WAJIB ada: Lapis 1 (`InvalidArgumentException` dari `AcademicProfile`, mengalir otomatis), Lapis 2 (`LogicException` baru sbg defense-in-depth kalau ada key `reportTemplate` valid yang lupa di-map ke Blade path).
- Branch `LogicException` (Lapis 2) TIDAK PERLU test coverage terpisah — cukup dipastikan test `AcademicProfile` (Sprint 4, sudah ada) tetap mencakup semua key valid.
- SLB → `pdf.rapor.sd` dipertahankan sbg **regression compatibility** (perilaku lama krn fallback default), BUKAN diklaim sbg keputusan domain baru bahwa SLB "memang seharusnya" sama dgn SD.
- Signature method `templateUntukJenjang(string $bentukPendidikan): string` TIDAK BERUBAH — kedua consumer (`Guru\RaporController::cetak()`, `Lembaga\Rapor\PersetujuanController::cetak()`) TIDAK PERLU diubah sama sekali.
- Non-goal (JANGAN dikerjakan): apa pun di luar 1 method ini.

---

### Task 1: Konsolidasi `templateUntukJenjang()` ke `AcademicProfile`

**Files:**
- Modify: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`
- Modify: `tests/Feature/Akademik/RaporPdfDataBuilderTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Support\AcademicProfile::fromBentukPendidikan(string): AcademicProfile` (existing, Sprint 4, tidak diubah).
- Produces: `RaporPdfDataBuilder::templateUntukJenjang(string $bentukPendidikan): string` — signature sama persis dgn sebelumnya, HANYA behavior internal & exception yang berubah untuk input di luar whitelist. Konsumen (`Guru\RaporController`, `Lembaga\Rapor\PersetujuanController`) tidak perlu tahu apa pun tentang perubahan ini.

- [ ] **Step 1: Baca ulang test existing yang HARUS diubah (BUKAN placeholder — file ini sudah ada dan berisi assertion behavior lama yang akan sengaja diubah)**

File `tests/Feature/Akademik/RaporPdfDataBuilderTest.php` baris 1-18 saat ini:
```php
<?php

use App\Domains\Akademik\Services\RaporPdfDataBuilder;

it('maps bentuk_pendidikan to the correct template, defaulting unknown values to sd', function () {
    $builder = app(RaporPdfDataBuilder::class);

    expect($builder->templateUntukJenjang('KB'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('TPA'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('SPS'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('TK'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('SMK'))->toBe('pdf.rapor.smk');
    expect($builder->templateUntukJenjang('SMP'))->toBe('pdf.rapor.smp-sma');
    expect($builder->templateUntukJenjang('SMA'))->toBe('pdf.rapor.smp-sma');
    expect($builder->templateUntukJenjang('SD'))->toBe('pdf.rapor.sd');
    expect($builder->templateUntukJenjang('SLB'))->toBe('pdf.rapor.sd');
    expect($builder->templateUntukJenjang('NILAI_TAK_DIKENAL'))->toBe('pdf.rapor.sd');
});
```
Baris terakhir (`'NILAI_TAK_DIKENAL'` → `'pdf.rapor.sd'`) menguji PERSIS behavior lama (silent fallback) yang Sprint 5 SENGAJA ubah jadi throw. **Implementer WAJIB mengubah test ini sbg bagian dari Task 1 — ini bukan "test lain yang kebetulan gagal", ini konsekuensi langsung yang sudah diantisipasi spec.**

- [ ] **Step 2: Ganti isi test (RED dulu — implementasi belum diubah, baris SLB masih akan PASS krn behavior lama belum berubah, tapi baris throw baru akan FAIL krn belum ada exception)**

Ganti seluruh isi `tests/Feature/Akademik/RaporPdfDataBuilderTest.php` baris 1-18 menjadi:
```php
<?php

use App\Domains\Akademik\Services\RaporPdfDataBuilder;

it('maps every known bentuk_pendidikan to its correct report template', function (string $bentukPendidikan, string $expectedTemplate) {
    $builder = app(RaporPdfDataBuilder::class);

    expect($builder->templateUntukJenjang($bentukPendidikan))->toBe($expectedTemplate);
})->with([
    ['KB', 'pdf.rapor.paud'],
    ['TPA', 'pdf.rapor.paud'],
    ['SPS', 'pdf.rapor.paud'],
    ['TK', 'pdf.rapor.paud'],
    ['SD', 'pdf.rapor.sd'],
    ['SMP', 'pdf.rapor.smp-sma'],
    ['SMA', 'pdf.rapor.smp-sma'],
    ['SMK', 'pdf.rapor.smk'],
    // SLB -> sd adalah regression compatibility (hasil lama krn fallback default),
    // BUKAN keputusan domain baru bahwa SLB "memang seharusnya" sama dgn SD.
    ['SLB', 'pdf.rapor.sd'],
]);

it('throws InvalidArgumentException for an unknown bentuk_pendidikan instead of silently falling back to sd', function () {
    $builder = app(RaporPdfDataBuilder::class);

    expect(fn () => $builder->templateUntukJenjang('NILAI_TAK_DIKENAL'))
        ->toThrow(InvalidArgumentException::class);
});
```
(Sisa isi file setelah baris 18 — test-test untuk `build()` — TIDAK diubah, dibiarkan apa adanya.)

Run: `php artisan test --filter=RaporPdfDataBuilderTest`
Expected: FAIL pada test kedua (`'throws InvalidArgumentException...'`) — `templateUntukJenjang()` belum diubah, `'NILAI_TAK_DIKENAL'` masih mengembalikan `'pdf.rapor.sd'` (bukan throw). Test pertama (9 mapping table-driven) harus tetap PASS di titik ini (belum ada perubahan implementasi).

- [ ] **Step 3: Ubah `RaporPdfDataBuilder::templateUntukJenjang()`**

Baca file `app/Domains/Akademik/Services/RaporPdfDataBuilder.php` dulu (WAJIB — pastikan baseline sama dgn yang dikutip di sini sebelum edit; kalau berbeda, STOP dan laporkan ke user).

Tambah `use` baru di bagian atas file (setelah `use` existing lain, urutan alfabetis mengikuti konvensi file):
```php
use App\Domains\Akademik\Support\AcademicProfile;
```
```php
use LogicException;
```

Ganti seluruh isi method `templateUntukJenjang()` (baris ~153-167 di baseline sebelum Task 1, docblock "Whitelist sama seperti field kondisional 04c..." di atasnya DIHAPUS krn sudah tidak relevan — whitelist-nya sekarang di `AcademicProfile`, bukan di sini lagi) dari:
```php
    /** Whitelist sama seperti field kondisional 04c — literal duplikasi disengaja (YAGNI). */
    public function templateUntukJenjang(string $bentukPendidikan): string
    {
        if (in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true)) {
            return 'pdf.rapor.paud';
        }

        if ($bentukPendidikan === 'SMK') {
            return 'pdf.rapor.smk';
        }

        if (in_array($bentukPendidikan, ['SMP', 'SMA'], true)) {
            return 'pdf.rapor.smp-sma';
        }

        return 'pdf.rapor.sd';
    }
```
menjadi:
```php
    public function templateUntukJenjang(string $bentukPendidikan): string
    {
        return match (AcademicProfile::fromBentukPendidikan($bentukPendidikan)->reportTemplate) {
            'paud' => 'pdf.rapor.paud',
            'sd' => 'pdf.rapor.sd',
            'smp-sma' => 'pdf.rapor.smp-sma',
            'smk' => 'pdf.rapor.smk',
            // Defense-in-depth: AcademicProfile saat ini hanya mengembalikan 4 key
            // di atas (dibuktikan test AcademicProfileTest, Sprint 4). Branch ini
            // TIDAK bisa dites lewat unit test biasa (tidak ada cara alami membuat
            // reportTemplate mengembalikan key ke-5 tanpa mengubah AcademicProfile
            // itu sendiri) -- itu SAH, bukan dead code yang harus dihapus.
            default => throw new LogicException('Unsupported academic report template key.'),
        };
    }
```

- [ ] **Step 4: Jalankan test lagi**

Run: `php artisan test --filter=RaporPdfDataBuilderTest`
Expected: PASS — 9 test table-driven (kategori) + 1 test throw + seluruh test `build()` lain di file yang sama (tidak disentuh, harus tetap hijau).

- [ ] **Step 5: `php -l` dan commit**

```bash
php -l app/Domains/Akademik/Services/RaporPdfDataBuilder.php
php -l tests/Feature/Akademik/RaporPdfDataBuilderTest.php
git add app/Domains/Akademik/Services/RaporPdfDataBuilder.php tests/Feature/Akademik/RaporPdfDataBuilderTest.php
git commit -m "refactor(akademik): konsolidasi templateUntukJenjang() ke AcademicProfile, fail-fast utk bentuk_pendidikan tak dikenal"
```

---

### Task 2: Regresi Penuh (termasuk verifikasi 2 consumer tidak perlu diubah)

**Files:** Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Verifikasi 2 consumer TIDAK perlu diubah (baca, jangan edit)**

Baca `app/Http/Controllers/Guru/RaporController.php` (method `cetak()`) dan `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php` (method `cetak()`) — konfirmasi keduanya memanggil `$this->raporPdfDataBuilder->templateUntukJenjang(...)` apa adanya, tidak menangkap exception apa pun dari situ (signature return type `string` tidak berubah, jadi tidak ada breaking call-site). Kalau ternyata ada penanganan exception yang perlu ditambah di sana (mis. requirement baru muncul saat baca kode), STOP dan laporkan ke user — jangan diam-diam menambah try/catch di luar scope Task 1.

- [ ] **Step 2: Jalankan full test suite (TANPA filter), sekali, foreground, tidak ada proses `php artisan test`/`migrate` lain berjalan bersamaan**

Run: `php artisan test`
Expected: 0 failed. Baseline sebelum Sprint 5 adalah **2221 passed, 4 skipped** (state akhir Sprint 4). Task 1 mengubah 1 test existing jadi 2 test baru (net +1 test) — laporkan angka NYATA dari output, jangan asumsikan.

- [ ] **Step 3: Laporkan hasil final ke user**

Ringkasan: jumlah test pass/fail (angka pasti dari run nyata), commit hash Task 1, konfirmasi hanya 2 file yang tersentuh (implementasi + test), konfirmasi 2 consumer controller tidak diubah sama sekali.

## Self-Review

- Cakupan spec: §1 (implementasi) → Task 1 Step 3; §2 (test matrix: regresi 9 mapping + SLB compatibility note + unknown throws + LogicException tanpa test terpisah) → Task 1 Step 2 & Global Constraints; Keputusan Desain poin 5 (fail-fast 2 lapis, konsekuensi ke 2 consumer) → Task 2 Step 1 (verifikasi eksplisit, bukan diasumsikan aman).
- Placeholder scan: tidak ada. Test existing yang HARUS diubah (bukan file baru) dikutip lengkap isi lama & baru-nya di Task 1 Step 1-2 — bukan cuma disebut "update test terkait" secara samar.
- Konsistensi tipe: `templateUntukJenjang(string $bentukPendidikan): string` tidak berubah di seluruh plan — ditegaskan eksplisit di Global Constraints dan Task 2 Step 1 bahwa ini alasan kenapa consumer tidak perlu disentuh.
- Baseline check: plan mengutip isi PERSIS `RaporPdfDataBuilderTest.php` baris 1-18 dan method `templateUntukJenjang()` (termasuk komentar docblock lama yang harus dihapus) — implementer diberi instruksi eksplisit verifikasi baseline cocok sebelum edit, bukan diasumsikan.
