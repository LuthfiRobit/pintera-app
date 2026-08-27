# Asesmen Diagnostik & Formatif (Priority 6) — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks roadmap**: `PETA_PENGEMBANGAN.md` §"🔵 Roadmap Kurikulum Dinamis", Prioritas #6 — independen dari Prioritas #1-3 (SELESAI), #4-5 (sengaja ditunda menunggu pelanggan nyata).

---

## 1. Latar Belakang & Masalah

`JenisAsesmen` (`app/Domains/Akademik/Enums/JenisAsesmen.php`) punya 6 case sejak desain awal, tapi `v1Didukung()` cuma expose 3 varian Sumatif ke guru — Diagnostik Kognitif, Diagnostik Non-Kognitif, dan Formatif tidak pernah bisa dibuat lewat form guru sejak awal, padahal justru fitur pedagogi inti yang ditekankan Kurikulum Merdeka (pemetaan kesiapan belajar di awal semester, penyesuaian metode ajar selama proses — BUKAN untuk nilai rapor).

**Temuan kritis saat discovery**: `RaporCalculationService::hitungRekapKelas()` (baris 28-31) mengambil SEMUA `Asesmen` untuk `kelas_id`+`semester_id` TANPA filter `jenis` sama sekali. Ini berarti kalau Diagnostik/Formatif dibuka ke guru tanpa perbaikan ini, nilainya akan otomatis ikut tercampur ke perhitungan rapor bersama Sumatif — bug pra-existing yang baru jadi berbahaya begitu jenis baru dibuka. Perbaikan ini WAJIB, bukan opsional, dan jadi bagian tak terpisahkan dari spec ini.

## 2. Keputusan Bisnis (dari diskusi user, WAJIB diikuti persis)

1. **`komponen_id` (Tujuan Pembelajaran) tetap wajib ≥1** untuk SEMUA jenis Asesmen, termasuk Diagnostik/Formatif — TIDAK membuat jalur "asesmen umum tanpa TP/subjek". Kalau kelak ada kebutuhan nyata untuk itu, itu desain domain terpisah, bukan bagian spec ini.
2. **`jenis` (Asesmen) dan `assessment_type` (KomponenPenilaian) SEPENUHNYA ORTOGONAL** — tidak ada constraint silang (mis. "Diagnostik Non-Kognitif harus Narrative"). Guru bebas memilih `assessment_type` apa pun (Numeric/Predicate/Narrative) untuk TP yang dipakai Asesmen jenis apa pun. Tidak ada perubahan skema `KomponenPenilaian`/`NilaiSiswa` sama sekali.
3. **v1-minimal utk tampilan hasil**: guru melihat hasil Diagnostik/Formatif lewat halaman `show`/matrix input nilai yang SUDAH ADA (dipakai juga oleh Sumatif) — TIDAK ADA halaman rekap/ringkasan/analytics baru. Guru menginterpretasi sendiri dari matrix mentah.
4. **`RaporCalculationService` WAJIB mengecualikan Diagnostik & Formatif** dari perhitungan rapor — filter eksplisit berbasis method baru yang namanya menyatakan kontrak semantik "sumber rapor", bukan bergantung nama `v1Didukung()` yang jadi ambigu setelah 6 jenis dibuka semua.
5. **`v1Didukung()` di-RETIRE**, diganti 2 konsep terpisah:
   - `JenisAsesmen::cases()` — semua asesmen yang boleh dibuat guru (6 jenis, tidak perlu method baru, sudah bawaan PHP enum).
   - `JenisAsesmen::masukRapor(): array` — HANYA jenis yang secara semantik jadi sumber perhitungan rapor (3 Sumatif). Dipakai SATU-SATUNYA oleh `RaporCalculationService`.

## 3. Perubahan #1 — Retire `v1Didukung()`, Tambah `masukRapor()`

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

`v1Didukung()` DIHAPUS TOTAL (bukan dipertahankan sbg alias/deprecated — project ini tidak memakai backward-compatibility shim, lihat `CLAUDE.md`).

## 4. Perubahan #2 — Buka Semua 6 Jenis ke Form Guru

`app/Http/Controllers/Guru/AsesmenController.php` baris 70, ganti:

```php
'jenisAsesmenList' => JenisAsesmen::v1Didukung(),
```

menjadi:

```php
'jenisAsesmenList' => JenisAsesmen::cases(),
```

View `resources/views/portals/guru/akademik/asesmen/create.blade.php` TIDAK BERUBAH — sudah generik (`@foreach ($jenisAsesmenList as $jenis)`), otomatis merender 6 opsi begitu controller mengirim 6 case.

## 5. Perubahan #3 — Validasi Ikut Enum, Bukan Hardcode

`app/Http/Requests/Akademik/StoreAsesmenRequest.php` baris 39, ganti:

```php
'jenis' => ['required', 'in:sumatif_lingkup_materi,sumatif_akhir_semester,sumatif_akhir_jenjang'],
```

menjadi:

```php
'jenis' => ['required', Rule::enum(JenisAsesmen::class)],
```

(`Rule` sudah di-import di file ini; tambahkan `use App\Domains\Akademik\Enums\JenisAsesmen;`.) Ini juga menutup celah yang ditemukan discovery: sebelumnya daftar jenis divalidasi lewat string hardcode terpisah dari `v1Didukung()` — dua tempat yang harus disinkron manual. Sekarang validasi otomatis ikut SEMUA case enum, tidak perlu disentuh lagi kalau enum berubah.

`komponen_id` (baris 42-43) TIDAK BERUBAH — tetap `['required', 'array', 'min:1']` untuk semua jenis (Keputusan Bisnis #1).

## 6. Perubahan #4 — Filter `RaporCalculationService` (BLOCKER, Bukan Opsional)

`app/Domains/Akademik/Services/RaporCalculationService.php` baris 28-31, ganti:

```php
$asesmenList = Asesmen::where('kelas_id', $kelas->id)
    ->where('semester_id', $semester->id)
    ->with(['subjek', 'komponenPenilaian'])
    ->get();
```

menjadi:

```php
$asesmenList = Asesmen::where('kelas_id', $kelas->id)
    ->where('semester_id', $semester->id)
    ->whereIn('jenis', JenisAsesmen::masukRapor())
    ->with(['subjek', 'komponenPenilaian'])
    ->get();
```

Tambahkan `use App\Domains\Akademik\Enums\JenisAsesmen;` di import file ini. Baris ini adalah SATU-SATUNYA titik query `Asesmen` di seluruh method `hitungRekapKelas()` (dikonfirmasi saat Prioritas #2) — filter di sini otomatis berlaku ke semua downstream (`$subjekList`, `$asesmenByKey`, `$allNilai`, `$totalNarrativeBySubjek`, `rekapNilai`, `classAvg`, `highestScore`). Tidak ada perubahan lain di file ini.

## 7. Non-Goals (eksplisit di luar scope)

- Tidak ada halaman rekap/ringkasan/analytics baru untuk Diagnostik/Formatif — reuse `AsesmenController::show()` yang sudah ada.
- Tidak ada perubahan skema database (`assessment_type`, `NilaiSiswa` sudah cukup).
- Tidak ada constraint silang `jenis` × `assessment_type`.
- Tidak ada jalur Asesmen tanpa `komponen_id`/tanpa subjek.
- Tidak menyentuh `RaporPdfDataBuilder`/template PDF rapor — perubahan filter di §6 sudah cukup krn `RaporPdfDataBuilder` konsumsi `hitungRekapKelas()` yang sama (dikonfirmasi Prioritas #2).
- Tidak menyentuh alur guru lain (`updateNilai`, `SimpanNilaiSiswaAction`) — generik, tidak bergantung `jenis`.

## 8. Testing (acceptance criteria wajib)

**Unit test `JenisAsesmen`** (`tests/Unit/Enums/JenisAsesmenTest.php`, retrofit):
1. `cases()` tetap 6 case (test existing, tidak berubah).
2. `masukRapor()` mengembalikan TEPAT 3 case Sumatif, urutan sama seperti sekarang (ganti nama test dari `'exposes only the 3 sumatif cases as v1-supported'` jadi mencerminkan `masukRapor()`).
3. `label()` test existing tidak berubah.
4. Test BARU: pastikan `v1Didukung()` TIDAK ADA lagi (mis. `expect(method_exists(JenisAsesmen::class, 'v1Didukung'))->toBeFalse();`) — bukti retire benar-benar total, bukan cuma tidak dipanggil.

**Feature test guru bisa membuat Diagnostik/Formatif** (`tests/Feature/Guru/AsesmenControllerTest.php`, retrofit test `'rejects a jenis outside the v1-supported sumatif options'`):
- Ganti jadi 3 test TERPISAH (bukan 1 representative case) — Diagnostik Kognitif, Diagnostik Non-Kognitif, Formatif — masing-masing membuktikan `store()` BERHASIL (`assertRedirect`, Asesmen tersimpan di DB), bukan lagi ditolak.

**Feature test filter rapor** (`tests/Feature/Akademik/RaporCalculationJenisAsesmenTest.php`, BARU):
1. Kelas dengan HANYA Asesmen Diagnostik Kognitif (nilai terisi) → `rekapNilai` kosong utk subjek itu, `classAvg`/`highestScore` `null` — Diagnostik tidak pernah masuk rekap sama sekali.
2. Sama utk Diagnostik Non-Kognitif secara terpisah.
3. Sama utk Formatif secara terpisah.
4. **Test regresi paling kuat** — SATU siswa, SATU subjek, kombinasi:
   - Asesmen Sumatif dgn nilai 88.
   - Asesmen Formatif dgn nilai 100 (subjek+siswa+semester SAMA).
   - Asesmen Diagnostik Kognitif dgn nilai 100 (subjek+siswa+semester SAMA).

   → `rekapNilai` utk siswa+subjek itu HARUS tetap `88` (bukan rata-rata campuran ketiganya, bukan 100) — membuktikan nilai non-rapor benar-benar tidak ikut memengaruhi agregasi, bukan cuma "tidak muncul di daftar terpisah".

## 9. Ringkasan Alur

```text
JenisAsesmen
    │
    ├── cases()          → 6 jenis, SEMUA boleh dibuat guru (form + validasi)
    │
    └── masukRapor()     → HANYA 3 Sumatif, sumber rapor
                              │
                              ▼
                    RaporCalculationService::hitungRekapKelas()
                    (whereIn('jenis', masukRapor()) -- SATU-SATUNYA filter jenis)

Guru buat Asesmen (jenis apa pun dari 6)
    │
    ├── pilih ≥1 Tujuan Pembelajaran (komponen_id, WAJIB semua jenis)
    │
    ▼
Input nilai di matrix existing (assessment_type bebas: Numeric/Predicate/Narrative)
    │
    ▼
Buka kembali Asesmen (AsesmenController::show(), TIDAK BERUBAH)
    │
    ├── Diagnostik/Formatif → guru baca matrix utk penyesuaian ajar, TIDAK masuk rapor
    │
    └── Sumatif → masuk RaporCalculationService, muncul di Rekap Rapor
```
