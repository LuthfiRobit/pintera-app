# Modul Keuangan — Sub-project 2b-2: Tombol "Proses Tagihan" + Guard Kategori PPDB

> Status: Disetujui — siap ke Implementation Plan.

## Konteks & Dependensi

Bergantung pada Sub-project 2a (`TagihanBillingGenerator`, `JenisTagihanSasaranMatcher`, `billing:proses` console command, event listener `StudentCreated`/`StudentUpdatedClass`/`BillTypeActivated` — semua sudah live di `demo`) dan Sub-project 2b-1 (form Jenis Tagihan, sudah live di `demo`). Bagian kedua dari 3 sub-plan Sub-project 2b (2b-1 form ✅, 2b-2 tombol proses — dokumen ini, 2b-3 dashboard monitoring belum).

## Temuan Kritis yang Memperluas Scope

Saat brainstorming sub-project ini, ditemukan **bug keamanan-data yang sudah live di `demo`** (bukan baru diperkenalkan sub-project ini): `TagihanBillingGenerator` dan pemicu-pemicunya (cron, event listener, console command) tidak pernah memvalidasi bahwa `jenis_tagihan->kategori` BUKAN `pendaftaran`/`daftar_ulang`. Karena kategori PPDB TIDAK PERNAH punya `sasaran` grup dikonfigurasi (2b-1 memblokir ini server-side), `JenisTagihanSasaranMatcher::resolveTargetSiswa()`/`siswaMatchesJenisTagihan()` menganggap sasaran kosong = **cocok SEMUA siswa di lembaga**.

Dikonfirmasi langsung di database dev nyata (2026-08-11): kedelapan baris `jenis_tagihan` berkategori PPDB (`Biaya Pendaftaran`/`Uang Pangkal` × 4 lembaga) semuanya `is_active = true` (default model). Belum ada `tagihan` siswa yang tercemar (0 baris ditemukan), tapi listener `GenerateTagihanForNewStudent` (fire pada SETIAP `Siswa::created()`, TANPA filter kategori) akan langsung memicu bug ini begitu ada siswa baru dibuat — satu pendaftaran/import siswa berikutnya akan menghasilkan tagihan `pendaftaran`/`daftar_ulang` palsu untuk siswa tsb, terpisah dari alur PPDB yang sebenarnya. Ini HARUS diperbaiki sebagai bagian sub-project ini, bukan ditunda.

## Tujuan

1. **Guard berlapis** yang mencegah `TagihanBillingGenerator` memproses `jenis_tagihan` berkategori PPDB, dari SEMUA jalur pemicu.
2. Admin bisa memicu generate tagihan manual untuk satu `jenis_tagihan` (non-PPDB) langsung dari halaman index, dengan ringkasan hasil yang jelas.

## Desain Guard (Defense in Depth)

### Layer 1 — `TagihanBillingGenerator` (last line of defense, WAJIB untuk semua caller)

Tambah method private `assertBillable(JenisTagihan $jenisTagihan): void` yang throw `\RuntimeException` (pesan: `"Jenis tagihan berkategori {$jenisTagihan->kategori} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB."`) kalau `$jenisTagihan->kategori` termasuk `['pendaftaran', 'daftar_ulang']`. Dipanggil di AWAL kedua entry point publik:
- `generate(JenisTagihan $jenisTagihan, ...)` — dipanggil SEBELUM `resolveTargetSiswa()`, supaya tidak ada query pencarian siswa sia-sia dan tidak ada `billing_job_logs` row yang tercatat untuk kategori yang seharusnya tidak pernah diproses.
- `generateForSiswaViaEvent(Siswa $siswa, JenisTagihan $jenisTagihan, ...)` — sama, sebelum apa pun dieksekusi.

`generateForSiswa()` (method internal per-siswa, dipanggil dari dalam kedua entry point di atas) TIDAK perlu guard terpisah — kedua caller-nya sudah guard sebelum memanggilnya, jadi guard tambahan di sana cuma duplikasi tanpa nilai (kedua entry point publik itulah "pintu masuk" yang perlu dijaga).

### Layer 2 — Guard dini di setiap caller (pesan error jelas + hindari kerja sia-sia)

- **`GenerateTagihanForNewStudent`** (listener `StudentCreated`) — tambah `->whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])` LANGSUNG di query `JenisTagihan::withoutGlobalScope(...)`, supaya baris PPDB tidak pernah bahkan masuk ke `->each()`.
- **`GenerateTagihanForUpdatedClass`** (listener `StudentUpdatedClass`) — fix identik (query yang sama persis strukturnya, bug yang sama).
- **`GenerateTagihanForActivatedBillType`** (listener `BillTypeActivated`) — tidak ada query untuk difilter (cuma menerima satu `JenisTagihan` dari event), jadi tambah pre-check di awal `handle()`: kalau kategori PPDB, `return;` (skip diam-diam — ini adalah kondisi VALID, admin mengaktifkan kembali jenis_tagihan PPDB adalah aksi sah yang tidak seharusnya memicu billing generation apa pun, dan TIDAK BOLEH membuat request HTTP `update()` yang memicunya menjadi gagal/500).
- **`ProsesTagihan`** (console command `billing:proses`) — tambah pre-check di awal `handle()`, kalau kategori PPDB: `$this->error(...)`, return `self::FAILURE`.
- **`JenisTagihanController::prosesTagihan()`** (controller action baru, lihat di bawah) — pre-check sama, kalau kategori PPDB: response 422 JSON dengan pesan jelas.

### Testing Plan untuk Guard (WAJIB, per layer)

1. **`TagihanBillingGeneratorTest`** — test langsung ke `assertBillable()` via kedua entry point publik:
   - `generate()` dipanggil dengan `JenisTagihan` berkategori `pendaftaran` → assert throw `\RuntimeException`, assert `Tagihan::count()` tetap 0, assert `BillingJobLog::count()` tetap 0 (guard harus mencegah SEBELUM logging, bukan menghasilkan log kegagalan).
   - `generateForSiswaViaEvent()` dipanggil dengan `JenisTagihan` berkategori `daftar_ulang` → assert throw yang sama, assert tidak ada `Tagihan`/`BillingJobLog` tercipta.
2. **`StudentBillingEventsTest`** — test regresi LANGSUNG untuk bug yang ditemukan: buat `JenisTagihan` berkategori `pendaftaran`, `is_active=true`, TANPA sasaran (kondisi asli bug) di sebuah lembaga → buat `Siswa` baru di lembaga yang sama (memicu `StudentCreated`) → assert TIDAK ADA `Tagihan` baru tercipta untuk siswa tsb dengan kategori pendaftaran/daftar_ulang. Sama untuk `StudentUpdatedClass`.
3. **`BillTypeActivatedEventTest`** — test: `JenisTagihan` berkategori `daftar_ulang`, toggle `is_active` false→true (memicu event) → assert TIDAK throw (request tidak boleh gagal), assert tidak ada `Tagihan`/`BillingJobLog` tercipta.
4. **`ProsesTagihanCommandTest`** — test: jalankan `artisan billing:proses {id}` untuk `jenis_tagihan` berkategori PPDB → assert exit code `FAILURE`, assert tidak ada `Tagihan` tercipta.
5. **Test controller `prosesTagihan()` baru** (lihat bagian Testing di bawah) — assert 422 untuk kategori PPDB.

## Fitur: Tombol "Proses Tagihan"

### Endpoint

`POST admin/jenis-tagihan/{jenisTagihan}/proses` → `JenisTagihanController::prosesTagihan()`, permission `jenis-tagihan.edit` (aksi mengubah data tagihan, setara level akses dengan edit jenis_tagihan itu sendiri).

### Breakdown hasil: `sudah_tertagih` vs `tidak_memenuhi_kriteria`

Spec asli Sub-project 2 minta "X dibuat, Y dilewati, Z gagal" — tapi "dilewati" itu ambigu, bisa berarti dua hal yang sangat berbeda secara operasional buat admin:
- **`sudah_tertagih`**: siswa COCOK kriteria sasaran, tapi SUDAH punya tagihan aktif untuk `jenis_tagihan`+periode ini (idempotency check di `TagihanBillingGenerator::generateForSiswa()` — bukan bug, ini WORKING AS INTENDED, aman untuk di-generate ulang kapan saja).
- **`tidak_memenuhi_kriteria`**: siswa ada di lembaga tapi TIDAK cocok kriteria sasaran manapun (mis. field `kelas`/`jenis_kelamin`/`status_siswa` tidak match) — beda kelas informasi sama sekali, bukan soal duplikasi tapi soal apakah admin perlu cek ulang konfigurasi sasaran.

Menyatukan keduanya jadi satu angka bikin admin tidak bisa membedakan "wajar, memang sudah ditagih semua" dari "kok cuma sedikit yang ke-generate, jangan-jangan sasarannya salah". Dipisah eksplisit di response JSON.

**Cara hitung** (tanpa mengubah kontrak `TagihanBillingGenerator::generate()` yang sudah dipakai cron/event/command — semua breakdown dihitung di controller, komposisi dari method yang sudah ada + satu method baru):

1. `JenisTagihanSasaranMatcher::countTotalSiswaPool(JenisTagihan $jenisTagihan): int` (method BARU) — total siswa di lembaga ini SEBELUM kriteria sasaran diterapkan (query dasar yang sama dengan awal `resolveTargetSiswa()`, tanpa eager-load `kelas` karena cuma butuh count).
2. Controller: `$totalPool = $matcher->countTotalSiswaPool($jenisTagihan);`
3. Controller: `$targetCount = $matcher->resolveTargetSiswa($jenisTagihan)->count();`
4. Controller: `$log = $generator->generate($jenisTagihan, 'manual');`
5. Hitung: `tidak_memenuhi_kriteria = $totalPool - $targetCount`; `gagal = count($log->error_log ?? [])`; `sudah_tertagih = $targetCount - $log->bills_generated - $gagal`.

Trade-off yang disadari: langkah 3 dan 4 sama-sama memanggil `resolveTargetSiswa()` (satu eksplisit di controller, satu lagi implisit di dalam `generate()`) — query dobel yang secara teknis bisa dihindari kalau `generate()` diubah untuk mengembalikan breakdown ini langsung. Diputuskan TIDAK mengubah signature/return type `generate()` karena itu dipakai oleh cron/2 event listener yang sudah shipped+tested di 2a — breaking/memperluas kontraknya demi kebutuhan UI ini scope creep yang tidak sepadan. Query dobel diterima sebagai trade-off yang wajar untuk aksi manual admin (bukan hot path, dipicu per klik tombol, bukan per request pengguna).

### Response

```json
{
    "message": "5 tagihan dibuat, 12 sudah tertagih, 3 tidak memenuhi kriteria, 0 gagal.",
    "bills_generated": 5,
    "sudah_tertagih": 12,
    "tidak_memenuhi_kriteria": 3,
    "gagal": 0,
    "status": "success"
}
```

Kalau kategori PPDB: 422 `{"message": "Jenis tagihan berkategori Pendaftaran/Daftar Ulang tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB."}`.

### UI

Tombol "Proses Tagihan" di `x-table-actions` dropdown index, hanya dirender untuk kategori non-PPDB (`<template x-if="!['pendaftaran', 'daftar_ulang'].includes(item.kategori)">` — sama pola dengan "Kelola Nominal" yang sebaliknya). Ini KEMUDAHAN UI saja, bukan batas keamanan — batas sesungguhnya di server (Layer 1+2 di atas), konsisten dengan prinsip yang sudah divalidasi keras di 2b-1 ("jangan percaya UI saja").

Klik tombol → modal konfirmasi (pola sama dengan `deleteItem`'s `confirmDialog`, pesan: `"Proses tagihan untuk \"{nama}\"? Ini akan membuat tagihan baru untuk siswa yang cocok kriteria dan belum tertagih periode ini."`) → AJAX POST → toast hasil dengan `message` dari response di atas.

## Testing (fitur, di luar guard)

- Feature test: admin_keuangan POST `proses` untuk `jenis_tagihan` non-PPDB dengan sasaran cocok sebagian siswa → assert response berisi keempat angka yang benar (buat fixture dengan siswa yang: (a) cocok+belum tertagih, (b) cocok+sudah tertagih, (c) tidak cocok kriteria — assert masing-masing masuk kategori yang tepat).
- Feature test: POST untuk kategori PPDB → assert 422, assert tidak ada `Tagihan` baru.
- Feature test: user tanpa permission `jenis-tagihan.edit` → assert 403.
- Feature test: `countTotalSiswaPool()` di `JenisTagihanSasaranMatcherTest` → assert hitung semua siswa lembaga terlepas dari kriteria manapun.

## Yang TIDAK Termasuk Sub-project 2b-2

- Dashboard monitoring/daftar penerima/daftar tunggakan (→ 2b-3).
- Detail per-siswa yang gagal (cuma angka ringkas `gagal`, bukan daftar nama — spec asli cuma minta ringkasan; detail ada di `billing_job_logs.error_log` untuk siapa pun yang perlu investigasi lewat DB/log, bukan lewat UI ini).
- Live-update badge jumlah tagihan di baris index setelah proses selesai (toast saja; admin refresh manual kalau perlu lihat angka terbaru) — pemotongan scope yang wajar untuk MVP, tidak disebutkan di spec asli.
