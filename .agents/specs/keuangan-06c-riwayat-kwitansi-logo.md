# Spec — Keuangan Sub-project 6c: Riwayat Transaksi & Kwitansi PDF + Pengaturan Yayasan

**Status:** Draft, menunggu review.
**Depends on:** Sub-project 6a (SHIPPED — fondasi/dashboard), Sub-project 6b (SHIPPED — checkout multi-channel, `PembayaranTagihan`, `authorizePembayaran()`).
**Next in sequence:** Sub-project 6c2 (bundling top-up wallet + UI admin verifikasi transfer manual — dijadwalkan sebelum 6d), lalu Sub-project 6d (preferensi notifikasi).

## 1. Ringkasan

Sub-project ketiga dari 4 pemecahan Keuangan Sub-project 6 (Parent Dashboard & Kwitansi). Menambahkan dua fitur presentasi/laporan yang murni membaca data yang sudah ada dari 6a/6b — **tidak ada perubahan pada `PaymentService`, `PaymentAllocationService`, atau service Keuangan manapun**:

1. **Riwayat Transaksi & Kwitansi PDF** (portal orang tua) — daftar semua `Pembayaran` milik anak aktif dengan filter, plus unduh kwitansi PDF untuk pembayaran yang `lunas`.
2. **Pengaturan Yayasan** (admin) — halaman baru (belum ada sama sekali sebelumnya) untuk `yayasan_super_admin` mengelola seluruh data tabel `yayasan`, termasuk logo untuk branding kwitansi.

## 2. Keputusan Scope (dikonfirmasi user)

- **Fokus scope asli** — bundling top-up wallet dan UI admin verifikasi transfer manual (2 open item dari 6b) **tidak** masuk 6c, dijadwalkan jadi sub-project terpisah (6c2) sebelum 6d.
- **Kwitansi PDF: generate on-demand / stream**, tidak disimpan ke storage — dibuat ulang dari data `Pembayaran` setiap kali diunduh, tidak ada kolom `file_path` baru.
- **Riwayat menampilkan semua status** `Pembayaran` (lunas, menunggu_pembayaran, menunggu_verifikasi, gagal, dll), bukan cuma yang lunas — tombol unduh kwitansi hanya muncul di baris `lunas`.
- **Halaman Pengaturan Yayasan: akses `yayasan_super_admin` saja**, halaman baru mandiri (bukan ditambahkan ke form edit Lembaga), dan **mengelola SELURUH kolom tabel `yayasan`** (bukan cuma logo) — mengikuti pola UI/UX form edit Lembaga (`admin/lembaga/edit.blade.php`): kartu hero + toggle mode Lihat/Edit.

## 3. Arsitektur & Routing

**Riwayat & Kwitansi** — masuk grup `keuangan.*` yang sudah ada di `routes/web.php` (middleware `auth`, `verified`, `permission:keuangan.akses`, `resolve.active.siswa`):

| Method | Path | Name |
|---|---|---|
| GET | `/keuangan/riwayat` | `keuangan.riwayat.index` |
| GET | `/keuangan/riwayat/{pembayaran}/kwitansi` | `keuangan.riwayat.kwitansi` |

Controller baru: `App\Http\Controllers\Keuangan\RiwayatController` (`index`, `kwitansi`). Reuse `authorizePembayaran()` — method ini saat ini `private` di `CheckoutController` (6b); dipindahkan ke sebuah trait `App\Http\Controllers\Keuangan\Concerns\AuthorizesPembayaran` supaya bisa dipakai `RiwayatController` juga tanpa duplikasi logic, dan `CheckoutController` di-refactor untuk `use` trait ini (tidak mengubah behavior, murni pemindahan lokasi kode).

**Pengaturan Yayasan** — route baru di `routes/admin.php`, guard `web`, permission baru `yayasan.kelola` (digrant hanya ke role `yayasan_super_admin` via seeder):

| Method | Path | Name |
|---|---|---|
| GET | `/admin/pengaturan-yayasan` | `admin.yayasan.edit` |
| PUT | `/admin/pengaturan-yayasan` | `admin.yayasan.update` |

Controller baru: `App\Http\Controllers\Admin\YayasanSettingController` (`edit`, `update`). Karena hanya ada 1 record Yayasan per instalasi, tidak ada parameter `{id}` di URL — controller mengambil `Yayasan::first()`.

## 4. Halaman & Komponen UI

### 4.1 Riwayat Transaksi (`GET /keuangan/riwayat`)

- `<x-app-layout>`, nav sidebar tambah item "Riwayat" di grup Keuangan (setelah "Tagihan").
- Filter bar (server-rendered GET query string, bukan Alpine reactive — konsisten dengan pola list ber-filter non-Alpine di modul lain): date range `dari`/`sampai` (`<input type="date">`) + dropdown `metode` (Semua/VA BRI/QRIS/Saldo Wallet/Transfer Manual/Cash).
- Tabel kolom: Tanggal, Metode (label human-readable + ikon kecil), Rincian (ringkas: nama jenis tagihan pertama + "+N lainnya" kalau `pembayaranTagihan->count() > 1`), Total, Status (badge warna: hijau=lunas, kuning=menunggu_pembayaran/menunggu_verifikasi, abu=gagal/dibatalkan), Aksi.
- Kolom Aksi: tombol "Unduh Kwitansi" (`target=_blank`, link ke `keuangan.riwayat.kwitansi`) **hanya muncul** kalau `status === 'lunas'`.
- Paginasi 15/halaman (`->paginate(15)`, bukan `->get()` — riwayat berpotensi panjang seiring waktu, beda dari rekap tagihan aktif 6b yang biasanya sedikit baris).
- Empty state kalau belum ada riwayat sama sekali (belum pernah ada filter aktif) vs "tidak ada hasil untuk filter ini" (filter aktif tapi kosong) — dua pesan berbeda.

### 4.2 Kwitansi PDF (`GET /keuangan/riwayat/{pembayaran}/kwitansi`)

- Controller: `authorizePembayaran()` (via trait) → `abort_unless($pembayaran->status === 'lunas', 404)` → `Pdf::loadView('pdf.kwitansi', [...])->stream("kwitansi-{$pembayaran->id}.pdf")` — pola identik `BuktiPendaftaranController` (stream langsung, tidak disimpan ke disk).
- Template baru `resources/views/pdf/kwitansi.blade.php`, struktur mengikuti `resources/views/pdf/bukti-pendaftaran.blade.php`:
  - **Header**: nama+alamat lembaga, logo yayasan (`@if($yayasan->logo)` — kalau null, tidak render `<img>` sama sekali, bukan broken-image).
  - **Body**: nomor kwitansi (`KW-{pembayaran->id}`, tidak perlu sequence counter terpisah), tanggal pembayaran, identitas siswa (nama, NIS/NISN, kelas), tabel rincian dari `pembayaranTagihan` (nama jenis tagihan + nominal per baris — kalau collection kosong, tabel tetap tampil dengan baris "Rincian tidak tersedia" + total dari `pembayaran->amount`, bukan lempar 500), total, metode pembayaran (label human-readable).
  - **Footer**: teks statis placeholder tanda tangan/stempel administrasi (bukan gambar tanda tangan digital asli — di luar scope).
- Data yang dikirim ke view: `$pembayaran` (eager-load `pembayaranTagihan.tagihan.jenisTagihan`), `$siswa` (dari `$pembayaran->siswa`), `$lembaga` (dari `$siswa->lembaga`), `$yayasan` (dari `$lembaga->yayasan`).

### 4.3 Pengaturan Yayasan (`GET/PUT /admin/pengaturan-yayasan`)

- View baru `resources/views/admin/yayasan/edit.blade.php`, mengikuti pola persis `admin/lembaga/edit.blade.php` + `tabs/profil.blade.php` — **tanpa struktur tab** (Yayasan cuma 1 record, tidak ada sub-koleksi seperti Ekstrakurikuler-nya Lembaga):
  - `x-data="{ mode: {{ $errors->any() ? "'edit'" : "'view'" }} }"` — toggle "Mode Edit Profil" identik gaya Lembaga.
  - Kartu hero: ikon + nama yayasan + jumlah lembaga naungan (`{{ $yayasan->lembaga->count() }}`).
  - **Mode Lihat**: `<dl>` grid 2 kartu — "Identitas Yayasan" (nama, NPWP, alamat, telepon, email, website, logo preview) dan "Legalitas & Kepemimpinan" (no. akta pendirian + tanggal, no. SK Kemenkumham, nama ketua pembina, nama ketua pengurus). NPWP ditampilkan penuh (tidak di-mask) karena halaman ini sudah dibatasi ke `yayasan_super_admin`.
  - **Mode Edit**: form dengan input untuk semua field (text/date/textarea sesuai tipe kolom dari migrasi `2026_07_12_090129_create_yayasan_table.php`) + `<input type="file" name="logo">` (accept `.jpg,.jpeg,.png,.svg`, live preview via Alpine `URL.createObjectURL` sebelum submit, fallback ke logo lama kalau belum pilih file baru).
- Nav sidebar admin tambah item "Pengaturan Yayasan" (gated `@can('yayasan.kelola')`).

## 5. Data Flow & Validasi

### 5.1 `RiwayatController@index`
- `$activeSiswa = $request->attributes->get('activeSiswa')`; kalau null → `view('keuangan.tanpa-anak')` (pola sama seperti `TagihanController`).
- Query: `Pembayaran::where('siswa_id', $activeSiswa->id)` — **bukan** query `Tagihan`, karena riwayat berbasis catatan pembayaran, bukan tagihan. `Pembayaran` tidak punya `TenantScope`, tidak perlu `withoutGlobalScope`.
- Filter: `when($dari && $sampai valid, fn ($q) => $q->whereBetween('created_at', [...]))`, `when($metode, fn ($q) => $q->where('metode', $metode))`. Kalau `dari > sampai`: **abaikan filter tanggal** (treat sebagai tanpa filter), jangan redirect error — kasus non-kritis.
- Eager-load `pembayaranTagihan.tagihan.jenisTagihan`, urut `created_at desc`, `paginate(15)` (mempertahankan query string filter di link paginasi via `->appends($request->query())`).

### 5.2 `RiwayatController@kwitansi`
- `authorizePembayaran()` (via trait, sama logic dengan 6b: cek `Pembayaran->siswa_id` termasuk anak `Auth::user()->orangTua`).
- `abort_unless($pembayaran->status === 'lunas', 404)`.
- Load relasi yang dibutuhkan template, render + stream.

### 5.3 `YayasanSettingController@edit`
- `$yayasan = Yayasan::first()`; kalau `null` → tampilkan view state "belum ada data yayasan" (defensif, seharusnya tidak terjadi di data yang ada).

### 5.4 `YayasanSettingController@update`
- Validasi: `nama` required string; `npwp_yayasan`, `akta_pendirian_nomor`, `sk_kemenkumham_nomor`, `telepon`, `email` (email format), `website` (url format), `nama_ketua_pembina`, `nama_ketua_pengurus` nullable string; `akta_pendirian_tanggal` nullable date; `alamat` nullable string (textarea); `logo` nullable file `mimes:jpg,jpeg,png,svg|max:1024`.
- Kalau ada file `logo` baru: `Storage::disk('public')->put(...)`, lalu **hapus file lama** (`Storage::disk('public')->delete($yayasan->logo)`) kalau `$yayasan->logo` sebelumnya tidak null — cegah sampah file menumpuk.
- Update record, redirect back dengan `session('status', 'Data yayasan berhasil diperbarui.')`. `LogsActivity` trait yang sudah ada di model `Yayasan` otomatis mencatat perubahan field yang di-log (`getActivitylogOptions()` sudah ada, tidak perlu diubah).
- Gagal validasi → redirect back dengan errors, view otomatis buka `mode='edit'` (pola `$errors->any()` sama seperti Lembaga).

## 6. Error Handling

| Skenario | Penanganan |
|---|---|
| Akses kwitansi untuk `Pembayaran` yang belum lunas | 404 (bukan halaman kosong/500) |
| Akses riwayat/kwitansi milik anak orang tua lain | 403 via `authorizePembayaran()` — wajib test cross-parent eksplisit |
| Filter tanggal `dari > sampai` | Diabaikan, tampil semua (tidak ada filter tanggal aktif) |
| Logo yayasan belum diupload saat kwitansi dicetak | Template render tanpa `<img>` logo, tidak error |
| `pembayaranTagihan` kosong pada `Pembayaran` lunas (edge case teoretis) | Kwitansi tetap render, baris "Rincian tidak tersedia" + total dari `pembayaran->amount` |
| Upload logo dengan format/ukuran salah | Validasi `mimes:jpg,jpeg,png,svg\|max:1024`, error tampil di form, `mode='edit'` tetap terbuka |
| Yayasan belum ada (instalasi baru) | View defensif "belum ada data yayasan", bukan crash |
| Non-`yayasan_super_admin` akses `/admin/pengaturan-yayasan` | 403 via `permission:yayasan.kelola` middleware |

## 7. Testing Strategy

- **Feature tests**: `RiwayatControllerTest` (filter tanggal/metode, paginasi, tombol kwitansi hanya di baris lunas, empty-state dua varian), `KwitansiControllerTest` (404 untuk non-lunas, `assertOk()` + content-type `application/pdf` untuk lunas — tidak parse isi PDF), `YayasanSettingControllerTest` (update semua field, validasi logo, file lama terhapus saat ganti logo, permission gate untuk non-`yayasan_super_admin`).
- **Cross-parent authorization test** (pola dua-pihak wajib, bukan fixture satu-pihak — pelajaran berulang dari 6a/6b): parent A tidak bisa lihat riwayat parent B (`assertDontSee`), dan 403 saat akses `keuangan.riwayat.kwitansi` milik parent B.
- **Manual browser verification (Playwright)**: 1 check minimal — buka halaman riwayat, filter berfungsi, klik "Unduh Kwitansi" pada baris lunas menghasilkan response dengan content-type PDF. Halaman admin Yayasan cukup dites via feature test PHP (form biasa, bukan Alpine-heavy dengan interaksi kompleks).
- **Regression**: selama proses, jalankan `tests/Feature/Keuangan/` + test file admin Yayasan yang baru — bukan full-suite tiap task. Full-suite sekali di akhir sebagai gerbang terakhir, terisolasi (tidak concurrent dengan proses test lain).

## 8. Eksplisit di Luar Scope 6c

- Bundling top-up wallet saat checkout VA/QRIS (dijadwalkan jadi Sub-project 6c2).
- UI admin approve/reject bukti transfer manual (dijadwalkan jadi Sub-project 6c2).
- Preferensi/pengaturan notifikasi (→ 6d).
- Tanda tangan digital asli pada kwitansi (footer PDF pakai teks statis placeholder).
- Multi-yayasan per instalasi (asumsi 1 record `Yayasan`, `Yayasan::first()`).
- Perubahan pada `PaymentService`, `PaymentAllocationService`, `AutoAllocationEngine`, atau `Wallet`.
