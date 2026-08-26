# Spec: Fondasi Akademik Multi-Jenjang — Sprint 5 (Konsolidasi Derivasi Kategori Jenjang Rapor)

**Status:** Ready for Plan — disetujui user, siap masuk plan eksekusi.
**Branch:** `akademik-v2`
**Bergantung pada:** Sprint 4 (SELESAI — `AcademicProfile::fromBentukPendidikan()` sudah ada, live, full-test-covered).

## Latar Belakang

Outline awal (spec fondasi) menamai sprint ini "Report Engine Abstraction" dengan premis: *"Hanya implementasi `DikdasReportBuilder` yang benar-benar dibangun sekarang — `PaudReportBuilder` dkk BELUM diimplementasikan (tidak ada pelanggan PAUD nyata)"*.

**Premis ini TIDAK sesuai kenyataan saat ini.** Audit terhadap `resources/views/pdf/rapor/` menunjukkan **4 template Blade sudah ada dan production** (`paud.blade.php`, `sd.blade.php`, `smp-sma.blade.php`, `smk.blade.php`), semuanya sudah dikonsumsi lewat `RaporPdfDataBuilder::templateUntukJenjang()` yang sudah menangani ke-4 kategori. `RaporPdfDataBuilder::build()` sendiri adalah **satu builder data generik tunggal** (tidak ada percabangan data per-jenjang di dalamnya, kecuali helper kecil `isTingkatAkhir()`) — datanya dikonsumsi seragam oleh ke-4 template.

Mengikuti outline apa adanya (rename jadi `DikdasReportBuilder`, builder lain `throw NotImplementedException`) akan **merusak fitur PAUD/SMK/SMP-SMA yang sudah berjalan hari ini** — bukan menyederhanakan. Premis "cuma Dikdas yang diimplementasikan" sudah tidak berlaku (mungkin benar 1 bulan sebelum Sprint 1 saat outline ditulis, sebelum ke-4 template dibangun).

**Scope Sprint 5 dipangkas total** menjadi konsolidasi kecil: menghilangkan duplikasi derivasi "kategori jenjang" yang sekarang hidup di 2 tempat berbeda (`RaporPdfDataBuilder::templateUntukJenjang()` dan `AcademicProfile::fromBentukPendidikan()`), bukan membangun arsitektur Report Engine baru.

## Keputusan Desain

1. **`AcademicProfile` jadi satu-satunya sumber derivasi kategori jenjang.** `RaporPdfDataBuilder::templateUntukJenjang()` TIDAK LAGI punya logic `if`/`in_array` sendiri untuk menentukan kategori (`paud`/`sd`/`smp-sma`/`smk`) — ia delegasikan ke `AcademicProfile::fromBentukPendidikan($bentukPendidikan)->reportTemplate`, lalu hanya bertanggung jawab memetakan key abstrak itu ke path Blade (concern yang memang miliknya, karena ia yang tahu di mana file view rapor berada).

2. **TIDAK ADA `ReportBuilder` interface, `DikdasReportBuilder`/`PaudReportBuilder` dkk, atau `ReportEngine` orchestrator.** Semua itu di-drop — premisnya (builder per-jenjang) tidak didukung bukti nyata (tidak ada divergensi cara membangun data antar jenjang, hanya divergensi template Blade yang menampilkannya). Evolutionary path yang sehat: kalau nanti BENAR-BENAR muncul kebutuhan data-building berbeda per jenjang, `ReportBuilder` diperkenalkan berdasarkan kebutuhan nyata & acceptance criteria konkret saat itu — bukan sekarang berdasarkan asumsi spec lama.

3. **`RaporPdfDataBuilder::build()` TIDAK DISENTUH SAMA SEKALI.** Sprint 5 murni mengganti isi `templateUntukJenjang()`, tidak ada perubahan pada method `build()` atau shape data yang dikembalikannya.

4. **Tidak ada DTO/Action/FormRequest baru.** Ini refactor internal derivasi di satu method Service existing — tidak ada boundary HTTP baru, tidak ada input baru yang perlu divalidasi. Menambah class baru (mis. `ReportTemplateResolver`) untuk 4 baris `match()` akan melanggar §23 `laravel-feature-standard` sendiri (anti-pattern layer yang tidak perlu untuk operasi sederhana).

5. **Fail-fast eksplisit di 2 lapis, bukan cuma 1.** Method sekarang punya dua titik kegagalan eksplisit yang saling melengkapi:
   - **Lapis 1** (`AcademicProfile::fromBentukPendidikan()`): `bentuk_pendidikan` di luar 9 whitelist resmi → `InvalidArgumentException`. Ini sudah ada dari Sprint 4, sekarang perilakunya "mengalir" ke `templateUntukJenjang()` karena dipanggil di dalamnya.
   - **Lapis 2** (`match` di `templateUntukJenjang()` sendiri, TAMBAHAN baru): `default => throw new LogicException('Unsupported academic report template key.')` sbg **defense-in-depth** — kalau suatu saat `AcademicProfile.reportTemplate` menambah key baru (mis. Sprint 4 lanjutan menambah kategori ke-5) tapi lupa menambah baris lookup Blade di sini, kegagalannya eksplisit (`LogicException`, bukan `InvalidArgumentException` — beda exception class sengaja dipakai supaya jelas dari log/stack trace mana yang gagal: "input jenjang tidak dikenal" [Lapis 1] vs "kode ini punya bug, lupa handle key valid" [Lapis 2]).
   - Konsekuensi diterima: kedua exception ini sekarang bisa terlempar dari 2 endpoint customer-facing — `Guru\RaporController::cetak()` (`app/Http/Controllers/Guru/RaporController.php:212`) dan `Lembaga\Rapor\PersetujuanController::cetak()` (`app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php:79`), keduanya sudah diverifikasi langsung (nama method persis, bukan asumsi). Diterima secara sadar — konsisten dgn prinsip Sprint 4 (silent fallback menyembunyikan data/configuration error; kasus ini seharusnya mustahil terjadi di data valid karena `bentuk_pendidikan` divalidasi `NOT NULL` + `in:...` saat Lembaga dibuat).

## §1. Implementasi

`app/Domains/Akademik/Services/RaporPdfDataBuilder.php` — HANYA method `templateUntukJenjang()` yang berubah:

```php
use App\Domains\Akademik\Support\AcademicProfile;
use LogicException;

// ...

public function templateUntukJenjang(string $bentukPendidikan): string
{
    return match (AcademicProfile::fromBentukPendidikan($bentukPendidikan)->reportTemplate) {
        'paud' => 'pdf.rapor.paud',
        'sd' => 'pdf.rapor.sd',
        'smp-sma' => 'pdf.rapor.smp-sma',
        'smk' => 'pdf.rapor.smk',
        default => throw new LogicException('Unsupported academic report template key.'),
    };
}
```

Method privat `isTingkatAkhir()` di file yang sama TIDAK diubah (sudah punya whitelist-nya sendiri untuk keperluan berbeda — "tingkat akhir per jenjang", bukan "kategori template" — dan tidak ada indikasi keduanya perlu disatukan sekarang; menyatukan tanpa kebutuhan nyata adalah scope creep).

## §2. Test Matrix (Acceptance Criteria WAJIB)

**Regresi — hasil `templateUntukJenjang()` untuk 9 whitelist HARUS identik dengan behavior lama:**

| `bentuk_pendidikan` | Hasil (harus tetap sama persis dgn sebelum refactor) |
|---|---|
| KB | `pdf.rapor.paud` |
| TPA | `pdf.rapor.paud` |
| SPS | `pdf.rapor.paud` |
| TK | `pdf.rapor.paud` |
| SD | `pdf.rapor.sd` |
| SMP | `pdf.rapor.smp-sma` |
| SMA | `pdf.rapor.smp-sma` |
| SMK | `pdf.rapor.smk` |
| SLB | `pdf.rapor.sd` |

Table-driven test membandingkan hasil baru vs 9 nilai di atas — bukan cuma "tidak error", tapi memastikan behavior lama (termasuk SLB→sd yang sebelumnya silent-default) tetap identik pasca-refactor.

**Fail-fast Lapis 1 (dari `AcademicProfile`, mengalir lewat method ini):**
- `templateUntukJenjang('XYZ')` (atau string di luar whitelist) → `InvalidArgumentException`.

**Fail-fast Lapis 2 (defense-in-depth, baru):**
- Test ini secara desain SULIT ditulis sbg unit test biasa (tidak ada cara alami membuat `AcademicProfile::reportTemplate` mengembalikan key ke-5 tanpa mengubah `AcademicProfile` itu sendiri). Implementer WAJIB mencatat di kode (komentar) bahwa branch `default => throw LogicException` ini murni defense-in-depth yang TIDAK bisa ditest lewat unit test biasa tanpa mocking/reflection berlebihan — dan itu SAH, bukan berarti kode mati (dead code) yang harus dihapus. Kalau implementer menemukan cara elegan mem-verifikasi ini (mis. lewat reflection atau test terpisah yang sengaja memanggil private helper dgn key palsu), boleh ditambahkan, TAPI TIDAK WAJIB — jangan memaksa coverage 100% dgn cara yang janggal.

**Test independen menyusul (harus tetap hijau)**: `AcademicProfileTest.php` (Sprint 4) TIDAK diubah oleh Sprint 5 — dijalankan lagi sbg bagian regresi biasa, bukan ditulis ulang.

## Non-Goals Sprint 5 (eksplisit)

- `ReportBuilder` interface — TIDAK dibangun.
- `ReportEngine` orchestrator — TIDAK dibangun.
- `DikdasReportBuilder`/`PaudReportBuilder`/builder per-jenjang apa pun — TIDAK dibangun.
- Perubahan `RaporPdfDataBuilder::build()` — TIDAK disentuh.
- Perubahan Blade template (`paud.blade.php`, dll) — TIDAK disentuh.
- DTO/Action/FormRequest baru — tidak diperlukan, tidak dibuat.
- Perubahan behavior lain di luar konsekuensi fail-fast yang sudah dijelaskan di §Keputusan Desain poin 5.

## Self-Review

- Semua keputusan hasil diskusi masuk eksplisit: (1) `AcademicProfile` jadi satu sumber derivasi §Keputusan Desain poin 1, (2) drop total `ReportBuilder`/`ReportEngine`/builder-per-jenjang dgn alasan bukti nyata §Latar Belakang + poin 2, (3) `build()` tidak disentuh §poin 3, (4) tidak ada DTO/Action/FormRequest dgn alasan eksplisit merujuk §23 skill §poin 4, (5) fail-fast 2 lapis dgn `LogicException` sbg defense-in-depth tambahan dari catatan review user §poin 5 + §1.
- Placeholder scan: tidak ada. Nama & lokasi persis kedua method consumer (`Guru\RaporController::cetak()`, `Lembaga\Rapor\PersetujuanController::cetak()`) sudah diverifikasi langsung dari kode, bukan ditebak.
- Scope check: fokus tunggal pada 1 method, 1 file. Tidak melebar ke `build()`, Blade, atau abstraksi baru apa pun.
- Konsistensi tipe: `templateUntukJenjang(string $bentukPendidikan): string` — signature TIDAK berubah dari sebelumnya, hanya isi method yang berubah. Konsumen (`Guru\RaporController`, `Lembaga\Rapor\PersetujuanController`) TIDAK perlu diubah sama sekali.
