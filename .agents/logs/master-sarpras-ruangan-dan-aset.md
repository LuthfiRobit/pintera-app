# Handoff Log: Master Sarpras, Ruangan, Kategori, dan Aset Inventaris

- **Spec File:** `.agents/specs/master-sarpras-ruangan-dan-aset.md`
- **Plan File:** `.agents/plans/master-sarpras-ruangan-dan-aset.md`
- **Tanggal:** 16 Agustus 2026
- **Git Branch:** `akademik-v2`
- **Status:** **COMPLETE & VERIFIED**

---

## 1. Apa yang Dikerjakan

Telah selesai dibangun modul **Master Sarana & Prasarana (Sarpras)** secara penuh dan modular menggunakan arsitektur domain `app/Domains/Sarpras/` sesuai standar `laravel-feature-standard`, yang mencakup:

1. **Fondasi Domain & Shared Context (`app/Domains/Shared/Context/TenantContext.php`):**
   - Resolusi multi-tenant dinamis berbasis scope akun user (Scope Lembaga vs Scope Yayasan).
   - Enums domain: `JenisRuangan`, `KondisiAset`, `TipePencatatanAset`, dan `SumberPerolehanAset`.

2. **Skema Database & Migrasi (6 Migrasi):**
   - Tabel `gedung` (dukungan multi-tenant hybrid: kepemilikan unit lembaga & fasilitas yayasan).
   - Tabel `ruangan` (dukungan tipe ruangan, kapasitas, luas m², penanggung jawab, flag `is_shared`).
   - Alterasi tabel `kelas` (tambah foreign key `ruangan_id` sebagai *Home Room*).
   - Alterasi tabel `jadwal_pelajaran` (tambah foreign key `ruangan_id` untuk alokasi slot ruangan KBM).
   - Tabel `kategori_aset` (pengelompokan inventaris).
   - Tabel `aset_barang` (pencatatan hybrid: barcode unik per unit vs kuantitas massal per ruangan).
   - Tabel `riwayat_mutasi_aset` (audit log perpindahan barang antar-ruangan).

3. **Eloquent Models & RBAC Permissions (`app/Domains/Sarpras/Models/`):**
   - Model `Gedung`, `Ruangan`, `KategoriAset`, `AsetBarang`, dan `RiwayatMutasiAset` (dengan Spatie ActivityLog & Relasi lengkap).
   - `SarprasPermissionSeeder` untuk 11 granular permissions: `sarpras.gedung.*`, `sarpras.ruangan.*`, `sarpras.kategori.*`, `sarpras.aset.*`, `sarpras.mutasi.*`, dan `sarpras.kir.export`.

4. **DTOs & Business Logic Actions (`app/Domains/Sarpras/Actions/`):**
   - `CreateGedungAction` & `UpdateGedungAction`
   - `CreateRuanganAction` & `UpdateRuanganAction`
   - `ValidateRoomClashAction` (Deteksi & pencegahan bentrok ruangan pada jadwal pelajaran).
   - `CreateKategoriAsetAction`
   - `CreateAsetBarangAction` & `UpdateAsetBarangAction`
   - `MutasiAsetRuanganAction` (Perpindahan lokasi aset, pemecahan batch qty, dan pencatatan audit log mutasi).

5. **HTTP Layer & Controllers:**
   - `GedungController`, `RuanganController`, `KategoriAsetController`, `AsetBarangController`, `MutasiAsetController`, dan `KirController` (Scope Lembaga).
   - `RekapAsetGlobalController` (Scope Yayasan).
   - Registrasi rute di `routes/admin.php` di bawah prefix `admin/sarpras/`.

6. **Frontend Blade Views & Cetak PDF KIR:**
   - 12 Blade views interaktif modern di `resources/views/portals/lembaga/sarpras/` dan `resources/views/portals/yayasan/sarpras/`.
   - Template PDF resmi Kartu Inventaris Ruangan (KIR) berbasis DomPDF (`resources/views/pdf/kartu-inventaris-ruangan.blade.php`).
   - Penambahan navigasi menu Sarpras pada Sidebar aplikasi (`resources/views/layouts/sidebar.blade.php`).

---

## 2. Keputusan Penting yang Diambil

1. **Kepemilikan Hybrid (Lembaga & Yayasan):**
   - Kolom `lembaga_id` dibuat *nullable* pada tabel `gedung` dan `ruangan`. Jika `null`, entitas tersebut dianggap milik Yayasan (Fasilitas Bersama/Shared Facility seperti Masjid Yayasan, Aula Utama, Lapangan Bersama).
2. **Pencatatan Aset Hybrid (Unit vs Batch):**
   - Aset bernilai tinggi (laptop, proyektor, PC) dicatat per kode barcode unik (`tipe_pencatatan = 'unit'`).
   - Aset perabotan/massal (kursi, meja, spidol) dicatat berbasis kuantitas per ruangan (`tipe_pencatatan = 'batch'`).
   - Pada saat mutasi sebagian kuantitas batch, sistem otomatis memotong stok ruangan asal dan mendistribusikan kuantitas ke ruangan tujuan tanpa merusak integritas data.
3. **Anti-Bentrok Ruangan pada Jadwal Mengajar:**
   - `ValidateRoomClashAction` memeriksa apakah ruangan yang sama telah dialokasikan pada semester dan jam pelajaran yang beririsan untuk mencegah bentrok ruangan antar-kelas.
4. **Base Controller Trait:**
   - Menambahkan `use AuthorizesRequests;` pada `app/Http/Controllers/Controller.php` untuk standardisasi otorisasi `$this->authorize(...)` di Laravel 12.

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Integrasi Form Pembuatan Jadwal Pelajaran:**
   - Saat admin membuat jadwal pelajaran baru di menu Akademik, field dropdown `ruangan_id` kini sudah tersedia di database dan siap dihubungkan dengan memanggil `ValidateRoomClashAction`.
2. **Roadmap Lanjutan (Pengadaan / E-Budgeting):**
   - Fondasi master data aset dan ruangan ini sudah 100% siap untuk diintegrasikan dengan modul **Pengajuan Pengadaan (Purchase Requisition) & LPJ**, di mana ketika LPJ disetujui, item belanja dapat langsung di-*convert* menjadi record `AsetBarang` baru di ruangan tujuan.

---

## 4. Status Verifikasi Pengujian

Seluruh rangkaian pengujian otomatis (*Automated Tests*) telah dijalankan dan lulus 100%:
- `Tests\Unit\Domains\Shared\TenantContextTest` -> **PASS**
- `Tests\Unit\Domains\Sarpras\SarprasModelsTest` -> **PASS**
- `Tests\Unit\Domains\Sarpras\GedungRuanganActionTest` -> **PASS**
- `Tests\Unit\Domains\Sarpras\AsetMutasiActionTest` -> **PASS**
- `Tests\Feature\Sarpras\GedungRuanganControllerTest` -> **PASS**
- `Tests\Feature\Sarpras\KirPdfExportTest` -> **PASS**
