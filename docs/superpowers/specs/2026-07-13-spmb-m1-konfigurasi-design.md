# Design Doc — M1: SPMB — Konfigurasi
**Tanggal:** 13 Juli 2026
**Status:** Disetujui untuk breakdown ke implementation plan
**Referensi:** `PRD_Sistem_Administrasi_Yayasan.md` (bagian 5, 11), `docs/superpowers/specs/2026-07-12-m0-fondasi-design.md`

---

## 1. Tujuan & Scope

M1 adalah unit kerja pertama dari Modul SPMB (lihat PRD bagian 11), menyusul fondasi M0. Fokusnya murni **konfigurasi** — panel admin untuk menyiapkan seluruh aturan main PPDB sebuah lembaga sebelum portal publik (M2) dan alur verifikasi (M3) dibangun. Tidak ada entitas transaksional (`calon_murid`, `pendaftaran`, `hasil_seleksi`) di modul ini.

### Termasuk di M1
- CRUD **Gelombang PPDB** (per lembaga + tahun ajaran): nama, tanggal buka/tutup, kuota
- CRUD **Jalur PPDB** (per lembaga + tahun ajaran): nama, deskripsi, status aktif
- CRUD **Formulir Field** (anak Jalur): field tambahan/kustom formulir pendaftaran
- CRUD **Dokumen Syarat** (anak Jalur): daftar dokumen wajib/opsional per jalur
- CRUD **Seleksi/Tes** (anak Jalur × Gelombang): jadwal tes, kriteria kelulusan, bobot
- Master data **Jenis Tes** (per lembaga, lintas tahun ajaran — tidak didup­likasi)
- Fitur **duplikasi konfigurasi** dari tahun ajaran sebelumnya (Gelombang, Jalur, dan seluruh turunannya)
- Permission baru `manage-ppdb`, default diberikan ke role `admin_administrasi`

### Tidak termasuk (menyusul di modul lain)
- Portal publik pendaftaran (M2) — field wajib standar Dapodik akan di-hardcode di template form M2, **bukan** baris dinamis di `formulir_field`
- Verifikasi dokumen & keputusan diterima/ditolak (M3)
- Entitas `calon_murid`, `pendaftaran`, `dokumen_pendaftaran`, `hasil_seleksi`
- Modul Keuangan (M4–M7)
- Mekanisme "lock"/snapshot konfigurasi ke tiap `pendaftaran` — tidak relevan karena pendekatan yang dipilih (lihat bagian 2) sudah menghindari masalah ini di level skema

---

## 2. Keputusan Arsitektur

### 2.1 Snapshot per tahun ajaran, bukan master lintas tahun

Dipertimbangkan dua pendekatan untuk `gelombang_ppdb` dan `jalur_ppdb`:

- **A. Snapshot per tahun ajaran + tombol duplikasi** (dipilih)
- **B. Master data lintas tahun ajaran** (ditolak)

**Alasan menolak B:** `jalur_ppdb` akan dirujuk oleh `pendaftaran` (M2). Jika `jalur_ppdb` adalah master permanen yang bisa diedit admin kapan saja, perubahan formulir/dokumen syarat di masa depan akan **retroaktif mengubah tampilan data pendaftaran lama** yang merujuk baris master yang sama — bertentangan dengan prinsip *auditability* yang ditekankan PRD (bagian 8). Pendekatan B juga menambah kompleksitas skema (perlu split `gelombang_ppdb_master` + `gelombang_ppdb_periode`) tanpa manfaat yang sepadan.

**Keputusan:** `gelombang_ppdb` dan `jalur_ppdb` tetap terikat `tahun_ajaran_id`. Setiap tahun ajaran punya salinan konfigurasi sendiri yang independen — aman diaudit, dan admin bebas mengedit konfigurasi tahun berjalan tanpa risiko mengubah riwayat tahun lalu.

**Pengecualian:** `jenis_tes_master` **tidak** ikut aturan ini — ia murni kosakata referensi (nama jenis tes: "Tes Tulis", "Wawancara") yang tidak membawa data spesifik-pendaftaran, sehingga aman dipakai ulang lintas tahun tanpa risiko retroaktif. Ia di-scope per **lembaga** saja.

### 2.2 Fitur Duplikasi Konfigurasi

Tombol **"Salin dari Tahun Ajaran Sebelumnya"** muncul di halaman index Gelombang PPDB dan Jalur PPDB sebagai *empty-state callout* (lihat bagian 4), aktif hanya jika:
1. Tahun ajaran yang sedang dilihat **belum punya** Gelombang maupun Jalur sama sekali, dan
2. Ada tahun ajaran lain di lembaga yang sama yang **punya** data untuk disalin (dipilih tahun ajaran ber-`tanggal_mulai` terbaru sebelum tahun ajaran tujuan).

Proses (satu `DB::transaction`):
1. Salin semua `gelombang_ppdb` → tahun ajaran baru. `kuota` disalin apa adanya; `tanggal_buka`/`tanggal_tutup` digeser **+1 tahun** sebagai draft (admin wajib meninjau sebelum gelombang dibuka). Simpan mapping `id_lama → id_baru`.
2. Salin semua `jalur_ppdb` (nama, deskripsi, `status_aktif`) → tahun ajaran baru. Simpan mapping `id_lama → id_baru`.
3. Untuk tiap pasangan jalur lama→baru: salin `formulir_field` dan `dokumen_syarat_ppdb` miliknya apa adanya (field ini tidak mengandung referensi ke gelombang/tahun ajaran, jadi copy langsung).
4. Salin `seleksi_ppdb` milik tiap jalur lama → jalur baru, dengan `gelombang_ppdb_id` dipetakan lewat mapping langkah 1. `jenis_tes_master_id` **tidak diubah** (tetap merujuk master yang sama/persisten).
5. Dicegah dijalankan dua kali ke tahun ajaran tujuan yang sama (guard: tahun ajaran tujuan harus masih kosong — precondition di langkah awal).
6. Dicatat di activity log: siapa memicu, dari tahun ajaran mana ke mana, jumlah baris tersalin per tabel.

---

## 3. Skema Data

Semua migration baru menambah tabel berikut (nama kolom mengikuti konvensi Bahasa Indonesia yang sudah dipakai di M0):

### `jenis_tes_master`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `lembaga_id` | FK → `lembaga` | scope, **bukan** tenant trait biasa karena tidak per tahun ajaran — tetap pakai `BelongsToTenant` (trait hanya bergantung ke `lembaga_id`, tidak ke tahun ajaran) |
| `nama` | string | mis. "Tes Tulis", "Wawancara" |
| `deskripsi` | text, nullable | |
| timestamps | | |

### `gelombang_ppdb`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `lembaga_id` | FK → `lembaga` | via `BelongsToTenant` |
| `tahun_ajaran_id` | FK → `tahun_ajaran` | |
| `nama` | string | mis. "Gelombang 1" |
| `tanggal_buka` | date | |
| `tanggal_tutup` | date | harus > `tanggal_buka` |
| `kuota` | unsigned int | |
| timestamps | | |

### `jalur_ppdb`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `lembaga_id` | FK → `lembaga` | via `BelongsToTenant` |
| `tahun_ajaran_id` | FK → `tahun_ajaran` | |
| `nama` | string | mis. "Reguler", "Prestasi", "Afirmasi" |
| `deskripsi` | text, nullable | |
| `status_aktif` | boolean, default `true` | nonaktifkan tanpa hapus |
| timestamps | | |

### `formulir_field`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `jalur_ppdb_id` | FK → `jalur_ppdb`, cascade delete | |
| `label` | string | |
| `field_type` | enum: `text,textarea,number,date,select,file` | |
| `options` | json, nullable | daftar pilihan, wajib diisi (≥2) jika `field_type = select` |
| `is_required` | boolean, default `false` | |
| `urutan` | unsigned int | urutan tampil di form |
| timestamps | | |

### `dokumen_syarat_ppdb`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `jalur_ppdb_id` | FK → `jalur_ppdb`, cascade delete | |
| `nama_dokumen` | string | mis. "Akta Kelahiran", "Sertifikat Hafalan" |
| `wajib` | boolean, default `true` | |
| `urutan` | unsigned int | |
| timestamps | | |

### `seleksi_ppdb`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `jalur_ppdb_id` | FK → `jalur_ppdb`, cascade delete | |
| `gelombang_ppdb_id` | FK → `gelombang_ppdb`, cascade delete | |
| `jenis_tes_master_id` | FK → `jenis_tes_master` | |
| `jadwal` | datetime | |
| `kriteria_kelulusan` | text, nullable | |
| `bobot` | decimal(5,2), nullable | |
| timestamps | | |

**Catatan relasi:** `gelombang_ppdb` dan `jalur_ppdb` adalah entitas **sejajar** (bukan parent-child) — keduanya independen di bawah `lembaga_id` + `tahun_ajaran_id`. `seleksi_ppdb` adalah satu-satunya tabel yang menjembatani keduanya secara eksplisit.

---

## 4. Struktur UI & Navigasi

### 4.1 Sidebar
Grup baru **"III. SPMB"** disisipkan antara "Data Induk" dan "Akses & Peran" (bergeser jadi "IV"):
- **Gelombang PPDB** (ikon `waves`)
- **Jalur PPDB** (ikon `signpost`)
- **Jenis Tes** (ikon `quiz`)

### 4.2 Gelombang PPDB
CRUD standar (index/create/edit) mengikuti pola `Lembaga`/`Guru` — tabel + form terpisah. Di-scope ke tahun ajaran aktif lembaga, dengan indikator jelas tahun ajaran mana yang sedang ditampilkan.

### 4.3 Jalur PPDB — halaman detail sebagai "dossier"
Index + create ringan (nama, deskripsi) mengikuti pola CRUD standar. Halaman **edit/detail** jadi pusat konfigurasi satu jalur, mengikuti pola nested Tahun Ajaran + Semester yang sudah ada di M0:

- **Indikator kelengkapan** di header: chip per seksi (Formulir, Dokumen, Seleksi) — abu-abu jika kosong, brass jika terisi. Menjawab pertanyaan admin "jalur ini sudah siap dibuka atau belum?"
- **Seksi Formulir Field** — daftar field + form tambah cepat (label, tipe, wajib/opsional, opsi jika `select`)
- **Seksi Dokumen Syarat** — daftar dokumen + form tambah cepat (nama, wajib/opsional)
- **Seksi Seleksi & Tes** — daftar jadwal tes + form tambah cepat (pilih Gelombang, pilih Jenis Tes dari master, jadwal, kriteria, bobot)

### 4.4 Jenis Tes
Halaman ringan bergaya pengelola daftar istilah (mirip checkbox permission di Role Builder) — bukan tabel penuh karena datanya tipis (nama + deskripsi).

### 4.5 Empty state duplikasi
Saat index Gelombang atau Jalur kosong dan tahun ajaran sebelumnya punya data, tampilkan callout brass-tinted (bukan tabel kosong polos):
> "Belum ada konfigurasi SPMB untuk {tahun ajaran ini}." + tombol **"Salin dari {tahun ajaran sebelumnya}"**

### 4.6 Bahasa aksi
Konsisten dengan pola yang sudah ada: nama tombol dan pesan status sinkron persis (mis. tombol "Salin dari Tahun Ajaran Sebelumnya" → status "Konfigurasi berhasil disalin dari {tahun}").

---

## 5. Otorisasi

Permission baru: **`manage-ppdb`**, satu permission menaungi keenam tabel (mengikuti pola `manage-tahun-ajaran` yang menaungi Tahun Ajaran + Semester sekaligus).

| Role | Akses |
|---|---|
| `yayasan_super_admin` | penuh (tersinkron otomatis via seeder, sesuai pola M0) |
| `admin_administrasi` | penuh — diberikan default di seeder (role ini sebelumnya belum punya permission apa pun) |
| `kepala_sekolah`, `admin_keuangan`, `guru` | tidak ada akses |

---

## 6. Validasi & Aturan Bisnis

- **Guard cross-tenant/cross-tahun-ajaran**: semua field relasi (`tahun_ajaran_id` di form Gelombang/Jalur, `gelombang_ppdb_id` & `jenis_tes_master_id` di form Seleksi) divalidasi dengan `Rule::exists(...)->where(...)` yang mengunci ke `lembaga_id` (dan `tahun_ajaran_id` jika relevan) milik acting user — **bukan** `exists:table,id` polos, karena validasi `exists` bawaan tidak menghormati Eloquent global scope.
- **Duplikasi**: ditolak jika tahun ajaran tujuan sudah punya minimal satu Gelombang atau Jalur.
- **Tanggal Gelombang**: `tanggal_tutup` harus setelah `tanggal_buka`.
- **Field formulir `select`**: `options` wajib diisi minimal 2 pilihan.
- Operasi duplikasi dibungkus `DB::transaction` (menyentuh banyak tabel sekaligus), mengikuti pola `TahunAjaran::activate()`.
- Semua perubahan pada `jalur_ppdb`, `gelombang_ppdb`, dan aksi duplikasi dicatat via `spatie/laravel-activitylog`, mengikuti pola Lembaga/Guru di M0.

---

## 7. Rencana Pengujian

Mengikuti pola `tests/Feature` yang sudah ada:
- **Cross-tenant isolation**: admin lembaga A tidak bisa lihat/edit/duplikasi Gelombang/Jalur/Jenis Tes milik lembaga B.
- **Scope permission**: user tanpa `manage-ppdb` mendapat 403 di semua rute baru.
- **Aturan bisnis**: `tanggal_tutup` sebelum `tanggal_buka` ditolak; `field_type = select` tanpa opsi ditolak; duplikasi ke tahun ajaran yang sudah ada datanya ditolak.
- **Duplikasi end-to-end**: satu test yang memverifikasi seluruh rantai tersalin dengan benar (gelombang → jalur → formulir/dokumen/seleksi, termasuk mapping id gelombang baru pada seleksi yang tersalin, dan `jenis_tes_master_id` tetap merujuk baris yang sama).

---

## 8. Ringkasan Keputusan (untuk referensi cepat)

1. Snapshot per tahun ajaran + tombol duplikasi (bukan master lintas tahun) — untuk `gelombang_ppdb` dan `jalur_ppdb`.
2. `jenis_tes_master` adalah pengecualian: master per lembaga, lintas tahun ajaran, tidak ikut proses duplikasi (otomatis ke-reuse via referensi).
3. `formulir_field` hanya menyimpan field **tambahan** — field wajib Dapodik di-hardcode nanti di M2, bukan baris dinamis.
4. `gelombang_ppdb` dan `jalur_ppdb` adalah entitas sejajar, dijembatani lewat `seleksi_ppdb`.
5. Satu permission `manage-ppdb` untuk seluruh modul, default diberikan ke `admin_administrasi`.
6. Duplikasi hanya aktif jika tahun ajaran tujuan benar-benar kosong, dan dijalankan dalam satu transaksi dengan pencatatan activity log.
