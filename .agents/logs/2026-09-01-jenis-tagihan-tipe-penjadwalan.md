# Handoff Log: Jenis Tagihan - 5 Tipe Penjadwalan (Harian, Mingguan, Bulanan, Tahunan, Sekali)

- **Tanggal**: 2026-09-01
- **Branch**: `keuangan-v2`
- **Base Commit**: `12b05cf3`
- **Head Commit**: `911f58f5`
- **Spec**: `.agents/specs/2026-09-01-jenis-tagihan-tipe-penjadwalan.md`
- **Plan**: `.agents/plans/2026-09-01-jenis-tagihan-tipe-penjadwalan.md`
- **Test Suite Status**: **2601 passed (7146 assertions), 0 failed** (Baseline: 2557 passed, +44 net tests)

---

## 1. Apa yang Dikerjakan

Implementasi lengkap sistem penjadwalan tagihan 5 tipe (`harian`, `mingguan`, `bulanan`, `tahunan`, `sekali`) pada modul keuangan Pintera, memisahkan sumbu **Mode** (`manual` / `otomatis`) dan sumbu **Tipe** (`harian` / `mingguan` / `bulanan` / `tahunan` / `sekali`), dengan 9 task TDD atomik yang dieksekusi secara berurutan:

### Task 1: Fix label `hari_jatuh_tempo` (Commit `4734b233`)
- **Files**: `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`, `tests/Feature/Admin/JenisTagihanFormPageTest.php`
- **Perubahan**: Memperjelas label `hari_jatuh_tempo` pada form Blade bahwa nilai tersebut adalah tanggal absolut dalam bulan (bukan jarak/offset hari).
- **Hasil**: 6/6 tests passing.

### Task 2: Migration Kolom Penjadwalan & Widen `billing_period` (Commit `3d4d610e`)
- **Files**: `database/migrations/2026_09_01_000003_add_tipe_penjadwalan_to_jenis_tagihan_table.php`, `tests/Feature/Keuangan/JenisTagihanTipeMigrationTest.php`
- **Perubahan**:
  - Menambahkan kolom `tipe` (`enum: harian, mingguan, bulanan, tahunan, sekali`), `hari_generate` (`tinyint unsigned`), `bulan_generate` (`tinyint unsigned`), `offset_hari_jatuh_tempo` (`smallint unsigned`).
  - Menghapus kolom legacy `last_generated_period`.
  - Memperlebar kolom `tagihan.billing_period` dari `varchar(7)` ke `varchar(10)` untuk menampung format ISO-week (`YYYY-Www`) dan tanggal harian (`YYYY-MM-DD`).
  - Backfill data eksisting: `mode = 'otomatis'` -> `tipe = 'bulanan'`; `mode = 'manual'` -> `tipe = 'sekali'`.
  - Mengubah kolom `tipe` menjadi `NOT NULL`.
  - Menambahkan database CHECK constraint: `CHECK (NOT (mode = 'otomatis' AND tipe = 'sekali'))`.
- **Hasil**: 5/5 tests passing.

### Task 3: Enums `TipeTagihan` & `HariDalamMinggu` serta Model Update (Commit `5902541d`)
- **Files**: `app/Domains/Keuangan/Enums/TipeTagihan.php`, `app/Domains/Keuangan/Enums/HariDalamMinggu.php`, `app/Domains/Keuangan/Models/JenisTagihan.php`, `tests/Unit/Keuangan/TipeTagihanTest.php`, `tests/Feature/Keuangan/JenisTagihanTipeDefaultTest.php`
- **Perubahan**:
  - Dibuat PHP backed enums dengan method `label()` yang deskriptif.
  - Model `JenisTagihan` dikonfigurasi dengan default `$attributes['tipe'] = 'bulanan'`, fillable diperbarui (ditambah kolom baru, dihapus `last_generated_period`), dan cast `'tipe' => TipeTagihan::class`.
- **Hasil**: 3/3 new tests passing, 11/11 regression tests passing.

### Task 4: Form Validation Rules per Kombinasi Mode & Tipe (Commit `326ec161`)
- **Files**: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`, `tests/Feature/Admin/JenisTagihanModeTipeValidationTest.php`, `tests/Feature/Admin/JenisTagihanFormTest.php`, `tests/Feature/Admin/JenisTagihanTest.php`
- **Perubahan**:
  - `hasBillingPayload()` diperbarui untuk melindungi kategori PPDB (`pendaftaran`, `daftar_ulang`) dari payload `tipe` dan kolom pendukung.
  - `baseRules()` menerapkan validasi kondisional: `tipe` wajib untuk non-PPDB, `hari_generate` wajib untuk `otomatis + mingguan`, `bulan_generate` & `tanggal_generate` untuk `tahunan`, `hari_jatuh_tempo` untuk bulanan/tahunan, `offset_hari_jatuh_tempo` untuk harian/mingguan.
  - Error respons eksplisit 422 jika menerima kombinasi kontradiktif `mode=otomatis + tipe=sekali`.
- **Hasil**: 45/45 tests passing.

### Task 5: Null-out Stale Support Fields pada Update `tipe` (Commit `495dea40`)
- **Files**: `app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php`, `tests/Feature/Keuangan/JenisTagihanTipeNullifyOnChangeTest.php`
- **Perubahan**:
  - Menambahkan method `nullifyFieldsNotOwnedBy(TipeTagihan $tipe)` pada `UpdateJenisTagihanAction`.
  - Kolom pendukung yang dimiliki tipe lama otomatis di-`null`-kan di database saat `tipe` jenis tagihan diubah.
- **Hasil**: 2/2 unit tests passing, 27/27 regression tests passing.

### Task 6: `resolveDueDate()` 5 Explicit Branches per Tipe (Commit `5e4cec06`)
- **Files**: `app/Domains/Keuangan/Services/TagihanBillingGenerator.php`, `tests/Feature/Keuangan/TagihanBillingGeneratorResolveDueDateTest.php`
- **Perubahan**:
  - Memperbarui signature `resolveDueDate(JenisTagihan $jenisTagihan, ?string $billingPeriod, Carbon $tanggalGenerateAktual): ?string`.
  - 5 cabang eksplisit: `Sekali` -> `null`; `Harian` & `Mingguan` -> `generate + offset_hari_jatuh_tempo`; `Bulanan` -> hari ke-$n$ di bulan billing; `Tahunan` -> hari ke-$n$ di `bulan_generate`.
- **Hasil**: 5/5 new tests passing, 9/9 regression tests passing.

### Task 7: `resolveBillingPeriod()` Generalization & ISO-Week (Commit `75c54df3`)
- **Files**: `app/Domains/Keuangan/Services/TagihanBillingGenerator.php`, `tests/Feature/Keuangan/TagihanBillingGeneratorResolveBillingPeriodTest.php`
- **Perubahan**:
  - Menambahkan method `resolveBillingPeriod(JenisTagihan $jenisTagihan, Carbon $tanggalGenerateAktual): ?string`.
  - Format per tipe: `Manual` -> `null`; `Harian` -> `Y-m-d`; `Mingguan` -> `o-\WW` (ISO week format dengan ISO week-numbering year); `Bulanan` -> `Y-m`; `Tahunan` -> `Y`.
  - Terverifikasi lolos pada 2 boundary case kritis: `2027-01-01` -> `2026-W53` dan `2025-12-29` -> `2026-W01`.
- **Hasil**: 7/7 new tests passing, 13/13 regression tests passing.

### Task 8: Rewrite Cron `GenerateTagihanHarian` Query (Commit `58ee5d77`)
- **Files**: `app/Console/Commands/GenerateTagihanHarian.php`, `tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php`, `tests/Feature/Keuangan/BillTypeActivatedEventTest.php`
- **Perubahan**:
  - Query kandidat cron diperbarui untuk memeriksa tanggal hari ini terhadap jadwal masing-masing tipe: `Harian` (setiap hari dalam rentang aktif), `Mingguan` (`hari_generate = dayOfWeekIso`), `Bulanan` (`tanggal_generate = day`), `Tahunan` (`bulan_generate = month AND tanggal_generate = day`).
  - Terverifikasi aman dari duplikasi tagihan (idempotent) saat cron berjalan berulang kali di hari yang sama.
- **Hasil**: 8/8 command tests passing, full Keuangan domain suite passing.

### Task 9: Dynamic Form UI & Browser Verification (Commit `55a602ff`)
- **Files**: `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`, `resources/js/jenis-tagihan-form.js`, `tests/Feature/Admin/JenisTagihanFormPageTipeUiTest.php`
- **Perubahan**:
  - Kartu "Mode Penjadwalan & Default" dinamis di Blade dengan Alpine.js.
  - Menampilkan 5 opsi Tipe, menyembunyikan opsi `sekali` saat mode otomatis, dan menampilkan field input konfigurasi yang sesuai dengan tipe yang dipilih.
  - Kompilasi build frontend dengan Vite (`npm run build`).
  - Verifikasi otomatis via Playwright / `browser_subagent` dengan rekaman browser WebP artifact.
- **Hasil**: 14/14 form tests passing.

### Test Suite Alignment (Commit `911f58f5`)
- **Files**: `tests/Feature/Admin/JenisTagihanFinalReviewFixesTest.php`
- **Perubahan**: Menyesuaikan payload request non-PPDB di suite test lama agar menyertakan `'tipe' => 'bulanan'` sesuai aturan validasi baru.

---

## 2. Keputusan Penting yang Diambil

1. **Format ISO-Week Menggunakan `'o-\WW'`, Bukan `'Y-\WW'`**:
   - Format `o` menghasilkan ISO-8601 week-numbering year. Ini penting karena tanggal di awal/akhir tahun kalender bisa berada di ISO week tahun sebelumnya/berikutnya (misal `2027-01-01` adalah `2026-W53`). Menggunakan `Y` akan menyebabkan duplikasi tagihan di batas tahun.
2. **Ortogonalitas Mode vs Tipe**:
   - `mode` hanya menjawab *siapa yang memicu tagihan* (`manual` oleh staf, `otomatis` oleh cron harian).
   - `tipe` menjawab *frekuensi dan format periode tagihan* (`harian`, `mingguan`, `bulanan`, `tahunan`, `sekali`).
   - Kombinasi `manual + bulanan` valid (misalnya bendahara menagih SPP secara manual tiap bulan).
   - Kombinasi `otomatis + sekali` tidak masuk akal (kontradiktif) sehingga ditolak di level validasi (422) dan database CHECK constraint.
3. **Pembersihan Kolom Konfigurasi Stale di Action**:
   - Ketika bendahara mengedit jenis tagihan dari `Mingguan` ke `Bulanan`, field `hari_generate` dan `offset_hari_jatuh_tempo` otomatis di-set ke `NULL` oleh `UpdateJenisTagihanAction` agar database tetap bersih dan tidak menyimpan konfigurasi yatim.
4. **Isolasi Penuh untuk Kategori PPDB**:
   - Kategori PPDB (`pendaftaran`, `daftar_ulang`) diproses via alur penerimaan murid, bukan billing engine berulang. `hasBillingPayload()` dan validasi form secara ketat mencegah input tipe/billing masuk ke jenis tagihan PPDB.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

- **Git Branch Status**: Pekerjaan berada di branch `keuangan-v2`. Semua perubahan telah di-commit secara rapi (total 10 commit).
- **Production Migration Note**: Saat deploy ke production/staging, pastikan menjalankan `php artisan migrate` untuk menerapkan migration `2026_09_01_000003_add_tipe_penjadwalan_to_jenis_tagihan_table.php`. Migration ini sudah memiliki safe backfill dan idempotency.
- **Frontend Asset Build**: Perubahan Javascript Alpine.js sudah dibuild ke `public/build/` via Vite (`npm.cmd run build`).

---

## 4. Riwayat Commit

```
911f58f5 test(keuangan): pass tipe=bulanan in non-ppdb payloads in JenisTagihanFinalReviewFixesTest
55a602ff feat(keuangan): dynamic UI form for all 5 Tipe options and their support fields
58ee5d77 feat(keuangan): rewrite GenerateTagihanHarian candidate matching query per Tipe
75c54df3 feat(keuangan): generalize billing_period format per Tipe, verified at ISO-week year boundaries
5e4cec06 feat(keuangan): branch resolveDueDate() explicitly per Tipe (Harian/Mingguan/Bulanan/Tahunan/Sekali)
495dea40 feat(keuangan): null out stale tipe support fields on JenisTagihan update
326ec161 feat(keuangan): validate tipe and its support fields per mode+tipe combination
5902541d feat(keuangan): add TipeTagihan/HariDalamMinggu enums and default tipe=bulanan on JenisTagihan
3d4d610e feat(keuangan): add tipe penjadwalan columns, drop last_generated_period, widen billing_period
4734b233 fix(keuangan): correct hari_jatuh_tempo label to describe absolute day-of-month, not offset
```
