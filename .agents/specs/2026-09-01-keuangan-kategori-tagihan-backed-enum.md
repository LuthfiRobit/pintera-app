# Spec: Keuangan — `KategoriTagihan` sebagai PHP Backed Enum (Paket #4)

- **Branch**: `keuangan-v2`
- **Tanggal**: 1 September 2026
- **Konteks**: Paket terpisah dari `.agents/specs/2026-09-01-keuangan-tagihan-person-id-keringanan-ui.md` (paket #1+#2+#3) — dipisah karena radius kerja signifikan lebih besar dari perkiraan awal §5 spec itu (7 titik `PPDB_KATEGORI` saja). Setelah audit mendalam (grep + verifikasi empiris lewat tinker, bukan asumsi), ditemukan **13 titik patah** tersebar di `app/` dan `resources/views/`, termasuk **1 crash point** (fatal `Error`, bukan cuma salah tampil).
- **Revisi 2026-09-01 (post-review)**: versi sebelumnya (`11bf2ac7`) menandai `JenisTagihanController.php:129,175` sebagai aman di §3, padahal §4.3 menginstruksikan menghapus `self::PPDB_KATEGORI` di file yang sama — kontradiksi yang akan menyebabkan fatal `Undefined constant` begitu konstanta dihapus. Diperbaiki: kedua baris dipindah ke §4.2 sebagai titik wajib-perbaiki (total naik dari 11 ke 13 titik).

## 1. Keputusan Desain Terkunci

1. `jenis_tagihan.kategori` dan `tagihan.kategori` — KEDUA kolom di-cast ke enum yang SAMA (`KategoriTagihan`), karena keduanya berbagi 7 nilai valid yang identik dan `tagihan.kategori` pada dasarnya adalah salinan nilai dari `jenis_tagihan.kategori` saat tagihan dibuat.
2. **Kolom TETAP 7 nilai** (`pendaftaran`, `daftar_ulang`, `spp`, `tahunan`, `kegiatan`, `custom`, `lainnya`) — TIDAK dipangkas jadi 2. Alasan: `kategori` berfungsi sebagai label akuntansi untuk pelaporan (relevan untuk kebutuhan Buku Kas & BOS di masa depan), terpisah dari perannya sebagai discriminator routing (PPDB vs non-PPDB). Memangkas ke 2 nilai akan menghilangkan kapasitas pelaporan tanpa keuntungan nyata.
3. **Pemisahan peran discriminator vs label** (menambah kolom `tagihable_type` terpisah di `jenis_tagihan`, membebaskan `kategori` dari tanggung jawab routing) — **TETAP DI LUAR SCOPE paket ini**, sudah dicatat sebagai bagian dari inisiatif "penyatuan generator" yang ditunda (Non-Goal #3 di spec paket #1+2+3). Paket #4 ini HANYA memperbaiki bug perbandingan string vs enum object — TIDAK merombak makna/peran `kategori`.
4. **Tidak perlu migration ubah tipe kolom** — kolom sudah native MySQL `ENUM(...)` di kedua tabel (dikonfirmasi via Laravel Boost `database-schema`), cukup pasang `casts()` di level Eloquent.

## 2. Definisi Enum

```php
<?php

namespace App\Domains\Keuangan\Enums;

enum KategoriTagihan: string
{
    case Pendaftaran = 'pendaftaran';
    case DaftarUlang = 'daftar_ulang';
    case Spp = 'spp';
    case Tahunan = 'tahunan';
    case Kegiatan = 'kegiatan';
    case Custom = 'custom';
    case Lainnya = 'lainnya';

    /**
     * Menggantikan seluruh mekanisme private const PPDB_KATEGORI yang
     * sebelumnya didefinisikan independen di 4 file berbeda -- satu-satunya
     * sumber kebenaran untuk "apakah kategori ini termasuk alur PPDB".
     */
    public function isPpdb(): bool
    {
        return in_array($this, [self::Pendaftaran, self::DaftarUlang], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendaftaran => 'Pendaftaran',
            self::DaftarUlang => 'Daftar Ulang',
            self::Spp => 'SPP',
            self::Tahunan => 'Tahunan',
            self::Kegiatan => 'Kegiatan',
            self::Custom => 'Custom',
            self::Lainnya => 'Lainnya',
        };
    }
}
```

Pasang di kedua model:

```php
// app/Domains/Keuangan/Models/JenisTagihan.php dan Tagihan.php
protected function casts(): array
{
    return [
        // ...cast existing tetap ada...
        'kategori' => \App\Domains\Keuangan\Enums\KategoriTagihan::class,
    ];
}
```

## 3. Titik yang AMAN — dikonfirmasi empiris, TIDAK perlu diubah

| Kategori | Bukti |
|---|---|
| Query builder (`where('kategori', 'spp')`, `whereNotIn('kategori', [...])`, `whereHas(...)->where('kategori', ...)`) — ~10 titik di `WelcomeController`, `ResolvesWizardContext`, `RegisteredAkunController`, `Portal\DashboardController`, `Pendaftaran.php`, 2 listener, dll | Eloquent menerjemahkan ke SQL langsung terhadap kolom ENUM native, tidak lewat perbandingan objek PHP. |
| `@js(old('kategori', $jenisTagihan?->kategori ?? 'lainnya'))` — `form.blade.php:25` | Diverifikasi tinker: `Illuminate\Support\Js::from(BackedEnum)` otomatis unwrap ke `->value`. |
| `Rule::in([...])` — `JenisTagihanController.php:336` | Memvalidasi string mentah dari HTTP request SEBELUM pernah di-cast — tidak tersentuh sama sekali oleh perubahan ini. |
| `in_array($request->input('kategori'), self::PPDB_KATEGORI, true)` — `JenisTagihanController.php:129,175` | Membandingkan raw request input (string), bukan atribut Eloquent yang sudah di-cast. |
| `response()->json(['data' => $jenisTagihan])` dan `loadCount()` — `JenisTagihanController.php:156,198` | Diverifikasi tinker dengan cast sungguhan: `Model::toArray()`/`toJson()` otomatis unwrap `BackedEnum` ke `->value` — output JSON identik byte-per-byte sebelum/sesudah cast. Nol perubahan kontrak API. |
| Factory/seeder (`TagihanSeeder`, `KeuanganDemoSeeder`, `JenisTagihanFactory`) assign kategori sebagai string literal | Eloquent backed-enum cast menerima string mentah saat mass-assignment; cast cuma berlaku saat membaca balik. |
| `spmb-pendaftaran/show.blade.php:218` — `value="{{ $kategori }}"` | `$kategori` adalah variabel loop (array key string dari `['pendaftaran' => ..., 'daftar_ulang' => ...]`), bukan atribut `$item->kategori`. |

> **Koreksi**: `JenisTagihanController.php:129,175` (`in_array($request->input('kategori'), self::PPDB_KATEGORI, true)`) SEBELUMNYA tercantum di sini sebagai aman. Ini salah — meski beroperasi pada raw request input (bukan atribut Eloquent), keduanya tetap MEREFERENSIKAN `self::PPDB_KATEGORI`, yang dihapus oleh §4.3. Dipindah ke §4.2 sebagai titik #12 dan #13.

## 4. Titik yang PATAH — WAJIB diperbaiki (13 titik, prioritas Crash dulu)

### 4.1 Crash (fatal `Error`, bukan cuma salah tampil) — 1 titik

| File:baris | Kode saat ini | Perbaikan |
|---|---|---|
| `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:232` | `"...kategori {$jenisTagihan->kategori} tidak bisa..."` (string interpolation, PHP BackedEnum TIDAK implement `__toString()` — diverifikasi tinker: throw `Error`) | `"...kategori {$jenisTagihan->kategori->label()} tidak bisa..."` (atau `->value` kalau butuh nilai mentah, bukan label) |

### 4.2 Salah perilaku (comparison patah, silent — bukan crash) — 12 titik

| # | File:baris | Bentuk saat ini | Perbaikan |
|---|---|---|---|
| 1 | `app/Console/Commands/ProsesTagihan.php:28` | `in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)` | `$jenisTagihan->kategori->isPpdb()` |
| 2 | `app/Domains/Keuangan/Services/TagihanBillingGenerator.php:122` | idem | idem |
| 3 | `app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php:18` | idem | idem |
| 4 | `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:230` | idem | idem |
| 5 | `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:264` | idem | idem |
| 6 | `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:288` | idem | idem |
| 7 | `app/Http/Controllers/Lembaga/Keuangan/PembayaranController.php:34` | `$pembayaran->tagihan->kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang'` | `$pembayaran->tagihan->kategori->label()` (sekalian perbaiki bug laten: ternary lama SELALU salah label untuk kategori bukan 'pendaftaran'/'daftar_ulang') |
| 8 | `resources/views/portal/tagihan/index.blade.php:24` | `{{ $tagihan->kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang' }}` | `{{ $tagihan->kategori->label() }}` |
| 9 | `resources/views/portals/lembaga/keuangan/jenis-tagihan/_daftar.blade.php:19` | `@if (in_array($item->kategori, ['pendaftaran', 'daftar_ulang']))` | `@if ($item->kategori->isPpdb())` |
| 10 | `resources/views/portals/lembaga/keuangan/jenis-tagihan/_daftar.blade.php:33-40` | `@switch($item->kategori) @case('pendaftaran') Pendaftaran @break ...` | `{{ $item->kategori->label() }}` (ganti seluruh blok `@switch/@case` dengan satu baris) |
| 11 | `resources/views/admin/spmb-pendaftaran/show.blade.php:200` | `@forelse ([...] as $kategori => $label) ... $pendaftaran->tagihan->firstWhere('kategori', $kategori)` | `$pendaftaran->tagihan->first(fn ($t) => $t->kategori === \App\Domains\Keuangan\Enums\KategoriTagihan::from($kategori))` (bandingkan enum-ke-enum, bukan enum-ke-string) |
| 12 | `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:129` | `$isPpdbKategori = in_array($request->input('kategori'), self::PPDB_KATEGORI, true);` | `$isPpdbKategori = \App\Domains\Keuangan\Enums\KategoriTagihan::tryFrom($request->input('kategori'))?->isPpdb() ?? false;` (BUKAN `->isPpdb()` langsung — `$request->input()` masih string mentah, belum ter-cast, jadi harus lewat `tryFrom()` dulu; `tryFrom()` mengembalikan `null` untuk string tidak valid, ditangani dengan `?? false`) |
| 13 | `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:175` | idem baris 129 | idem baris 129 |

### 4.3 Konsolidasi `PPDB_KATEGORI`

Setelah semua titik di §4.2 memakai `->isPpdb()` (titik model-attribute) atau `KategoriTagihan::tryFrom(...)?->isPpdb() ?? false` (titik raw-request-input, yaitu #12 dan #13), hapus SELURUH `private const PPDB_KATEGORI = [...]` yang terduplikasi (4 file: `ProsesTagihan.php`, `TagihanBillingGenerator.php`, `JenisTagihanController.php`, `GenerateTagihanForActivatedBillType.php`) — ini menggantikan §5 di spec paket #1+2+3 SEPENUHNYA (bukan tambahan terpisah, `isPpdbKategori()` yang diusulkan di sana TIDAK jadi dibuat, digantikan `KategoriTagihan::isPpdb()`).

**Urutan wajib di dalam file `JenisTagihanController.php` sendiri**: titik #4, #5, #6 (baris 230/264/288, model attribute) DAN titik #12, #13 (baris 129/175, raw request input) HARUS SEMUA selesai diperbaiki SEBELUM `self::PPDB_KATEGORI` di file itu dihapus — kalau tidak, salah satu dari keduanya akan tetap mereferensikan konstanta yang sudah tidak ada dan menyebabkan fatal `Undefined constant`.

**Penting untuk urutan eksekusi**: paket #4 ini MENGGANTIKAN §5 paket #1+2+3, bukan berjalan berdampingan dengannya. Kalau paket #1+2+3 sudah dieksekusi lebih dulu (termasuk §5-nya) sebelum paket #4 dimulai, maka §5's `JenisTagihan::isPpdbKategori()` (const biasa) HARUS dihapus dan diganti `KategoriTagihan::isPpdb()` sebagai bagian dari eksekusi paket #4 — bukan dipertahankan sebagai 2 mekanisme paralel.

## 5. Test Requirements

- `KategoriTagihan::isPpdb()` benar untuk `Pendaftaran`/`DaftarUlang` (true) dan 5 nilai lain (false).
- `KategoriTagihan::label()` benar untuk ketujuh nilai.
- Regression untuk SEMUA 13 titik di §4 — assert perilaku SAMA seperti sebelum refactor, KECUALI titik #7 dan #8 yang sengaja diperbaiki labelnya (assert label yang BENAR, bukan cuma "tidak error").
- Test spesifik titik #12/#13: `tryFrom()` dengan string kategori valid → `isPpdbKategori` benar; dengan string TIDAK valid/kosong (mis. request tanpa parameter `kategori`) → `isPpdbKategori` `false`, bukan error (membuktikan `?? false` menutup jalur `tryFrom()` mengembalikan `null`).
- **Test spesifik untuk titik crash (§4.1)**: request ke endpoint `prosesTagihan()` dengan `JenisTagihan` berkategori PPDB — assert response 422 dengan pesan yang benar (bukan 500).
- Regression `toJson()`/`response()->json()` di `JenisTagihanController.php:156,198` — assert payload JSON mengandung `"kategori":"<value>"` sebagai string mentah (bukan object), membuktikan tidak ada perubahan kontrak API.
- `Rule::in()` di form create/update `JenisTagihan` tetap menerima ketujuh nilai valid dan menolak nilai lain — tidak berubah.

## 6. Urutan Implementasi

1. Buat `App\Domains\Keuangan\Enums\KategoriTagihan`.
2. Pasang `casts()` di `JenisTagihan` dan `Tagihan`.
3. Perbaiki titik crash (§4.1) — PALING PRIORITAS, jalankan test-nya duluan untuk buktikan tidak ada 500 lagi.
4. Perbaiki 12 titik comparison (§4.2, termasuk #12/#13), satu per satu, jalankan test scoped tiap titik. Dalam `JenisTagihanController.php`, kelima titiknya (#4, #5, #6, #12, #13) harus SEMUA selesai sebelum lanjut ke langkah 5.
5. Hapus 4 `PPDB_KATEGORI` const yang terduplikasi (§4.3) — paket #1+2+3 TIDAK membuat `isPpdbKategori()` sama sekali (lihat §7 keputusan urutan paket), jadi tidak ada yang perlu dihapus dari sisi itu.
6. Full test suite — pastikan tidak ada regresi di luar 13 titik yang memang sengaja diubah.

## 7. Urutan Paket & Risiko

- **Keputusan urutan lintas-paket** (mengganti asumsi sebelumnya): paket #4 (dokumen ini) dieksekusi PENUH terlebih dahulu, sampai full test suite hijau. BARU SETELAH ITU paket #1+2+3 dieksekusi, dengan §5 paket itu (sentralisasi PPDB_KATEGORI) disederhanakan menjadi "pakai `KategoriTagihan::isPpdb()` yang sudah ada" — `JenisTagihan::isPpdbKategori()` TIDAK PERNAH dibuat sama sekali. Ini menghilangkan risiko "2 mekanisme paralel" yang sebelumnya dicatat di sini sebagai risiko, karena tidak ada lagi skenario 2 sesi mengerjakan kedua paket tanpa koordinasi — urutannya sudah tegas dan tunggal.
- **Titik lain yang mungkin belum ditemukan** — audit ini sudah 2 putaran (grep awal + grep mendalam + verifikasi empiris) dan sudah dikoreksi sekali (§3 vs §4.3 kontradiksi, kini diperbaiki), tapi seperti pola berulang di sesi ini, tidak menutup kemungkinan ada titik ke-14 yang terlewat. Rekomendasi: jalankan full test suite SEBELUM dan SESUDAH pasang `casts()` (langkah 2 di §6) sebagai jaring pengaman — kalau ada titik yang terlewat, test yang sudah ada kemungkinan akan gagal dan menunjukkan lokasinya.
