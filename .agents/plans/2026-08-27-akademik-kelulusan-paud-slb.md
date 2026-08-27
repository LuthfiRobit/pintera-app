# Kelulusan/Rapor Akhir PAUD + Keputusan SLB Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Perbaiki `isTingkatAkhir()` supaya TK tingkat B dianggap tingkat akhir, tambahkan section "Keterangan Kelulusan" yang belum pernah ada di rapor PDF PAUD, dan formalkan keputusan SLB memakai template SD sbg final (bukan fallback compatibility).

**Architecture:** Satu baris tambahan di map `$tingkatAkhirPerJenjang` (`RaporPdfDataBuilder::isTingkatAkhir()`), satu section Blade baru di `paud.blade.php` (pola identik `sd.blade.php`, tidak ada perubahan builder selain fix di atas), dan 2 komentar kode diperbarui (dokumentasi murni, tanpa perubahan logic) utk menutup keputusan SLB.

**Tech Stack:** Laravel 12.63.0, Pest v4, MySQL 8.0.30. Tidak ada migration, tidak ada perubahan skema.

## Global Constraints

- Map `$tingkatAkhirPerJenjang` HANYA ditambah `'TK' => 'B'` — `KB`/`TPA`/`SPS` TIDAK BOLEH ditambahkan, tidak peduli tingkatnya, selamanya `false` (business rule eksplisit: cuma TK yang punya makna kelulusan formal ke SD).
- Wording label kelulusan PAUD SAMA PERSIS dgn jenjang lain (`$labelKenaikan` dari builder, `"Keterangan Kelulusan"`/`"Keterangan Kenaikan Kelas"`) — TIDAK ADA cabang wording baru khusus TK.
- SLB tetap pakai template `sd`, `isTingkatAkhir` tetap tingkat `6` — TIDAK ADA perubahan logic apa pun utk SLB, HANYA komentar kode yang diperbarui.
- Section baru di `paud.blade.php` HARUS diuji lewat integrasi penuh `RaporPdfDataBuilder::build()` → render, BUKAN dgn menyuntik variabel Blade manual — supaya fix `isTingkatAkhir()` benar-benar ter-cover end-to-end.
- Tidak ada migration baru, tidak ada perubahan skema DB, tidak ada template SLB baru.

---

## Task 1: Fix `isTingkatAkhir()` — TK Tingkat B Jadi Tingkat Akhir

**Files:**
- Modify: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php:145-156`
- Test: `tests/Feature/Akademik/RaporPdfDataBuilderIsTingkatAkhirTest.php`

**Interfaces:**
- Produces: `RaporPdfDataBuilder::isTingkatAkhir(?string $bentukPendidikan, ?string $tingkat): bool` (private method, tidak berubah signature) — dipakai internal `build()`, hasilnya menentukan `$labelKenaikan` yang dikonsumsi Task 2.

- [x] **Step 1: Tulis test regresi 10-baris via `build()` publik (akan gagal utk TK-B krn belum di-fix)**

Pola ini konsisten dgn test existing `RaporPdfDataBuilderTest.php` (`'labels kelulusan for a Genap semester at the final tingkat of SD'`) yang menguji `isTingkatAkhir()` secara TIDAK LANGSUNG lewat `build()['isTingkatAkhir']` — method private tidak boleh dites via reflection, harus lewat API publik.

```php
<?php

use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function hasilIsTingkatAkhir(string $bentukPendidikan, string $tingkat): bool
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 2]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'tingkat' => $tingkat]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    $data = app(RaporPdfDataBuilder::class)->build($siswa, $semester);

    return $data['isTingkatAkhir'];
}

it('treats TK tingkat B as tingkat akhir (Kelulusan PAUD)', function () {
    expect(hasilIsTingkatAkhir('TK', 'B'))->toBeTrue();
});

it('does not treat TK tingkat A as tingkat akhir', function () {
    expect(hasilIsTingkatAkhir('TK', 'A'))->toBeFalse();
});

it('never treats KB as tingkat akhir, even at tingkat B', function () {
    expect(hasilIsTingkatAkhir('KB', 'B'))->toBeFalse();
});

it('never treats TPA as tingkat akhir, even at tingkat B', function () {
    expect(hasilIsTingkatAkhir('TPA', 'B'))->toBeFalse();
});

it('never treats SPS as tingkat akhir, even at tingkat B', function () {
    expect(hasilIsTingkatAkhir('SPS', 'B'))->toBeFalse();
});

it('still treats SD tingkat 6 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SD', '6'))->toBeTrue();
});

it('still treats SLB tingkat 6 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SLB', '6'))->toBeTrue();
});

it('still treats SMP tingkat 9 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SMP', '9'))->toBeTrue();
});

it('still treats SMA tingkat 12 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SMA', '12'))->toBeTrue();
});

it('still treats SMK tingkat 12 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SMK', '12'))->toBeTrue();
});
```

Simpan sebagai `tests/Feature/Akademik/RaporPdfDataBuilderIsTingkatAkhirTest.php` (Feature, bukan Unit — memakai `RefreshDatabase` implisit krn butuh factory model asli, konsisten konvensi project `.ai/rules/tests.md`: "RefreshDatabase: implisit di Feature").

- [x] **Step 2: Jalankan test, pastikan hanya test TK-B yang gagal**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderIsTingkatAkhirTest.php`
Expected: FAIL pada `'treats TK tingkat B as tingkat akhir'` (`expected true, got false`), 9 test lain PASS (perilaku lama sudah benar utk semuanya)

- [x] **Step 3: Tambah `'TK' => 'B'` ke map**

Ubah method `isTingkatAkhir()` di `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`:

```php
    private function isTingkatAkhir(?string $bentukPendidikan, ?string $tingkat): bool
    {
        $tingkatAkhirPerJenjang = [
            'TK' => 'B',
            'SD' => '6',
            'SLB' => '6',
            'SMP' => '9',
            'SMA' => '12',
            'SMK' => '12',
        ];

        return isset($tingkatAkhirPerJenjang[$bentukPendidikan]) && $tingkatAkhirPerJenjang[$bentukPendidikan] === $tingkat;
    }
```

- [x] **Step 4: Jalankan test, pastikan semua lulus**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderIsTingkatAkhirTest.php`
Expected: PASS (10 test)

- [x] **Step 5: Lint & commit**

Run: `php -l app/Domains/Akademik/Services/RaporPdfDataBuilder.php`
Expected: `No syntax errors detected`

```bash
git add app/Domains/Akademik/Services/RaporPdfDataBuilder.php tests/Feature/Akademik/RaporPdfDataBuilderIsTingkatAkhirTest.php
git commit -m "fix(akademik): TK tingkat B dianggap tingkat akhir utk Keterangan Kelulusan PAUD"
```

---

## Task 2: Tambah Section "Keterangan Kelulusan" ke `paud.blade.php`

**Files:**
- Modify: `resources/views/pdf/rapor/paud.blade.php`
- Test: `tests/Feature/Akademik/RaporPdfPaudKelulusanTest.php`

**Interfaces:**
- Consumes: `RaporPdfDataBuilder::build()` (TIDAK BERUBAH — sudah menyediakan `isGenap`, `labelKenaikan`, `catatan` sejak sebelum plan ini, dibuktikan Task 1 hanya mengubah `isTingkatAkhir()` internal), `isTingkatAkhir()` hasil fix Task 1.

**PENTING**: `RaporPdfDataBuilder::build()` TIDAK PERLU diubah sama sekali di task ini — `$isGenap`, `$labelKenaikan`, `$catatan` sudah dihitung/di-query universal utk semua jenjang (baris 56, 74-76 file tsb, sudah diverifikasi di spec §4). Task ini MURNI perubahan Blade.

- [x] **Step 1: Tulis test integrasi penuh builder→view (akan gagal — section belum ada di template)**

```php
<?php

use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanSiswaPaud(string $bentukPendidikan, string $tingkat, int $urutanSemester): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => $urutanSemester]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'tingkat' => $tingkat]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    return compact('lembaga', 'kelas', 'semester', 'siswa');
}

it('renders Keterangan Kelulusan with the correct keterangan_kenaikan content for TK tingkat B on Genap semester', function () {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaPaud('TK', 'B', 2);
    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray([
        'siswa_id' => $siswa->id,
        'semester_id' => $semester->id,
        'keterangan_kenaikan' => 'Siap melanjutkan ke SD',
    ]));

    $data = app(RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);
    $html = view('pdf.rapor.paud', $data)->render();

    expect($html)->toContain('Keterangan Kelulusan');
    expect($html)->toContain('Siap melanjutkan ke SD');
});

it('does not render the kenaikan/kelulusan section at all on Ganjil semester', function () {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaPaud('TK', 'B', 1);
    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray([
        'siswa_id' => $siswa->id,
        'semester_id' => $semester->id,
        'keterangan_kenaikan' => 'Tidak seharusnya muncul',
    ]));

    $data = app(RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);
    $html = view('pdf.rapor.paud', $data)->render();

    expect($html)->not->toContain('Keterangan Kelulusan');
    expect($html)->not->toContain('Keterangan Kenaikan Kelas');
    expect($html)->not->toContain('Tidak seharusnya muncul');
});

it('renders Keterangan Kenaikan Kelas (not Kelulusan) for TK tingkat A on Genap semester', function () {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaPaud('TK', 'A', 2);

    $data = app(RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);
    $html = view('pdf.rapor.paud', $data)->render();

    expect($html)->toContain('Keterangan Kenaikan Kelas');
    expect($html)->not->toContain('Keterangan Kelulusan');
});

it('never renders Keterangan Kelulusan for KB/TPA/SPS at tingkat B, even on Genap semester', function (string $bentukPendidikan) {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaPaud($bentukPendidikan, 'B', 2);

    $data = app(RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);
    $html = view('pdf.rapor.paud', $data)->render();

    expect($html)->toContain('Keterangan Kenaikan Kelas');
    expect($html)->not->toContain('Keterangan Kelulusan');
})->with(['KB', 'TPA', 'SPS']);
```

Simpan sebagai `tests/Feature/Akademik/RaporPdfPaudKelulusanTest.php`.

- [x] **Step 2: Jalankan test, pastikan gagal (section belum ada)**

Run: `php artisan test tests/Feature/Akademik/RaporPdfPaudKelulusanTest.php`
Expected: FAIL pada semua test yang mengharapkan `'Keterangan Kelulusan'`/`'Keterangan Kenaikan Kelas'` muncul (section belum ada sama sekali di template — assertion `toContain` gagal). Test kedua (`'does not render ... on Ganjil semester'`) mungkin PASS kebetulan (krn section memang belum ada), itu tidak masalah — akan tetap PASS setelah fix krn section-nya memang tidak boleh muncul di kondisi itu.

- [x] **Step 3: Tambah section ke `paud.blade.php`**

Baca dulu isi lengkap `resources/views/pdf/rapor/paud.blade.php` (73 baris) untuk konfirmasi baris terakhir sebelum `@include('pdf.rapor._tanda-tangan')` — lalu tambahkan section baru TEPAT SEBELUM baris `@include('pdf.rapor._tanda-tangan')`:

```blade
    @if ($isGenap)
        <h2 style="font-size: 13px; margin-top: 14px;">{{ $labelKenaikan }}</h2>
        <p>{{ $catatan?->keterangan_kenaikan ?: '-' }}</p>
    @endif

    @include('pdf.rapor._tanda-tangan')
```

Baris lain di file (Capaian Pembelajaran, Pertumbuhan Fisik, Catatan Wali Kelas) TIDAK BERUBAH.

- [x] **Step 4: Jalankan test, pastikan semua lulus**

Run: `php artisan test tests/Feature/Akademik/RaporPdfPaudKelulusanTest.php`
Expected: PASS (6 test — 3 test biasa + 1 test dataset `->with(['KB','TPA','SPS'])` yang jalan 3x)

- [x] **Step 5: Jalankan test existing `RaporPdfDataBuilderTest.php` — pastikan render PAUD lama tidak rusak**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderTest.php`
Expected: PASS (semua test existing, termasuk `'renders paud and sd blade templates successfully'`)

- [x] **Step 6: Lint & commit**

Run: `vendor/bin/pint --dirty --format agent`
Expected: exit sukses

```bash
git add resources/views/pdf/rapor/paud.blade.php tests/Feature/Akademik/RaporPdfPaudKelulusanTest.php
git commit -m "feat(akademik): tambah section Keterangan Kelulusan ke rapor PDF PAUD"
```

---

## Task 3: Formalkan Keputusan SLB (Komentar Saja) & Regresi Penuh

**Files:**
- Modify: `app/Domains/Akademik/Services/AcademicProfile.php:30-34`
- Modify: `tests/Feature/Akademik/RaporPdfDataBuilderTest.php:18-19`
- Modify: `PETA_PENGEMBANGAN.md`

**PENTING**: task ini TIDAK mengubah logic/behavior apa pun — murni komentar kode + dokumentasi. Tidak ada step TDD (tidak ada test baru yang gagal/lulus, krn tidak ada behavior yang berubah).

- [x] **Step 1: Update komentar di `AcademicProfile.php`**

Ganti baris 30-34 (blok komentar sebelum `$bentukPendidikan === 'SLB' => 'sd',`):

```php
                // SLB memakai template SD sbg KEPUTUSAN FINAL yang disengaja (diformalkan
                // Prioritas #3 Roadmap Kurikulum Dinamis, 27 Agustus 2026) -- bukan fallback
                // diam-diam. Tidak ada pelanggan SLB nyata dgn kebutuhan struktur rapor
                // berbeda saat ini; keputusan ini revisable kalau itu berubah.
                $bentukPendidikan === 'SLB' => 'sd',
```

- [x] **Step 2: Update komentar dataset test di `RaporPdfDataBuilderTest.php`**

Ganti baris 18-19 (komentar sebelum `['SLB', 'pdf.rapor.sd'],`):

```php
    // SLB -> sd adalah keputusan final yang disengaja (Prioritas #3, 27 Agustus 2026),
    // bukan lagi fallback compatibility -- lihat AcademicProfile.php.
    ['SLB', 'pdf.rapor.sd'],
```

- [x] **Step 3: Jalankan test yang disentuh, pastikan tetap lulus (murni komentar, tidak ada perubahan assertion)**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderTest.php`
Expected: PASS (semua test, sama seperti Task 2 Step 5 — tidak ada regresi)

- [x] **Step 4: Grep akhir — pastikan tidak ada komentar basi lain yang terlewat**

Run: `grep -rn "Sprint 5\|regression compatibility" app/ tests/ --include="*.php"`
Expected: 0 hasil (kalau ada sisa, STOP dan laporkan ke user — berarti ada lokasi lain yang tidak tercatat di spec)

- [x] **Step 5: Jalankan full test suite tanpa filter**

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (passed/skipped/assertions).

- [x] **Step 6: Update `PETA_PENGEMBANGAN.md`**

Di bagian `## 🔵 Roadmap Kurikulum Dinamis`, ubah baris tabel Prioritas #3 kolom "Status" dari `Belum Ada` menjadi:

```
✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-kelulusan-paud-slb.md`
```

Tambahkan paragraf baru setelah tabel prioritas:

```markdown
**Prioritas #3 SELESAI (27 Agustus 2026)**: `isTingkatAkhir()` sekarang mengenali TK tingkat B sbg tingkat akhir (Keterangan Kelulusan PAUD) — `KB`/`TPA`/`SPS` sengaja tetap tidak pernah dianggap tingkat akhir. Ikut ditemukan & diperbaiki gap yang lebih mendasar: template PDF `paud.blade.php` ternyata belum pernah punya section "Keterangan Kelulusan"/"Keterangan Kenaikan Kelas" sama sekali (beda dari SD/SMP-SMA/SMK) — sudah ditambahkan. Keputusan SLB tetap pakai template SD diformalkan jadi final (bukan fallback compatibility). Dieksekusi lewat `.agents/plans/2026-08-27-akademik-kelulusan-paud-slb.md`.
```

- [x] **Step 7: Commit**

```bash
git add app/Domains/Akademik/Services/AcademicProfile.php tests/Feature/Akademik/RaporPdfDataBuilderTest.php PETA_PENGEMBANGAN.md
git commit -m "docs: formalkan keputusan SLB (template SD final), tandai Prioritas 3 Roadmap Kurikulum Dinamis SELESAI"
```
