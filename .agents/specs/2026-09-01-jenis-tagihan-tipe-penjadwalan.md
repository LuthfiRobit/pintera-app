# Spec: Jenis Tagihan — Tipe Penjadwalan (Harian/Mingguan/Bulanan/Tahunan/Sekali)

- **Branch**: `keuangan-v2`
- **Tanggal**: 1 September 2026
- **Konteks**: modul Jenis Tagihan saat ini cuma punya `mode` (manual/otomatis) tanpa konsep frekuensi eksplisit. Mekanisme generate otomatis yang ada (`GenerateTagihanHarian`, cron harian) cuma paham satu siklus: bulanan (cocokkan `tanggal_generate` dengan tanggal hari ini). Kategori "Tahunan" yang sudah ada di kolom `kategori` sekarang cuma label akuntansi — sama sekali tidak mengunci perilaku "sekali setahun" secara mekanis (kalau dikonfigurasi otomatis, akan generate TIAP BULAN, bukan setahun sekali). Spec ini menambah field **Tipe** yang benar-benar mengatur jadwal generate, terpisah dari kolom `kategori` yang tetap dipertahankan sebagai label pelaporan.

---

## 1. Ringkasan

Tambah field baru **Tipe** (Harian, Mingguan, Bulanan, Tahunan, Sekali) pada `jenis_tagihan`, wajib diisi di kedua Mode (Manual maupun Otomatis). Saat Mode=Otomatis, Tipe menentukan field pendukung apa yang wajib diisi dan bagaimana cron generate bekerja. Saat Mode=Manual, Tipe murni label/metadata untuk konsistensi laporan dan kemudahan migrasi mode nanti.

Sebagai prasyarat, satu bug lama di `resolveDueDate()` (semantik `hari_jatuh_tempo` yang ambigu antara "offset hari" dan "tanggal absolut") diperbaiki lebih dulu, sebagai langkah terpisah sebelum Tipe baru masuk.

## 2. Non-Goals (eksplisit di luar scope)

1. **Kategori PPDB (Pendaftaran/Daftar Ulang) tidak tersentuh sama sekali.** Field Tipe/Mode baru TIDAK berlaku untuk kategori ini — alurnya tetap nominal-per-jalur seperti sekarang, mengikuti aturan blok yang sudah ada di kode (`hasBillingPayload()` guard).
2. **Pro-rata otomatis untuk siswa yang mendaftar di tengah periode** (misal potongan proporsional untuk tagihan Tahunan kalau siswa masuk bulan ke-7) — bukan bagian scope ini. Tipe cuma mengatur KAPAN tagihan dibuat, bukan menghitung ulang nominalnya secara proporsional.
3. **Migrasi/rename istilah PPDB → SPMB** — eksplisit di-skip per instruksi user, urusan terpisah.
4. **Mengubah kolom `kategori`** (nilai, makna, atau perannya sebagai label akuntansi) — sudah diputuskan tetap sama seperti sekarang di sesi diskusi sebelumnya.

## 3. Kolom `kategori` tetap dipertahankan

`kategori` (enum: `pendaftaran`, `daftar_ulang`, `lainnya`, `spp`, `tahunan`, `kegiatan`, `custom`) TIDAK berubah dan TIDAK digantikan oleh Tipe. Keduanya dua sumbu berbeda:

| | `kategori` (existing) | `tipe` (baru) |
|---|---|---|
| Jawab pertanyaan | "Tagihan ini untuk apa?" | "Tagihan ini seberapa sering dibuat?" |
| Dipakai untuk | Label kwitansi, laporan Buku Kas/BOS, aturan PPDB (`isPpdb()`) | Mesin generate otomatis |

**Kategori PPDB (`pendaftaran`, `daftar_ulang`) dikecualikan total dari fitur ini** — field `tipe` dan semua field pendukungnya (lihat §4) TIDAK ditampilkan/divalidasi untuk kedua kategori ini, mengikuti pola blok yang sudah ada (`JenisTagihanController::hasBillingPayload()` guard, baris 129-133 & 175-179 saat ini).

## 4. Field baru: `tipe` dan field pendukung per Tipe

### 4.1 Enum `TipeTagihan`

Baru: `App\Domains\Keuangan\Enums\TipeTagihan` (string-backed, pola sama seperti `KategoriTagihan`):

```php
<?php

namespace App\Domains\Keuangan\Enums;

enum TipeTagihan: string
{
    case Harian = 'harian';
    case Mingguan = 'mingguan';
    case Bulanan = 'bulanan';
    case Tahunan = 'tahunan';
    case Sekali = 'sekali';

    public function label(): string
    {
        return match ($this) {
            self::Harian => 'Harian',
            self::Mingguan => 'Mingguan',
            self::Bulanan => 'Bulanan',
            self::Tahunan => 'Tahunan',
            self::Sekali => 'Sekali',
        };
    }
}
```

### 4.2 Field pendukung baru: `hari_generate` (untuk Tipe Mingguan)

Enum baru `App\Domains\Keuangan\Enums\HariDalamMinggu` (1 = Senin ... 7 = Minggu, mengikuti `Carbon::dayOfWeekIso` yang sudah dipakai project ini untuk perhitungan hari):

```php
enum HariDalamMinggu: int
{
    case Senin = 1;
    case Selasa = 2;
    case Rabu = 3;
    case Kamis = 4;
    case Jumat = 5;
    case Sabtu = 6;
    case Minggu = 7;
}
```

Kolom `jenis_tagihan.hari_generate` (tinyint unsigned, nullable) — **wajib diisi kalau Mode=Otomatis + Tipe=Mingguan**, tidak dipakai untuk Tipe lain.

### 4.3 Field pendukung baru: `bulan_generate` (untuk Tipe Tahunan)

Kolom `jenis_tagihan.bulan_generate` (tinyint unsigned 1-12, nullable) — **wajib diisi kalau Mode=Otomatis + Tipe=Tahunan**. Default value yang ditawarkan di form (bukan default database) = `MONTH(tanggal_mulai)`, tapi admin bisa override manual ke bulan lain (misal kalau sekolah mau tagihan tahunan selalu di kalender tetap, bukan relatif ke tanggal mulai).

### 4.4 Field pendukung baru: `offset_hari_jatuh_tempo` (untuk Tipe Harian & Mingguan)

Kolom `jenis_tagihan.offset_hari_jatuh_tempo` (smallint unsigned, nullable) — **wajib diisi kalau Mode=Otomatis + (Tipe=Harian atau Tipe=Mingguan)**. Maknanya: jatuh tempo = tanggal generate + N hari. Field ini SENGAJA dipisah dari `hari_jatuh_tempo` (existing) karena semantiknya beda total (offset vs tanggal absolut — lihat §7).

### 4.5 Field existing yang direuse (tidak berubah nama/tipe kolom)

- `tanggal_generate` (tinyint 1-31) — dipakai untuk Tipe **Bulanan** (seperti sekarang) DAN Tipe **Tahunan** (tanggal di dalam `bulan_generate`). **Wajib diisi eksplisit untuk kedua Tipe ini kalau Mode=Otomatis** — TIDAK default diam-diam ke tanggal 1.
- `hari_jatuh_tempo` (tinyint 1-31) — dipakai untuk Tipe **Bulanan** dan **Tahunan** saja (tanggal absolut di bulan yang relevan). TIDAK dipakai untuk Harian/Mingguan (pakai `offset_hari_jatuh_tempo` sebagai gantinya).
- `tanggal_mulai`/`tanggal_selesai` — tetap dipakai di semua Tipe sebagai batas masa berlaku (tidak berubah).

### 4.6 Ringkasan field wajib per kombinasi (Mode=Otomatis saja — Mode=Manual tidak butuh field pendukung apapun selain Tipe sendiri)

| Tipe | Field wajib tambahan |
|---|---|
| Harian | `offset_hari_jatuh_tempo` |
| Mingguan | `hari_generate`, `offset_hari_jatuh_tempo` |
| Bulanan | `tanggal_generate`, `hari_jatuh_tempo` (tidak berubah dari sekarang) |
| Tahunan | `bulan_generate`, `tanggal_generate`, `hari_jatuh_tempo` |
| Sekali | — (Mode=Otomatis + Tipe=Sekali DITOLAK validasi, lihat §6) |

## 5. DDL Migration

**Ambiguitas yang diperbaiki saat self-review**: default seragam `'bulanan'` untuk SEMUA baris lama tidak akurat — baris `mode=manual` historisnya berperilaku "sekali klik, sekali tagihan," jauh lebih dekat ke Tipe `sekali` daripada `bulanan`. Backfill-nya kondisional berdasarkan `mode` existing, bukan default kolom seragam.

**Catatan soal staging**: berbeda dari migrasi `tagihan.person_id` sebelumnya (yang dipisah nullable → backfill → NOT NULL sebagai 3 migration terpisah), di sini SATU migration saja cukup — backfill-nya deterministik 100% (setiap baris pasti dapat `tipe` karena `mode` sendiri sudah `NOT NULL` dengan cuma 2 kemungkinan nilai, tidak ada risiko "gagal di-resolve" seperti resolusi `person_id` lintas tabel yang bisa gagal kalau relasinya putus). Staging 3-langkah cuma perlu kalau ada risiko nyata sebagian baris tidak ter-backfill — di sini tidak ada risiko itu, jadi cukup satu migration:

```sql
ALTER TABLE jenis_tagihan
    ADD COLUMN tipe ENUM('harian','mingguan','bulanan','tahunan','sekali') NULL AFTER kategori,
    ADD COLUMN hari_generate TINYINT UNSIGNED NULL AFTER tanggal_generate,
    ADD COLUMN bulan_generate TINYINT UNSIGNED NULL AFTER hari_generate,
    ADD COLUMN offset_hari_jatuh_tempo SMALLINT UNSIGNED NULL AFTER hari_jatuh_tempo,
    DROP COLUMN last_generated_period;

ALTER TABLE tagihan
    MODIFY COLUMN billing_period VARCHAR(10) NULL;

UPDATE jenis_tagihan SET tipe = 'bulanan' WHERE mode = 'otomatis'; -- satu-satunya siklus yang mekanisnya benar-benar jalan sekarang
UPDATE jenis_tagihan SET tipe = 'sekali' WHERE mode = 'manual'; -- historisnya sekali klik, sekali tagihan -- lebih jujur daripada 'bulanan'

ALTER TABLE jenis_tagihan MODIFY COLUMN tipe ENUM('harian','mingguan','bulanan','tahunan','sekali') NOT NULL;
```

Catatan:
- `last_generated_period` dihapus di migration yang sama (bukan task terpisah tanpa jadwal) — alasan lengkap di §11.
- `billing_period` dilebarkan dari `varchar(7)` ke `varchar(10)` (cukup untuk format `Y-m-d`, format terpanjang di antara 5 varian).
- Test wajib untuk migration ini: assert SEMUA baris `jenis_tagihan` existing (dibuat lewat factory dengan `mode` bervariasi sebelum migrasi jalan) mendapat `tipe` yang benar sesuai `mode`-nya masing-masing setelah migrasi, dan kolom `tipe` benar-benar `NOT NULL` di akhir (percobaan insert tanpa `tipe` harus gagal di level DB).

## 6. Validasi form (`JenisTagihanController::baseRules()`)

```php
'tipe' => ['required', Rule::in(['harian', 'mingguan', 'bulanan', 'tahunan', 'sekali'])],
'hari_generate' => ['nullable', 'integer', 'between:1,7', 'required_if:mode,otomatis|tipe,mingguan'],
'bulan_generate' => ['nullable', 'integer', 'between:1,12', 'required_if:mode,otomatis|tipe,tahunan'],
'tanggal_generate' => ['nullable', 'integer', 'between:1,31', 'required_if:mode,otomatis|tipe,bulanan', 'required_if:mode,otomatis|tipe,tahunan'],
'hari_jatuh_tempo' => ['nullable', 'integer', 'between:1,31', 'required_if:mode,otomatis|tipe,bulanan', 'required_if:mode,otomatis|tipe,tahunan'],
'offset_hari_jatuh_tempo' => ['nullable', 'integer', 'min:0', 'required_if:mode,otomatis|tipe,harian', 'required_if:mode,otomatis|tipe,mingguan'],
```

**Catatan implementasi**: Laravel's `required_if` tidak mendukung sintaks kombinasi `field1,value1|field2,value2` secara native (itu OR dua kondisi terpisah, bukan AND) — kombinasi "Mode=Otomatis DAN Tipe=X" perlu ditulis sebagai `Rule::requiredIf(fn () => $request->input('mode') === 'otomatis' && $request->input('tipe') === 'mingguan')` per field, atau closure validation manual. Tuliskan closure ini eksplisit di implementasi, jangan pakai string `required_if` yang salah secara logika (AND vs OR).

**Validasi tambahan (custom rule, bukan bawaan Laravel)**: tolak kombinasi `mode=otomatis` + `tipe=sekali` dengan pesan eksplisit — "Tipe 'Sekali' tidak bisa dipasangkan dengan Mode Otomatis karena kontradiktif (generate berulang vs sekali saja)."

**Guard PPDB (existing, diperluas)**: `hasBillingPayload()` sudah mengecek `sasaran`/`tarif`/`keringanan`. Tambahkan `tipe`, `hari_generate`, `bulan_generate`, `offset_hari_jatuh_tempo` ke daftar field yang diblok kalau kategori PPDB — kirim field-field ini untuk kategori Pendaftaran/Daftar Ulang harus ditolak sama seperti field billing lain.

## 7. Perbaikan bug `hari_jatuh_tempo` (WAJIB SELESAI DULU sebelum §8-§10 dikerjakan)

**Bug saat ini** (`TagihanBillingGenerator::resolveDueDate()`): label form bilang "jumlah hari setelah tanggal generate" (offset), tapi kode menghitung tanggal absolut:

```php
// SEKARANG (salah secara semantik vs label):
$day = min($jenisTagihan->hari_jatuh_tempo, $daysInMonth);
return Carbon::create($year, $month, $day)->toDateString();
```

Kalau `tanggal_generate=25` dan `hari_jatuh_tempo=10`, hasilnya tanggal 10 di BULAN YANG SAMA (sebelum tanggal generate) — bukan "10 hari setelah tanggal 25" seperti yang diklaim label.

**Perbaikan**: ubah label form untuk `hari_jatuh_tempo` (Bulanan/Tahunan) menjadi eksplisit **"Tanggal jatuh tempo (tanggal di bulan yang sama, bukan jarak hari)"** — TIDAK mengubah perilaku kode (perilaku `Carbon::create($year, $month, $day)` yang sekarang justru benar untuk Bulanan/Tahunan, cuma labelnya yang menyesatkan). Field offset-hari yang sebenarnya (untuk Harian/Mingguan) memakai kolom BARU `offset_hari_jatuh_tempo` (§4.4), bukan field lama ini.

**Test wajib**: assert label form tidak lagi menyebut kata "setelah"/"offset" untuk `hari_jatuh_tempo`, dan tambahkan test yang secara eksplisit mendokumentasikan perilaku (tanggal absolut di bulan yang sama) supaya tidak dianggap bug lagi di masa depan.

## 8. `resolveDueDate()` — 5 cabang eksplisit per Tipe

```php
private function resolveDueDate(JenisTagihan $jenisTagihan, ?string $billingPeriod, Carbon $tanggalGenerateAktual): ?string
{
    return match ($jenisTagihan->tipe) {
        TipeTagihan::Sekali => null,

        TipeTagihan::Harian, TipeTagihan::Mingguan => $jenisTagihan->offset_hari_jatuh_tempo === null
            ? null
            : $tanggalGenerateAktual->copy()->addDays($jenisTagihan->offset_hari_jatuh_tempo)->toDateString(),

        TipeTagihan::Bulanan => $this->resolveDueDateBulanan($billingPeriod, $jenisTagihan->hari_jatuh_tempo),

        TipeTagihan::Tahunan => $this->resolveDueDateTahunan($billingPeriod, $jenisTagihan->bulan_generate, $jenisTagihan->hari_jatuh_tempo),
    };
}

private function resolveDueDateBulanan(?string $billingPeriod, ?int $hariJatuhTempo): ?string
{
    if (! $billingPeriod || ! $hariJatuhTempo) {
        return null;
    }

    $year = (int) substr($billingPeriod, 0, 4);
    $month = (int) substr($billingPeriod, 5, 2);
    $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

    return Carbon::create($year, $month, min($hariJatuhTempo, $daysInMonth))->toDateString();
}

private function resolveDueDateTahunan(?string $billingPeriod, ?int $bulanGenerate, ?int $hariJatuhTempo): ?string
{
    if (! $billingPeriod || ! $bulanGenerate || ! $hariJatuhTempo) {
        return null;
    }

    $year = (int) $billingPeriod;
    $daysInMonth = Carbon::create($year, $bulanGenerate, 1)->daysInMonth;

    return Carbon::create($year, $bulanGenerate, min($hariJatuhTempo, $daysInMonth))->toDateString();
}
```

**Perubahan signature**: `resolveDueDate()` sekarang butuh parameter tambahan `$tanggalGenerateAktual` (tanggal riil hari generator dijalankan, dipakai untuk cabang offset Harian/Mingguan) — ini mengubah signature method existing, semua pemanggilnya (di `generateForSiswa()`) perlu disesuaikan.

## 9. Generalisasi `billing_period` per Tipe

```php
private function resolveBillingPeriod(JenisTagihan $jenisTagihan, Carbon $tanggalGenerateAktual): ?string
{
    if ($jenisTagihan->mode !== 'otomatis') {
        return null; // Manual: selalu null, tidak berubah dari perilaku sekarang.
    }

    return match ($jenisTagihan->tipe) {
        TipeTagihan::Sekali => null, // constraint §6 menjamin ini tidak pernah mode=otomatis, tapi tetap didefinisikan untuk kelengkapan match
        TipeTagihan::Harian => $tanggalGenerateAktual->format('Y-m-d'),
        TipeTagihan::Mingguan => $tanggalGenerateAktual->format('o-\WW'), // 'o' KECIL wajib -- lihat catatan verifikasi di bawah
        TipeTagihan::Bulanan => $tanggalGenerateAktual->format('Y-m'), // tidak berubah
        TipeTagihan::Tahunan => $tanggalGenerateAktual->format('Y'),
    };
}
```

**Catatan verifikasi (WAJIB dipertahankan sebagai komentar kode)**: format ISO week HARUS pakai huruf `o` kecil (ISO week-numbering year), BUKAN `Y` besar (tahun kalender). Dikonfirmasi via test langsung: `Carbon::create(2027, 1, 1)->format('o-\WW')` menghasilkan `"2026-W53"` (bukan `"2027-W01"`), dan `Carbon::create(2025, 12, 29)->format('o-\WW')` menghasilkan `"2026-W01"`. Kalau salah pakai `Y`, dedup mingguan di sekitar pergantian tahun akan salah hitung (dua minggu yang secara ISO sama dianggap beda tahun, atau sebaliknya). Tulis test unit spesifik untuk kedua tanggal edge-case ini.

**Konfirmasi keamanan perubahan ini** (sudah digrep tuntas): tidak ada konsumen `billing_period` lain di luar `TagihanBillingGenerator.php` yang mem-parsing formatnya. `TagihanDiterbitkanNotification.php` dan `WhatsAppTemplateSeeder.php` cuma interpolasi string mentah ke pesan notifikasi (format-agnostic, aman terlepas dari format apapun). Tidak ada satupun blade/view/dashboard/export yang menyentuh kolom ini. Generalisasi format ini tidak berisiko breaking change di tempat lain.

## 10. Rewrite cron `GenerateTagihanHarian`

Query kandidat sekarang (SATU kondisi, cuma untuk Bulanan):

```php
// SEKARANG:
->where('tanggal_generate', $today->day)
```

**Diganti dengan percabangan eksplisit per Tipe**:

```php
$kandidat = JenisTagihan::withoutGlobalScope(TenantScope::class)
    ->where('mode', 'otomatis')
    ->where('is_active', true)
    ->where('tanggal_mulai', '<=', $today->toDateString())
    ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $today->toDateString()))
    ->where(function ($q) use ($today) {
        $q->where('tipe', TipeTagihan::Harian->value) // (1) Harian: tidak ada syarat tambahan -- generate tiap hari
            ->orWhere(function ($q2) use ($today) { // (2) Mingguan: DUA kondisi
                $q2->where('tipe', TipeTagihan::Mingguan->value)
                    ->where('hari_generate', $today->dayOfWeekIso);
            })
            ->orWhere(function ($q2) use ($today) { // (3) Bulanan: tidak berubah
                $q2->where('tipe', TipeTagihan::Bulanan->value)
                    ->where('tanggal_generate', $today->day);
            })
            ->orWhere(function ($q2) use ($today) { // (4) Tahunan: DUA kondisi
                $q2->where('tipe', TipeTagihan::Tahunan->value)
                    ->where('bulan_generate', $today->month)
                    ->where('tanggal_generate', $today->day);
            });
        // (5) Sekali: TIDAK ADA cabang -- constraint §6 menjamin tipe=sekali tidak pernah mode=otomatis,
        // jadi baris dengan tipe=sekali tidak mungkin lolos filter where('mode', 'otomatis') di atas.
    })
    ->get();
```

**Poin kritis untuk Tipe Mingguan**: kondisi `hari_generate === $today->dayOfWeekIso` SAJA TIDAK CUKUP sebagai penjamin anti-duplikat — itu cuma menentukan HARI mana kandidat dipertimbangkan, bukan mencegah generate dobel kalau cron sempat retry/ke-trigger dua kali di hari yang sama. Pencegahan duplikat yang sebenarnya tetap terjadi di `generateForSiswa()`'s existing dedup check (`exists()` query terhadap `billing_period`, sudah ada di kode), yang sekarang otomatis ikut bekerja untuk Mingguan begitu `billing_period` diisi format ISO-week (§9). **Tulis test yang secara eksplisit memanggil generator DUA KALI di hari yang sama untuk Tipe Mingguan, assert tagihan cuma dibuat SEKALI** — supaya kedua lapis perlindungan ini (query kandidat cron + dedup di generator) terverifikasi bekerja sama dengan benar, bukan cuma salah satu.

## 11. Penghapusan `jenis_tagihan.last_generated_period`

**Keputusan (dikonfirmasi user)**: field ini TIDAK di-reuse untuk mekanisme dedup baru. Alasan: field ini berada di level `jenis_tagihan` (satu definisi tagihan), sementara dedup yang sudah disepakati bekerja per-target (per siswa, via `exists()` check ke tabel `tagihan`). Kalau di-reuse sebagai cache di level `jenis_tagihan`, akan SALAH untuk skenario siswa baru yang masuk di tengah periode — cache akan bilang "periode ini sudah digenerate" padahal siswa baru itu belum pernah ditagih, menyebabkan dia terlewat (under-billing diam-diam, bug regresi nyata, bukan cuma isu YAGNI).

Kolom ini **dihapus di migration yang sama** dengan penambahan field baru (§5) — bukan ditunda ke task terpisah tanpa jadwal, karena area `billing_period`/dedup sudah disentuh langsung di scope ini, biaya hapus sekarang minimal, dan menunda tanpa jadwal jelas berisiko developer masa depan salah asumsi kolom ini aktif dipakai.

## 12. Test Requirements

- `TipeTagihan`/`HariDalamMinggu` enum: nilai dan `label()` benar untuk semua case.
- Validasi form: setiap kombinasi Mode × Tipe menghasilkan field wajib yang benar (§4.6) — 5 Tipe × 2 Mode = 10 kombinasi diuji eksplisit, plus penolakan Mode=Otomatis+Tipe=Sekali.
- Guard PPDB: kirim `tipe`/`hari_generate`/`bulan_generate`/`offset_hari_jatuh_tempo` untuk kategori Pendaftaran/Daftar Ulang → ditolak, sama seperti field billing lain.
- `resolveDueDate()`: 5 test terpisah, satu per Tipe, assert nilai due-date yang benar sesuai definisi §8 masing-masing (termasuk kasus `null` untuk Sekali, dan kasus field pendukung belum diisi → `null`, bukan error).
- `resolveBillingPeriod()`: 5 test terpisah, assert format string yang benar per Tipe, TERMASUK 2 test edge-case ISO-week di sekitar pergantian tahun (1 Januari & 29 Desember, nilai persis seperti temuan verifikasi di §9).
- Cron `GenerateTagihanHarian`: 5 test kandidat-matching (satu per Tipe, assert kandidat match/tidak-match sesuai kondisi hari ini), PLUS 1 test dedup-ganda khusus Mingguan (generator dipanggil 2x di hari yang sama, assert tagihan cuma 1).
- Migration: assert kolom baru ada dengan tipe/constraint yang benar, `last_generated_period` sudah tidak ada, `billing_period` sudah `varchar(10)`.
- Regression: seluruh test existing untuk Tipe Bulanan (perilaku default) harus tetap hijau tanpa perubahan hasil — Bulanan adalah satu-satunya Tipe yang perilakunya TIDAK berubah dari sekarang.

## 13. Urutan Implementasi

1. **Perbaiki label `hari_jatuh_tempo` (§7)** — task tersendiri, jalankan & pastikan hijau dulu SEBELUM langkah 2 dimulai.
2. **Migration** (§5) — kolom baru + hapus `last_generated_period` + lebarkan `billing_period`, dalam satu migration.
3. **Enum + validasi form** (§4, §6) — `TipeTagihan`, `HariDalamMinggu`, rules baru di `JenisTagihanController`, guard PPDB diperluas.
4. **`resolveDueDate()` + `resolveBillingPeriod()`** (§8, §9) — rewrite di `TagihanBillingGenerator`, dengan seluruh test per-Tipe.
5. **Rewrite cron `GenerateTagihanHarian`** (§10) — percabangan per Tipe + test dedup-ganda Mingguan.
6. **UI form** — 5 varian field dinamis sesuai Tipe (mockup sudah dikonfirmasi user di awal diskusi).
7. Full test suite.

## 14. Risiko

- **Perubahan signature `resolveDueDate()`** (tambah parameter `$tanggalGenerateAktual`) — pastikan SEMUA pemanggil di `generateForSiswa()` (dan di manapun lagi yang memanggilnya, cek via grep sebelum eksekusi) disesuaikan; kalau ada pemanggil yang terlewat, akan jadi fatal `ArgumentCountError`, bukan silent bug — mudah terdeteksi test tapi wajib diperiksa lengkap.
- **Backfill `tipe` untuk baris lama cuma berdasarkan `mode` (otomatis→bulanan, manual→sekali), bukan berdasarkan `kategori`-nya** — `jenis_tagihan` lama berkategori Tahunan/Kegiatan yang kebetulan `mode=otomatis` akan tercatat `tipe=bulanan` (akurat terhadap perilaku SEKARANG, karena itu satu-satunya siklus yang mekanisnya benar-benar jalan), tapi mungkin sebenarnya cocok dipindah ke Tipe Tahunan/Sekali begitu fitur ini rilis. Ini bukan bug — cuma keterbatasan backfill otomatis yang tidak bisa menebak maksud asli admin. Perlu catatan rilis/banner UI yang mengarahkan admin meninjau ulang `tipe` untuk `jenis_tagihan` lama mereka satu-satu, terutama yang kategorinya bukan SPP.
