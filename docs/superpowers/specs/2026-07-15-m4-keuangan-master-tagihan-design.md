# Keuangan — Master Tagihan & Mesin Invoicing — Design Spec

**Tanggal:** 2026-07-15
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Ini adalah **sub-project 2 dari 3** rangkaian modul Keuangan (mengikuti PRD bagian 11, M4-M5), lanjutan dari sub-project 1 (Portal Akun Pendaftar, sudah selesai). Urutan lengkapnya:

1. Portal Akun Pendaftar (selesai) — akun calon siswa + penautan otomatis ke Pendaftaran.
2. **Master Tagihan & Mesin Invoicing** (spec ini) — jenis tagihan generik lintas konteks, nominal per jalur, pembuatan tagihan otomatis dari event SPMB.
3. Pembayaran Manual & Portal Tagihan — skema cicilan, upload bukti transfer, verifikasi admin keuangan, tampilan tagihan di Portal Akun Pendaftar.

Sub-project ini murni membangun **apa yang harus dibayar** (master data + tagihan otomatis). **Bagaimana cara membayarnya** (cicilan, transfer manual, verifikasi) adalah domain sub-project 3, belum dikerjakan di sini.

## 2. Lingkup

**Termasuk:**
- Master `jenis_tagihan` per lembaga — generik, tidak terikat kata "SPMB" di struktur/nama kolom, siap dipakai jenis tagihan lain (SPP, uang pangkal, dst) di masa depan.
- Nominal per kombinasi jalur × jenis tagihan, termasuk Rp 0 (gratis).
- Mesin invoicing — otomatis membuat tagihan pendaftaran (event: submit M2) dan tagihan daftar ulang (event: keputusan diterima, M3).
- Tombol manual "Buat Tagihan Susulan" untuk menutup celah kalau nominal baru dikonfigurasi setelah pendaftaran sudah berjalan.
- Permission + tampilan admin untuk `admin_keuangan` (role yang sejak awal di-seed tapi belum pernah punya fitur nyata).

**Tidak termasuk (sengaja ditunda ke sub-project 3):**
- Skema cicilan (`skema_cicilan`, `cicilan`) — jumlah cicilan ditentukan calon siswa saat mulai membayar, bukan saat tagihan dibuat.
- Pembayaran itu sendiri (transfer manual, VA BRI, verifikasi admin).
- Tampilan tagihan di Portal Akun Pendaftar (dashboard calon siswa) — sub-project 1 hanya membangun dashboard pendaftaran, belum ada apapun soal tagihan di sana.
- Penggajian guru — arah uang berlawanan (pengeluaran, bukan piutang), akan jadi modul terpisah meniru pola (master + audit + verifikasi) tanpa berbagi tabel `tagihan`/`tagihan_item`.

**Keputusan desain kunci (dari brainstorming):**
- Verifikasi dokumen & keputusan diterima/ditolak (M3) **tidak menunggu** tagihan pendaftaran lunas — keduanya berjalan independen, konsisten dengan prinsip non-blocking M3. "Wajib lunas" (PRD) berarti tagihan pendaftaran tidak boleh dicicil, bukan gerbang alur SPMB.
- `tagihan` terikat ke `Pendaftaran` tertentu (bukan `CalonMurid` secara umum) — satu upaya SPMB, satu (atau dua) tagihan.
- Nominal dikonfigurasi per **lembaga + jalur**, bukan per lembaga saja — mendukung kasus jalur afirmasi/beasiswa gratis tanpa hardcode nama jalur di kode.

## 3. Model Data

### 3.1 `jenis_tagihan`
```
id
lembaga_id       (FK)
nama             (string, misal "Biaya Pendaftaran", "Uang Pangkal")
kategori         (enum: 'pendaftaran', 'daftar_ulang', 'lainnya')
bisa_dicicil     (boolean, default false)
maks_cicilan     (int, nullable — hanya relevan kalau bisa_dicicil)
timestamps
```
`kategori` adalah kunci yang membuat mesin invoicing tahu "untuk event pendaftaran, jenis tagihan mana saja yang relevan?" — nilai `'lainnya'` disiapkan untuk kebutuhan masa depan (SPP, dst) yang tidak dipicu otomatis oleh event SPMB apapun di sub-project ini.

### 3.2 `nominal_tagihan_jalur`
```
id
jenis_tagihan_id (FK)
jalur_ppdb_id    (FK)
nominal          (decimal, bisa 0)
timestamps

unique (jenis_tagihan_id, jalur_ppdb_id)
```

### 3.3 `tagihan` (header)
```
id
pendaftaran_id   (FK)
kategori         (enum: 'pendaftaran', 'daftar_ulang' — disalin dari jenis_tagihan.kategori saat dibuat, merekam KENAPA tagihan ini ada)
total_tagihan    (decimal)
status           (enum: 'belum_bayar', 'dicicil', 'lunas' — 'dicicil' disiapkan di enum sekarang, baru ditulis di sub-project 3, pola yang sama seperti Pendaftaran.status dulu menyiapkan daftar_ulang/aktif sebelum dipakai)
jatuh_tempo      (date, nullable — SELALU null saat dibuat mesin invoicing di sub-project ini; belum ada aturan bisnis "berapa hari tenggat" yang disepakati. Sub-project 3 yang mengisi kolom ini saat skema pembayaran/cicilan dikonfigurasi.)
timestamps
```
Satu `Pendaftaran` bisa punya sampai dua baris `tagihan` sepanjang hidupnya (satu `kategori='pendaftaran'`, satu `kategori='daftar_ulang'`) — dibuat di titik waktu yang berbeda, tidak pernah digabung jadi satu baris yang di-update.

### 3.4 `tagihan_item`
```
id
tagihan_id       (FK ke tagihan.id)
jenis_tagihan_id (FK)
jumlah           (decimal)
timestamps
```
Item bernilai 0 tetap tercatat (untuk transparansi/laporan), tidak dihilangkan.

## 4. Mesin Invoicing

### 4.1 Prinsip Inti — Jangan Pernah Berbohong Soal "Lunas"

Fungsi generate (dipanggil dengan `Pendaftaran` + `kategori`):
1. Ambil semua `jenis_tagihan` milik lembaga pendaftaran ini dengan `kategori` yang diminta.
2. Untuk masing-masing, cari `nominal_tagihan_jalur` yang cocok dengan `jalur_ppdb_id` milik pendaftaran ini.
3. **Kalau tidak ada satupun jenis_tagihan kategori ini yang punya nominal terkonfigurasi untuk jalur ini → tidak membuat baris `tagihan` sama sekali.** Absennya tagihan harus terlihat jelas sebagai "belum dikonfigurasi", bukan menyamar jadi "sudah lunas".
4. Kalau **sebagian** terkonfigurasi (misal 1 dari 3 jenis tagihan), tetap buat `tagihan` berisi hanya item yang terkonfigurasi — jangan dilewati total hanya karena tidak lengkap.
5. `total_tagihan` = jumlah semua item yang benar-benar dibuat. `status` otomatis `'lunas'` **hanya kalau** total ini genuinely 0 (semua item yang terkonfigurasi memang bernilai Rp 0 — kasus beasiswa/afirmasi asli, bukan kasus "belum diatur").
6. Idempoten per `(pendaftaran_id, kategori)` — pemanggilan berulang untuk kombinasi yang sama tidak membuat duplikat baris `tagihan`.

### 4.2 Titik Pemicu Otomatis

- **Tagihan pendaftaran** (`kategori='pendaftaran'`): dipanggil di titik akhir `ReviewSubmitController::submit()` (M2), tepat setelah `Pendaftaran` berhasil dibuat — tambahan aditif kecil seperti pola auto-link di Portal Akun Pendaftar, tidak mengubah satu pun baris kode M2 yang sudah ada.
- **Tagihan daftar ulang** (`kategori='daftar_ulang'`): dipanggil di `PendaftaranAdminController::tetapkanKeputusan()` (M3), hanya ketika `status` yang disimpan adalah `'diterima'` — tambahan aditif, tidak mengubah alur keputusan yang sudah ada (keputusan tetap tersimpan sukses baik tagihan berhasil dibuat maupun tidak).

### 4.3 Buat Tagihan Susulan (Manual)

Halaman detail pendaftaran (M3, sudah ada) mendapat panel baru menampilkan tagihan pendaftaran/daftar-ulang milik pendaftaran itu (kalau ada), plus tombol "Buat Tagihan Susulan" per kategori yang **belum** punya tagihan. Klik tombol menjalankan ULANG fungsi generate yang sama persis (bagian 4.1) untuk kategori tsb — memakai nominal yang berlaku SEKARANG (mungkin sudah diperbaiki admin sejak submit/keputusan awal terjadi).

**Idempotensi tombol ini wajib dijaga secara eksplisit** — klik ganda (misalnya karena koneksi lambat) tidak boleh membuat dua baris `tagihan` dengan `kategori` yang sama untuk `pendaftaran` yang sama. Tombol hanya muncul untuk kategori yang benar-benar belum punya tagihan; pengecekan ulang di server (bukan hanya di tampilan) wajib ada sebelum membuat baris baru.

## 5. Hak Akses

5 permission baru di bawah modul baru:
- `jenis-tagihan.view` / `.create` / `.edit` / `.delete`
- `tagihan.view`
- `tagihan.buat-susulan`

| Role | Permission |
|---|---|
| `admin_keuangan` | Semua 6 di atas — modul pertama yang benar-benar memberi peran ini fungsi nyata sejak di-seed di M0 |
| `kepala_sekolah` | `tagihan.view` saja (read-only, untuk meninjau status lunas/belum saat memutuskan, tanpa mengunci alur keputusan) |
| `yayasan_super_admin` | Semua, seperti biasa |

Setiap aksi diautorisasi independen (`$this->authorize('modul.aksi')`) — pola yang sama seperti seluruh modul lain di proyek ini, tidak ada gerbang gabungan.

## 6. Tampilan Admin

Mengikuti pola server-side-datatable yang sudah mapan (Roles page → PendaftaranAdminController), bukan desain baru:
- Halaman **Jenis Tagihan** (CRUD, per lembaga) — daftar jenis tagihan; halaman edit mengelola nominal per jalur sebagai sub-resource (matrix jalur × nominal), termasuk kategori dan bisa_dicicil/maks_cicilan.
- Halaman **Tagihan** (read-only) — datatable: daftar tagihan tergenerate, filter status/kategori, link ke pendaftaran terkait.
- Panel baru di halaman detail pendaftaran (M3) — tagihan milik pendaftaran ini + tombol "Buat Tagihan Susulan" (bagian 4.3).
- Sidebar: grup baru **"IV. Keuangan"**, grup "Akses & Peran" bergeser jadi "V." — domain baru, bukan sub-menu SPMB.

## 7. Rencana Pengujian

- **Master & nominal**: CRUD per lembaga (isolasi tenant), unique constraint `(jenis_tagihan_id, jalur_ppdb_id)` benar-benar dipaksakan (bukan cuma di level aplikasi).
- **Invoicing — normal**: submit M2 dengan nominal lengkap terkonfigurasi → tagihan pendaftaran + item + total benar; keputusan "diterima" M3 → tagihan daftar ulang otomatis.
- **Invoicing — gratis asli**: semua item kategori itu terkonfigurasi Rp 0 → tagihan tetap dibuat, `status='lunas'`.
- **Invoicing — tidak dikonfigurasi sama sekali (kritis)**: tidak ada tagihan dibuat sama sekali (`assertDatabaseMissing('tagihan', [...])` dan `assertDatabaseMissing('tagihan_item', [...])`), submit/keputusan tetap berhasil normal.
- **Invoicing — dikonfigurasi sebagian**: dari beberapa jenis_tagihan kategori itu, hanya sebagian punya nominal untuk jalur ini → tagihan tetap dibuat, hanya berisi item yang terkonfigurasi (bukan dilewati total).
- **Independensi dari M3**: `tetapkanKeputusan()` tetap berhasil normal termasuk tanpa tagihan sama sekali — bukti bahwa penambahan ini tidak mengganggu M3 yang sudah selesai.
- **Buat Tagihan Susulan**: berhasil membuat tagihan yang terlewat memakai nominal terkini; klik ganda/pemanggilan berulang tidak membuat duplikat (`assertCount(1, $pendaftaran->tagihan()->where('kategori', ...)->get())` atau setara).
- **Hak akses**: keenam permission diuji independen langsung ke controller (bukan hanya cek tombol tersembunyi di tampilan) — termasuk `kepala_sekolah` mendapat 403 saat mencoba `store`/`update`/`destroy` jenis tagihan, hanya bisa `view` tagihan.
- **Regresi penuh**: seluruh suite M2/M3/Portal Akun Pendaftar yang sudah ada tetap hijau setelah hook tambahan di `ReviewSubmitController`/`tetapkanKeputusan()`.

## 8. Non-Tujuan / Catatan untuk Sub-Project Berikutnya

- Sub-project 3 menambahkan `skema_cicilan`/`cicilan`/`pembayaran`/`bukti_transfer`, memakai `jenis_tagihan.bisa_dicicil`/`maks_cicilan` yang sudah dibangun di sini sebagai aturan main, dan menambahkan menu "Tagihan & Pembayaran" ke sidebar Portal Akun Pendaftar yang sudah punya struktur siap pakai.
- Modul penggajian guru (kalau/ketika dikerjakan) meniru pola (master + audit + verifikasi) dari modul ini, tapi lewat tabel terpisah — tidak berbagi `tagihan`/`tagihan_item` sama sekali.
