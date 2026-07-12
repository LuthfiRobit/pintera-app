# Design Doc — M0: Fondasi
**Tanggal:** 12 Juli 2026
**Status:** Disetujui untuk breakdown ke implementation plan
**Referensi:** `PRD_Sistem_Administrasi_Yayasan.md` (bagian 4, 11), khususnya bagian 10 yang sudah divalidasi

---

## 1. Tujuan & Scope

M0 adalah unit kerja pertama (lihat PRD bagian 11) yang jadi prasyarat semua modul lain: setup Laravel, autentikasi, RBAC dinamis, struktur multi-tenant, dan master data inti (yayasan, lembaga, guru, tahun ajaran/semester).

### Termasuk di M0
- Setup project Laravel + Laravel Breeze (Blade) untuk auth, Tailwind CSS, Alpine.js
- RBAC dinamis: role builder (bukan role hardcode), dengan atribut `scope_level` per role
- Infrastruktur multi-tenant: tabel `yayasan`, `lembaga`, trait `BelongsToTenant` + global scope, middleware `ResolveTenant`
- Master data: `tahun_ajaran`, `semester` (CRUD per lembaga, dengan aturan satu aktif)
- Data induk `guru` (PTK) lengkap sesuai Dapodik, termasuk riwayat pendidikan & sertifikasi
- Panel user management (Super Admin buat akun staff, assign role + lembaga)
- Layout admin dasar (Blade + Alpine + Tailwind) dengan lembaga switcher untuk role scope `yayasan`, termasuk dashboard placeholder per role (guru mendarat ke halaman "Dashboard Guru" kosong — konten sungguhan menyusul di fase Akademik)
- Audit trail (`spatie/laravel-activitylog`) untuk perubahan role/permission/assignment dan data master (yayasan/lembaga/guru)
- Enkripsi at-rest (Eloquent encrypted cast) untuk kolom sensitif: `nik` (guru), `nomor_rekening` (lembaga), `npwp` (lembaga), `npwp_yayasan` (yayasan)

### Tidak termasuk (menyusul di modul lain)
- Entitas SPMB & Keuangan (M1–M8)
- Akun Wali Murid/Murid (dibuat otomatis saat alur SPMB jalan)
- Notifikasi email, integrasi VA BRI
- Logic perhitungan beban kerja guru dari `jabatan_tambahan` (fase HRD)
- Enforcement `scope_level = diri_sendiri` secara konkret (baru didefinisikan sebagai kontrak/trait; penerapan nyata menyusul saat modul yang punya data relevan seperti `siswa`/`nilai` dibangun)

---

## 2. Keputusan Arsitektur Dasar

| Area | Keputusan |
|---|---|
| Frontend stack | Blade + Alpine.js saja, **tanpa Livewire** — form submit tradisional, Alpine.js untuk interaksi UI kecil (modal, dropdown, dsb) |
| Starter kit auth | **Laravel Breeze (Blade)** — dikustomisasi: halaman register publik **dinonaktifkan** untuk role internal |
| Pembuatan akun staff | **Dibuat oleh Super Admin/Admin via panel**, bukan self-register. Role & `lembaga_id` langsung ditetapkan saat pembuatan akun |
| Konvensi penamaan DB | **Bahasa Indonesia**, konsisten dengan istilah PRD (`lembaga`, `calon_murid`, `tagihan`, dst) |
| Resolusi tenant | Portal publik pakai **slug path** (`yayasan.id/{slug-lembaga}/spmb`); staff/admin internal pakai **session** (`lembaga_id` dari akun user) |
| Cakupan yayasan | **Satu yayasan per deployment** — tabel `yayasan` dibuat untuk kelengkapan relasional/legal, bukan lapisan tenant baru. Kalau ada yayasan lain di masa depan, mereka dapat instalasi/database terpisah |
| Audit trail | **`spatie/laravel-activitylog` dipasang di M0** — mencatat perubahan role/permission/assignment role-ke-user dan data master (`yayasan`, `lembaga`, `guru`): siapa, kapan, nilai sebelum/sesudah |
| Enkripsi data sensitif | **Eloquent encrypted cast di M0** untuk `nik` (guru), `nomor_rekening` & `npwp` (lembaga), `npwp_yayasan` (yayasan) |

---

## 3. RBAC Dinamis dengan Scope

### 3.1 Prinsip
Role **tidak** hardcode di kode. Super Admin Yayasan bisa membuat/mengedit/menghapus role bebas lewat panel ("Role Builder"), memilih kombinasi permission, dan menetapkan **scope_level**. Permission sendiri **tetap** didefinisikan di kode/seeder (kosakata tetap sesuai fitur yang dibangun) — yang dinamis adalah pengelompokan permission ke dalam role beserta scope-nya.

### 3.2 Skema Tabel
Extend tabel `roles` bawaan Spatie Permission:
- `scope_level` (enum): `yayasan` | `lembaga` | `diri_sendiri`
- `is_protected` (boolean, default `false`) — `true` khusus untuk role `yayasan_super_admin`

Tabel `permissions`, `model_has_roles`, `role_has_permissions` tetap standar Spatie.

### 3.2.1 Daftar Permission Awal (seeder)
- `manage-roles` — CRUD role lewat Role Builder (tunduk aturan `is_protected`)
- `manage-users` — CRUD akun staff (buat, edit, nonaktifkan, assign role & lembaga)
- `manage-yayasan` — edit profil yayasan
- `manage-lembaga` — CRUD lembaga + data periodik/layanan khusus/program inklusi/ekstrakurikuler
- `manage-tahun-ajaran` — CRUD tahun ajaran & semester, termasuk aktivasi
- `manage-guru` — CRUD data guru + riwayat pendidikan + sertifikasi + jabatan tambahan
- `view-audit-log` — lihat riwayat perubahan (activitylog)

Permission tambahan akan ditambahkan seiring modul baru (SPMB, Keuangan, dst) dibangun — daftar ini hanya cakupan M0.

### 3.3 Seed Awal (bisa diedit via panel, kecuali yang protected)

| Role | scope_level | is_protected |
|---|---|---|
| `yayasan_super_admin` | yayasan | ✅ true — tidak bisa dihapus, `scope_level` terkunci |
| `kepala_sekolah` | lembaga | false |
| `admin_administrasi` | lembaga | false |
| `admin_keuangan` | lembaga | false |
| `guru` | diri_sendiri | false |

### 3.4 Panel Role Builder
Super Admin Yayasan bisa:
- Membuat role baru: nama + pilih permission dari daftar yang tersedia + set `scope_level`
- Mengedit role non-protected (termasuk ubah `scope_level`)
- Menghapus role non-protected — dengan pengecekan tidak ada user aktif yang masih memakainya (atau wajib reassign dulu)
- Role dengan `is_protected = true` tidak bisa dihapus; `scope_level`-nya tidak bisa diubah, tapi daftar permission-nya tetap bisa disesuaikan

### 3.5 Resolusi Multi-Role (Union Scope)
Kalau satu user punya lebih dari satu role dengan `scope_level` berbeda, sistem memakai **scope terluas** di antara semua role aktifnya: `yayasan` > `lembaga` > `diri_sendiri`.

### 3.6 Enforcement `scope_level = diri_sendiri`
Level ini **tidak** memengaruhi `TenantScope` (lihat bagian 4). Didefinisikan sebagai kontrak/trait `BelongsToOwner` (dengan method `scopeOwnedByCurrentUser()`) yang wajib diimplementasikan tiap model yang butuh pembatasan ke data milik sendiri — namun penerapan konkretnya menyusul di modul yang punya data relevan (mis. `siswa`, `nilai` di fase Akademik). Di M0 belum ada tabel yang wajib memakainya.

---

## 4. Multi-Tenant

### 4.1 Middleware `ResolveTenant`
- **Request terautentikasi**: `lembaga_id` diambil dari akun user yang login. Kalau salah satu role user ber-`scope_level = yayasan`, tidak ada pembatasan `lembaga_id` secara default (mode lihat-semua); user bisa fokus ke satu lembaga lewat switcher UI, disimpan di `session('active_lembaga_id')` — ini murni filter tampilan, bukan pembatas keamanan.
- **Request publik** (portal SPMB, menyusul M2): `lembaga_id` diresolusi langsung dari slug di URL (`/{slug}/spmb`), tidak lewat session.

### 4.2 Trait `BelongsToTenant` + Global Scope `TenantScope`
Mengambil scope_level terluas dari semua role aktif user:
- `yayasan` → skip filter `lembaga_id` (kecuali sedang fokus manual ke satu lembaga via switcher)
- `lembaga` → otomatis `where('lembaga_id', $user->lembaga_id)`
- `diri_sendiri` → tidak memengaruhi `TenantScope` (lihat 3.6)

Model yang memakai trait ini di M0: `users` (staff), `tahun_ajaran`, `semester`, `guru`. Tabel `lembaga` dan `yayasan` **tidak** memakai trait ini (mereka tabel tenant, bukan tenant-scoped).

---

## 5. Data Model

### 5.1 `yayasan`
Satu baris per deployment. Field: `nama`, `npwp_yayasan`, `akta_pendirian_nomor`, `akta_pendirian_tanggal`, `sk_kemenkumham_nomor`, `alamat`, `telepon`, `email`, `website`, `logo`, `nama_ketua_pembina`, `nama_ketua_pengurus`.

### 5.2 `lembaga` (Satuan Pendidikan)
`yayasan_id` (FK, relasional saja — bukan filter tenant baru).

**Identitas:** `npsn` (unik), `nss` (nullable), `nama`, `slug` (path publik SPMB), `bentuk_pendidikan` (KB/TPA/SPS/TK/SD/SMP/SMA/SMK/SLB), `status_sekolah` (negeri/swasta), `status_kepemilikan`, `naungan` (kemendikdasmen/kemenag), `sk_pendirian_nomor`, `sk_pendirian_tanggal`, `sk_izin_operasional_nomor`, `sk_izin_operasional_tanggal`, `akreditasi` (A/B/C/belum), `sk_akreditasi_nomor`, `tanggal_sk_akreditasi`, `nama_kepala_sekolah`, `nama_bendahara_bosp`

**Lokasi:** `alamat_jalan`, `rt`, `rw`, `nama_dusun`, `desa_kelurahan`, `kecamatan`, `kabupaten_kota`, `provinsi`, `kode_pos`, `lintang`, `bujur`

**Kontak:** `telepon`, `fax`, `email`, `website`

**Bank** (untuk rekonsiliasi Keuangan di modul mendatang): `nama_bank`, `cabang_kcp_unit`, `rekening_atas_nama`, `nomor_rekening`

**Administrasi:** `mbs` (bool), `nama_wajib_pajak`, `npwp`, `memungut_iuran` (bool), `nominal_iuran`, `periode_iuran` (bulanan/tahunan), `status_aktif`

### 5.3 Tabel terpisah milik `lembaga` (periodik/repeatable, mengikuti struktur Dapodik)
- **`lembaga_data_periodik`** (per `semester_id`): `waktu_penyelenggaraan`, `sumber_listrik`, `daya_listrik`, `akses_internet`, `status_bos`, `sertifikasi_iso`, `ketersediaan_air_bersih`, `kecukupan_air_bersih`, `jumlah_tempat_cuci_tangan`, `jumlah_jamban`, `stratifikasi_uks`, `media_kie_sanitasi`
- **`layanan_khusus_lembaga`**: `jenis_layanan`, `no_sk`, `tmt`, `tst`, `keterangan`
- **`program_inklusi_lembaga`**: `kebutuhan_khusus`, `no_sk`, `tanggal_sk`, `tmt`, `tst`, `keterangan`
- **`ekstrakurikuler_lembaga`**: `jenis_ekskul`, `nama_ekskul`, `no_sk`, `tanggal_sk`, `jam_per_minggu`

### 5.4 `users`
Field Breeze standar + `lembaga_id` (FK, nullable — hanya null untuk user yang seluruh role-nya `scope_level = yayasan`)

### 5.5 `guru` (PTK)
`user_id` (FK), `lembaga_id` (FK)

**Identitas:** `nik` (16 digit, unik), `nuptk` (nullable, unik), `nip` (nullable), `nama`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `kewarganegaraan`

**Alamat:** `alamat_jalan`, `rt`, `rw`, `desa_kelurahan`, `kecamatan`, `kabupaten_kota`, `provinsi`, `kode_pos`

**Kontak:** `no_hp`, `email`

**Kepegawaian:** `jenis_ptk` (guru_kelas/guru_mapel/kepala_sekolah/tenaga_administrasi), `status_kepegawaian` (PNS/PPPK/GTY/PTY/Honorer), `golongan_pangkat` (nullable), `tmt_tugas`, `tmt_pns` (nullable), `status_aktif` (aktif/non_aktif/mutasi/pensiun)

> Catatan: satu baris `guru` = satu penugasan di satu `lembaga_id` (konsisten dengan asumsi PRD bahwa satu user terikat satu lembaga). Penugasan guru di lebih dari satu lembaga sekaligus di luar cakupan M0.

### 5.6 Riwayat guru (one-to-many, sesuai struktur Dapodik yang mencatat berulang)
- **`riwayat_pendidikan_guru`**: `guru_id`, `jenjang_pendidikan`, `gelar_akademik`, `sekolah_formal`, `fakultas`, `bidang_studi`, `kependidikan` (bool), `tahun_masuk`, `tahun_lulus`
- **`sertifikasi_guru`**: `guru_id`, `jenis_sertifikasi`, `nomor_sertifikat`, `bidang_studi_sertifikasi`, `nrg`, `tahun_sertifikasi`, `kode_lembaga_sertifikasi`

### 5.7 `jabatan_tambahan_master` + `guru_jabatan_tambahan`
Sesuai PRD bagian 3.1 (Permendikdasmen 11/2025 & Kepmendikdasmen 221/P/2025):
- **`jabatan_tambahan_master`** (referensi global, tidak per lembaga): `nama` (Wakil Kepsek Kurikulum/Kesiswaan/Sarpras/Humas, Kepala Perpustakaan, Kepala Laboratorium, Kepala Program Keahlian, Koordinator BK, Wali Kelas, Guru Wali, Pembina OSIS, Pembina Ekstrakurikuler, Koordinator Pengembangan Kompetensi, Koordinator P5, Koordinator/anggota TPPK, GPK/Pembimbing Khusus), `kelompok` (struktural/fungsional)
- **`guru_jabatan_tambahan`** (pivot): `guru_id`, `jabatan_tambahan_master_id`, `mulai_periode`, `akhir_periode`, `no_sk`

### 5.8 `tahun_ajaran` + `semester`
- **`tahun_ajaran`**: `lembaga_id`, `nama` ("2026/2027"), `tanggal_mulai`, `tanggal_selesai`, `status_aktif`
- **`semester`** (child dari `tahun_ajaran`, meniru struktur Dapodik): `tahun_ajaran_id`, `nama` (Ganjil/Genap), `urutan` (1/2), `kode_dapodik` (format `20261`/`20262`, untuk kompatibilitas ekspor Dapodik), `tanggal_mulai`, `tanggal_selesai`, `status_aktif`

**Aturan bisnis:** hanya satu `semester` boleh `status_aktif` per `lembaga` (lintas semua tahun ajaran), dan semester aktif itu harus berada dalam `tahun_ajaran` yang juga aktif — ditegakkan lewat `DB::transaction` saat aktivasi.

---

## 6. Testing Plan

- Global scope: user lembaga A tidak bisa lihat data lembaga B lewat query Eloquent biasa
- Union scope: user dengan role ganda (lembaga + yayasan) bisa lihat lintas lembaga
- Authorization langsung by ID: staff akses resource lembaga lain via URL/ID → ditolak (policy/route model binding, bukan cuma andalkan global scope)
- Aktivasi `tahun_ajaran`/`semester`: mengaktifkan yang baru menonaktifkan yang lama secara atomic, dan semester aktif harus ikut tahun ajaran aktif
- Proteksi role: percobaan hapus/ubah `scope_level` role `is_protected = true` harus ditolak
- Role builder: role baru dengan kombinasi permission & scope_level custom berfungsi sesuai enforcement di atas
- Pembuatan akun staff hanya bisa lewat panel admin (halaman register publik tidak dapat diakses/dinonaktifkan)
- Audit log: perubahan role/permission/assignment dan data master (yayasan/lembaga/guru) tercatat dengan aktor, waktu, dan nilai sebelum/sesudah
- Enkripsi: kolom `nik`, `nomor_rekening`, `npwp`, `npwp_yayasan` tersimpan terenkripsi di database (bukan plaintext) dan tetap bisa dibaca kembali lewat model (round-trip test)

---

## 7. Referensi Eksternal
Struktur field `lembaga` dan `guru` disusun berdasarkan dokumentasi resmi Helpdesk Dapodik:
- [Data Rinci Satuan Pendidikan (1)](https://helpdesk.pauddasmen.id/help/en-us/19-data-satuan-pendidikan/49-data-rinci-satuan-pendidikan-1)
- [Data Rinci Satuan Pendidikan (3)](https://helpdesk.pauddasmen.id/help/en-us/19-data-satuan-pendidikan/65-data-rinci-satuan-pendidikan-3)
- [Profil Satuan Pendidikan](https://helpdesk.pauddasmen.id/help/en-us/19-data-satuan-pendidikan/45-profil-satuan-pendidikan)
- [Data Sekolah](https://helpdesk.pauddasmen.id/help/en-us/21-data-pokok/51-data-sekolah)
- [Data PTK](https://helpdesk.pauddasmen.id/help/en-us/21-data-pokok/52-data-ptk)
- [Data Rinci PTK](https://helpdesk.pauddasmen.id/help/en-us/16-data-gtk/68-data-rinci-ptk)

Serta Permendikdasmen No. 11 Tahun 2025 & Kepmendikdasmen 221/P/2025 (jabatan tugas tambahan guru, lihat PRD bagian 3.1).
