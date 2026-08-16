# Spec: Modul Master Sarpras, Ruangan, Kategori, dan Aset Inventaris

## 1. Ringkasan & Tujuan
Modul Master Sarana dan Prasarana (Sarpras) bertujuan menyediakan pengelolaan terpadu dan terstruktur untuk:
1. **Hierarki Fasilitas Fisik:** Pencatatan Gedung dan Ruangan secara multi-tenant (mendukung kepemilikan unit Lembaga maupun fasilitas bersama milik Yayasan).
2. **Integrasi KBM & Akademik:** Mengaitkan Ruangan ke Kelas (*Home Room*) dan Jadwal Pelajaran (*Room Slot*) dengan deteksi bentrok ruangan (*Room Clash Detection*).
3. **Manajemen Aset & Inventaris:** Pencatatan inventaris barang dengan metode hybrid (unit barcode unik vs batch kuantitas) beserta riwayat mutasi perpindahan lokasi barang antar-ruangan.
4. **Dasar Pengadaan & LPJ:** Menjadi fondasi data master untuk alur pengadaan barang (*Purchase Requisition*) dan auto-registrasi inventaris saat LPJ disetujui.

---

## 2. Scope & Batasan

### In-Scope:
- **Master Gedung:** CRUD data gedung, jumlah lantai, deskripsi, status aktif (Scope Yayasan & Lembaga).
- **Master Ruangan:** CRUD data ruangan, lantai, jenis ruangan, kapasitas, luas m², penanggung jawab, status *shared facility* (dapat dipinjam lintas unit).
- **Integrasi Akademik:** Relasi `ruangan_id` pada tabel `kelas` (Home Room) dan tabel `jadwal_pelajaran` (Slot Ruangan KBM) beserta validasi anti-bentrok.
- **Master Kategori Aset:** CRUD kategori barang (Elektronik & IT, Mebel, Alat KBM, Kendaraan, ATK).
- **Master Aset / Inventaris Barang:** CRUD barang inventaris, kode inventaris/barcode unik, merk, tipe pencatatan (`unit` vs `batch`), kondisi barang, tanggal & harga perolehan, foto barang.
- **Mutasi Lokasi Aset:** Form pemindahan aset antar-ruangan beserta pencatatan riwayat log perpindahan (`riwayat_mutasi_aset`).
- **Kartu Inventaris Ruangan (KIR):** Tampilan dan export PDF rekapitulasi daftar aset yang berada di dalam suatu ruangan.
- **RBAC Multi-Scope:** Otorisasi dinamis untuk Scope Yayasan (`sarpras_yayasan`), Scope Lembaga (`admin_sarpras`), serta Guru/Staf (*View Only*).

### Out-of-Scope (Fase Berikutnya):
- Alur Pengajuan Anggaran & LPJ Pengadaan (*Purchase Requisition & Settlement Workflow*) — akan dibangun di modul Pengadaan setelah data master ini tuntas.
- Peminjaman Ruangan Insidental Publik/Siswa.
- Depresiasi/Penyusutan Nilai Aset Finansial Otomatis (Akuntansi Aktiva Tetap).

---

## 3. Aktor & Matriks Hak Akses (RBAC)

### A. Permission Names
- `sarpras.gedung.view`, `sarpras.gedung.manage`
- `sarpras.ruangan.view`, `sarpras.ruangan.manage`
- `sarpras.kategori.view`, `sarpras.kategori.manage`
- `sarpras.aset.view`, `sarpras.aset.manage`
- `sarpras.mutasi.create`, `sarpras.mutasi.view`
- `sarpras.kir.export`

### B. Matriks Akses Per Role
| Role | Scope | Hak Gedung & Ruang | Hak Kategori & Aset | Mutasi Aset | Cetak KIR |
|---|---|---|---|---|---|
| `sarpras_yayasan` / `superadmin` | Yayasan | Full CRUD (Semua Unit + Yayasan) | Full CRUD (Semua Unit + Yayasan) | Full CRUD | Ya |
| `viewer_yayasan` | Yayasan | View Only (Semua Unit) | View Only (Semua Unit) | View Only | Ya |
| `admin_sarpras` / `admin_lembaga`| Lembaga | Full CRUD (Unit Sendiri) + View Shared | Full CRUD (Unit Sendiri) | Buat Mutasi | Ya |
| `guru` / `staf` | Lembaga | View Only (Cek Jadwal & Ruang) | View Only (Katalog Fasilitas) | – | View Only |

---

## 4. Struktur Database & Schema

### A. Tabel `gedung`
```sql
CREATE TABLE gedung (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    yayasan_id BIGINT UNSIGNED NOT NULL,
    lembaga_id BIGINT UNSIGNED NULL, -- NULL = Fasilitas Bersama / Milik Yayasan
    kode_gedung VARCHAR(50) NOT NULL,
    nama_gedung VARCHAR(255) NOT NULL,
    jumlah_lantai INT UNSIGNED NOT NULL DEFAULT 1,
    deskripsi TEXT NULL,
    is_aktif BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (yayasan_id) REFERENCES yayasan(id) ON DELETE CASCADE,
    FOREIGN KEY (lembaga_id) REFERENCES lembaga(id) ON DELETE CASCADE,
    UNIQUE KEY uq_gedung_lembaga_kode (lembaga_id, kode_gedung)
);
```

### B. Tabel `ruangan`
```sql
CREATE TABLE ruangan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    yayasan_id BIGINT UNSIGNED NOT NULL,
    lembaga_id BIGINT UNSIGNED NULL, -- NULL = Fasilitas Bersama / Shared Campus
    gedung_id BIGINT UNSIGNED NOT NULL,
    kode_ruangan VARCHAR(50) NOT NULL,
    nama_ruangan VARCHAR(255) NOT NULL,
    lantai INT NOT NULL DEFAULT 1,
    jenis_ruangan ENUM(
        'kelas_teori', 'laboratorium', 'perpustakaan', 
        'kantor_guru', 'aula', 'ibadah', 'olahraga', 
        'toilet', 'gudang', 'lainnya'
    ) NOT NULL DEFAULT 'kelas_teori',
    kapasitas_siswa INT UNSIGNED NULL,
    luas_m2 DECIMAL(8, 2) NULL,
    penanggung_jawab_guru_id BIGINT UNSIGNED NULL,
    is_shared BOOLEAN NOT NULL DEFAULT FALSE,
    is_aktif BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (yayasan_id) REFERENCES yayasan(id) ON DELETE CASCADE,
    FOREIGN KEY (lembaga_id) REFERENCES lembaga(id) ON DELETE CASCADE,
    FOREIGN KEY (gedung_id) REFERENCES gedung(id) ON DELETE CASCADE,
    FOREIGN KEY (penanggung_jawab_guru_id) REFERENCES guru(id) ON DELETE SET NULL,
    UNIQUE KEY uq_ruangan_lembaga_kode (lembaga_id, kode_ruangan)
);
```

### C. Alterasi Modul Akademik
1. **Tabel `kelas`:**
   - Tambah kolom `ruangan_id BIGINT UNSIGNED NULL` (FK ke `ruangan.id`, `ON DELETE SET NULL`) sebagai *Home Room*.
2. **Tabel `jadwal_pelajaran`:**
   - Tambah kolom `ruangan_id BIGINT UNSIGNED NULL` (FK ke `ruangan.id`, `ON DELETE SET NULL`) sebagai ruangan spesifik jam mengajar.

### D. Tabel `kategori_aset`
```sql
CREATE TABLE kategori_aset (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    yayasan_id BIGINT UNSIGNED NOT NULL,
    lembaga_id BIGINT UNSIGNED NULL,
    kode_kategori VARCHAR(50) NOT NULL,
    nama_kategori VARCHAR(255) NOT NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (yayasan_id) REFERENCES yayasan(id) ON DELETE CASCADE,
    FOREIGN KEY (lembaga_id) REFERENCES lembaga(id) ON DELETE CASCADE
);
```

### E. Tabel `aset_barang`
```sql
CREATE TABLE aset_barang (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    yayasan_id BIGINT UNSIGNED NOT NULL,
    lembaga_id BIGINT UNSIGNED NULL,
    kategori_aset_id BIGINT UNSIGNED NOT NULL,
    ruangan_id BIGINT UNSIGNED NOT NULL,
    kode_inventaris VARCHAR(100) NOT NULL,
    nama_barang VARCHAR(255) NOT NULL,
    merk VARCHAR(255) NULL,
    spesifikasi TEXT NULL,
    tipe_pencatatan ENUM('unit', 'batch') NOT NULL DEFAULT 'unit',
    qty INT UNSIGNED NOT NULL DEFAULT 1,
    satuan VARCHAR(50) NOT NULL DEFAULT 'unit',
    kondisi ENUM('baik', 'rusak_ringan', 'rusak_berat', 'hilang') NOT NULL DEFAULT 'baik',
    sumber_perolehan ENUM('beli_yayasan', 'beli_lembaga', 'hibah', 'bantuan_pemerintah') NOT NULL DEFAULT 'beli_lembaga',
    tanggal_perolehan DATE NULL,
    harga_perolehan DECIMAL(15, 2) NULL,
    foto_barang_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (yayasan_id) REFERENCES yayasan(id) ON DELETE CASCADE,
    FOREIGN KEY (lembaga_id) REFERENCES lembaga(id) ON DELETE CASCADE,
    FOREIGN KEY (kategori_aset_id) REFERENCES kategori_aset(id) ON DELETE RESTRICT,
    FOREIGN KEY (ruangan_id) REFERENCES ruangan(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_aset_kode (lembaga_id, kode_inventaris)
);
```

### F. Tabel `riwayat_mutasi_aset`
```sql
CREATE TABLE riwayat_mutasi_aset (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aset_barang_id BIGINT UNSIGNED NOT NULL,
    ruangan_asal_id BIGINT UNSIGNED NOT NULL,
    ruangan_tujuan_id BIGINT UNSIGNED NOT NULL,
    qty_pindah INT UNSIGNED NOT NULL DEFAULT 1,
    tanggal_mutasi DATE NOT NULL,
    alasan_mutasi TEXT NOT NULL,
    dilakukan_oleh_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (aset_barang_id) REFERENCES aset_barang(id) ON DELETE CASCADE,
    FOREIGN KEY (ruangan_asal_id) REFERENCES ruangan(id) ON DELETE RESTRICT,
    FOREIGN KEY (ruangan_tujuan_id) REFERENCES ruangan(id) ON DELETE RESTRICT,
    FOREIGN KEY (dilakukan_oleh_user_id) REFERENCES users(id) ON DELETE RESTRICT
);
```

---

## 5. Arsitektur Domain & File Structure

Sesuai standar `laravel-feature-standard`:

```text
app/
├── Domains/
│   ├── Shared/
│   │   └── Context/
│   │       └── TenantContext.php
│   │
│   └── Sarpras/
│       ├── Actions/
│       │   ├── CreateGedungAction.php
│       │   ├── UpdateGedungAction.php
│       │   ├── CreateRuanganAction.php
│       │   ├── UpdateRuanganAction.php
│       │   ├── CreateKategoriAsetAction.php
│       │   ├── CreateAsetBarangAction.php
│       │   ├── UpdateAsetBarangAction.php
│       │   ├── MutasiAsetRuanganAction.php
│       │   └── ValidateRoomClashAction.php
│       │
│       ├── DataTransferObjects/
│       │   ├── GedungData.php
│       │   ├── RuanganData.php
│       │   ├── KategoriAsetData.php
│       │   ├── AsetBarangData.php
│       │   └── MutasiAsetData.php
│       │
│       ├── Enums/
│       │   ├── JenisRuangan.php
│       │   ├── KondisiAset.php
│       │   ├── TipePencatatanAset.php
│       │   └── SumberPerolehanAset.php
│       │
│       ├── Models/
│       │   ├── Gedung.php
│       │   ├── Ruangan.php
│       │   ├── KategoriAset.php
│       │   ├── AsetBarang.php
│       │   └── RiwayatMutasiAset.php
│       │
│       └── ViewModels/
│           ├── RuanganIndexViewModel.php
│           ├── AsetIndexViewModel.php
│           └── KartuInventarisRuanganViewModel.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Lembaga/
│   │   │   └── Sarpras/
│   │   │       ├── GedungController.php
│   │   │       ├── RuanganController.php
│   │   │       ├── KategoriAsetController.php
│   │   │       ├── AsetBarangController.php
│   │   │       ├── MutasiAsetController.php
│   │   │       └── KirController.php
│   │   │
│   │   └── Yayasan/
│   │       └── Sarpras/
│   │           ├── GedungYayasanController.php
│   │           ├── RuanganYayasanController.php
│   │           └── RekapAsetGlobalController.php
│   │
│   └── Requests/
│       └── Sarpras/
│           ├── StoreGedungRequest.php
│           ├── StoreRuanganRequest.php
│           ├── StoreAsetBarangRequest.php
│           └── StoreMutasiAsetRequest.php
│
└── Views:
    ├── portals/
    │   ├── lembaga/sarpras/
    │   │   ├── gedung/ (index, create, edit)
    │   │   ├── ruangan/ (index, create, edit, show)
    │   │   ├── aset/ (index, create, edit, show, mutasi-modal)
    │   │   └── kir/ (show, pdf)
    │   └── yayasan/sarpras/
    │       ├── gedung/
    │       └── rekap/
```

---

## 6. Acceptance Criteria

1. **Gedung & Ruangan:**
   - Admin lembaga dapat mengelola gedung dan ruangan milik unitnya.
   - Ruangan dengan `is_shared = true` dapat dilihat dan dipilih oleh unit lembaga lain di bawah yayasan yang sama.
2. **Integrasi Akademik & Anti-Bentrok:**
   - Pembuatan jadwal pelajaran yang menggunakan ruangan yang sama pada hari & jam pelajaran yang beririsan wajib tertolak dengan pesan error yang jelas (*Room Clash Detected*).
3. **Aset & Inventaris:**
   - Barang bertipe `unit` wajib memiliki kode inventaris unik.
   - Barang bertipe `batch` mencatat total kuantitas per ruangan.
4. **Mutasi Ruangan:**
   - Pemindahan aset dari Ruang A ke Ruang B memvalidasi ketersediaan qty di Ruang A, mengupdate lokasi aset (atau memecah baris jika mutasi sebagian batch), serta mencatat log di `riwayat_mutasi_aset`.
5. **Cetak KIR (Kartu Inventaris Ruangan):**
   - User dapat mengunduh PDF Kartu Inventaris Ruangan yang memuat daftar seluruh aset, kondisi, penanggung jawab, dan tanggal cetak.
6. **Multi-Tenant & RBAC:**
   - Seluruh query terfilter aman berdasarkan `TenantContext` aktif.
   - Hak akses sesuai matriks RBAC (Sarpras Yayasan, Admin Sarpras Lembaga, dan Guru/Staf).
