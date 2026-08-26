# Spec: Fondasi Akademik Multi-Jenjang — Sprint 4 (Academic Profile Service)

**Status:** Draft untuk review — belum masuk plan eksekusi.
**Branch:** `akademik-v2`
**Bergantung pada:** Tidak bergantung teknis pada Sprint 1-3. `learningMode` reuse `ModePembelajaran` (existing, sudah ada sebelum Sprint 1). Diurutkan setelah Sprint 3 sesuai roadmap, bukan dependency nyata.

## Latar Belakang

Outline awal (spec fondasi) mengusulkan `AcademicProfile::fromBentukPendidikan()` dengan 4 field: `learningMode`, `defaultAssessmentType`, `subjectRequired`, `reportTemplate`. Setelah dicek terhadap kode nyata dan hasil diskusi, scope ini dipangkas jadi 2 field — 2 field lain di-drop dengan alasan eksplisit (bukan terlewat):

- **`defaultAssessmentType` di-drop**: sudah tergantikan oleh defaulting Sprint 2 yang berbasis `subjekType` (`elemen_cp`→narrative, `mata_pelajaran`→numeric) di `CreateKomponenPenilaianAction` — ini lebih presisi daripada default per-`bentuk_pendidikan`, karena satu lembaga PAUD tetap bisa punya komponen `mata_pelajaran` (mis. baca-tulis dasar) yang wajar numeric. Menambah `defaultAssessmentType` di sini akan jadi sumber default kedua yang bersaing/membingungkan dengan yang sudah ada.
- **`subjectRequired` di-drop**: tidak ada satu pun logic existing yang membutuhkannya sekarang — beda kasus dari `defaultAssessmentType` (yang "sudah usang"), ini murni "belum pernah dipakai". Menambah field spekulatif tanpa consumer melanggar YAGNI yang sudah jadi prinsip pegangan project ini.

## Keputusan Desain

1. **`AcademicProfile` adalah platform default/preset, BUKAN business rule atau tenant policy.** Kontrak ini WAJIB ditulis eksplisit di docblock class:
   > "AcademicProfile adalah immutable value object yang menyediakan platform defaults untuk pre-fill UX. Ia bukan sumber kebenaran konfigurasi tenant dan tidak boleh dipakai untuk menimpa nilai yang sudah dipilih/disimpan admin."

   Ini BEDA prinsip dengan `FaseDefaultMapping` (Sprint 3, config-driven) karena konsekuensi kesalahannya berbeda kelas:
   | | `FaseDefaultMapping` | `AcademicProfile` |
   |---|---|---|
   | Sifat | Kebijakan/konfigurasi kurikulum | Preset karakteristik jenjang |
   | Hasil disimpan? | `kelas.fase_id` (snapshot permanen) | Tidak disimpan — dipakai sesaat sbg default |
   | Salah default berakibat | Assignment kurikulum keliru yang ter-snapshot | Pre-fill keliru yang masih bisa diubah user sebelum jadi state bisnis |
   | Perlu admin config? | Ya | Belum perlu |
   | Implementasi | Data-driven (tabel + resolver) | Statis (`match()` murni) |

   Rule praktis: **kalau nilai hanya membantu memilih default dan masih bisa diubah user sebelum jadi state bisnis, static derivation sah. Kalau nilai jadi konfigurasi tersimpan/mengikat/sulit dikoreksi, harus config/data.** `learningMode` dan `reportTemplate` Sprint 4 masuk kategori pertama.

2. **Tanpa tabel/migration baru** — murni value object + factory statis, mengikuti pola `ModePembelajaran::fromBentukPendidikan()` yang sudah ada dan terbukti benar.

3. **`learningMode` reuse `ModePembelajaran` existing, tidak menduplikasi mapping.** `AcademicProfile::fromBentukPendidikan()` memanggil `ModePembelajaran::fromBentukPendidikan()` di dalamnya — TIDAK menulis ulang logic `SesiMapel`/`Tematik` sendiri. Kalau `ModePembelajaran` berubah nanti, `AcademicProfile` otomatis ikut benar tanpa perlu disentuh.

4. **`reportTemplate` adalah key abstrak baru (`'paud'`/`'sd'`/`'smp-sma'`/`'smk'`), BUKAN path Blade.** `RaporPdfDataBuilder::templateUntukJenjang()` (existing, mengembalikan path Blade langsung seperti `'pdf.rapor.sd'`) TIDAK disentuh/di-refactor di Sprint 4 — kedua-duanya hidup berdampingan untuk sementara. Konsolidasi (`RaporPdfDataBuilder` di-refactor jadi `DikdasReportBuilder implements ReportBuilder`, dipilih `ReportEngine` berdasarkan `AcademicProfile::reportTemplate`) eksplisit jadi pekerjaan Sprint 5, bukan Sprint 4.

5. **SLB → `'sd'` eksplisit (bukan lewat default diam-diam), sbg compatibility behavior yang disengaja.** `RaporPdfDataBuilder::templateUntukJenjang()` versi production SAAT INI tidak punya cabang eksplisit untuk SLB — SLB jatuh ke branch default yang sama dengan SD (`'pdf.rapor.sd'`). `AcademicProfile.reportTemplate` MENIRU perilaku nyata ini secara eksplisit (baris `'SLB' => 'sd'` sendiri, bukan tercampur ke `default`), TAPI ini bukan klaim "kebutuhan pelaporan SLB identik dengan SD" — murni mempertahankan compatibility dengan behavior yang sudah berjalan. Re-evaluasi/pemisahan template SLB (kalau memang dibutuhkan) eksplisit didorong ke desain Sprint 5.

6. **`bentuk_pendidikan` yang tidak dikenali WAJIB throw, bukan silent fallback ke `'sd'`.** Karena `reportTemplate` adalah abstraksi yang akan dikonsumsi `ReportEngine` (Sprint 5) untuk memilih builder, nilai `bentuk_pendidikan` di luar whitelist 9 nilai yang sudah dikenal (`KB`,`TPA`,`SPS`,`TK`,`SD`,`SMP`,`SMA`,`SMK`,`SLB`) HARUS melempar `InvalidArgumentException` — silent fallback ke `'sd'` akan menyembunyikan data/configuration error (mis. typo `bentuk_pendidikan` baru yang belum pernah diantisipasi) sbg pemilihan template yang tampak normal tapi sebenarnya salah.

7. **Tidak ada refactor consumer existing di Sprint 4.** `GenerateSesiHarianAction` (dan tempat lain yang query `bentuk_pendidikan` manual) TETAP memakai `ModePembelajaran::fromBentukPendidikan()` langsung, TIDAK diganti ke `AcademicProfile::fromBentukPendidikan()->learningMode`. Alasan: menjaga scope & blast radius kecil; abstraksi baru divalidasi dulu lewat unit/contract test, bukan lewat refactor paksa "biar kelihatan terpakai". Konsolidasi consumer (kalau memang dibutuhkan nanti) jadi task/sprint terpisah eksplisit dengan daftar consumer & acceptance criteria sendiri — BUKAN dikerjakan diam-diam demi validasi keberadaan Sprint 4.

## §1. Implementasi

`app/Domains/Akademik/Support/AcademicProfile.php` (lokasi sama dengan `SubjekPenilaianKey.php` — Sprint 1 — pola "stateless domain support class"):

```php
<?php

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

## §2. Test Matrix (Acceptance Criteria WAJIB)

Table-driven test membuktikan seluruh whitelist 9 nilai `bentuk_pendidikan` resmi (`app/Http/Controllers/Admin/LembagaController.php:164` — `'in:KB,TPA,SPS,TK,SD,SMP,SMA,SMK,SLB'`):

| `bentuk_pendidikan` | `learningMode` (harus match `ModePembelajaran::fromBentukPendidikan()`) | `reportTemplate` |
|---|---|---|
| KB | Tematik | paud |
| TPA | Tematik | paud |
| SPS | Tematik | paud |
| TK | Tematik | paud |
| SD | Tematik | sd |
| SMP | SesiMapel | smp-sma |
| SMA | SesiMapel | smp-sma |
| SMK | SesiMapel | smk |
| SLB | Tematik | sd |

Ditambah:
- **Konsistensi dgn `ModePembelajaran`**: untuk seluruh 9 nilai di atas, `AcademicProfile::fromBentukPendidikan($bp)->learningMode` HARUS identik dgn `ModePembelajaran::fromBentukPendidikan($bp)` dipanggil langsung — test eksplisit membandingkan keduanya (bukan cuma hardcode expected value yang bisa diam-diam divergen dari sumber aslinya kalau `ModePembelajaran` berubah nanti).
- **Unknown `bentuk_pendidikan` throws**: `AcademicProfile::fromBentukPendidikan('XYZ')` (atau string sembarang di luar whitelist) HARUS melempar `InvalidArgumentException`, bukan mengembalikan default apa pun.
- **Immutability**: property `learningMode`/`reportTemplate` bertipe `readonly` — tidak ada test khusus untuk ini (dijamin bahasa PHP sendiri), tapi constructor `private` WAJIB diverifikasi mencegah instansiasi langsung dari luar (`new AcademicProfile(...)` di luar class harus gagal saat compile/lint, bukan cuma diasumsikan).

## Non-Goals Sprint 4 (eksplisit)

- Tidak ada field `defaultAssessmentType`/`subjectRequired` — di-drop dgn alasan di §Latar Belakang, bukan lupa.
- Tidak ada refactor consumer existing (`GenerateSesiHarianAction` dkk tetap pakai `ModePembelajaran` langsung).
- Tidak menyentuh/refactor `RaporPdfDataBuilder::templateUntukJenjang()` — tetap hidup berdampingan, konsolidasi eksplisit Sprint 5.
- Tidak ada `ReportEngine`/`ReportBuilder` interface — itu Sprint 5.
- Tidak ada tabel/migration/konfigurasi apa pun — murni kode statis.

## Self-Review

- Semua keputusan hasil diskusi masuk eksplisit: (1) prinsip "preset bukan policy" §Keputusan Desain poin 1 dgn tabel perbandingan vs `FaseDefaultMapping`, (2) `defaultAssessmentType`/`subjectRequired` di-drop dgn alasan masing-masing (bukan alasan yang sama disamaratakan) §Latar Belakang, (3) `reportTemplate` sbg key abstrak terpisah dari `templateUntukJenjang()` §Keputusan Desain poin 4, (4) SLB→'sd' eksplisit dgn justifikasi compatibility, bukan klaim domain §Keputusan Desain poin 5 + komentar kode di §1, (5) unknown `bentuk_pendidikan` throw bukan silent fallback §Keputusan Desain poin 6, (6) tidak ada refactor consumer §Keputusan Desain poin 7.
- Placeholder scan: tidak ada. Whitelist 9 nilai `bentuk_pendidikan` diverifikasi langsung dari `Admin\LembagaController.php:164` (sumber kebenaran validasi), bukan ditebak.
- Scope check: fokus tunggal pada 1 value object + factory + test. Tidak melebar ke Report Engine (Sprint 5) atau refactor consumer.
- Konsistensi tipe: `AcademicProfile::fromBentukPendidikan(string $bentukPendidikan): self` dgn 2 property readonly (`ModePembelajaran $learningMode`, `string $reportTemplate`) konsisten dipakai di §1 (implementasi) dan §2 (test).
