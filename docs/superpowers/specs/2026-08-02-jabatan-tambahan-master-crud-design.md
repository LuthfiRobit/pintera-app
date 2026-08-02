# Desain Spesifikasi: CRUD Mandiri Jabatan Tambahan Master (Reactive AJAX/Fetch with Alpine.js)

**Tanggal:** 2026-08-02  
**Status:** Disetujui (Approved)  
**Pendekatan:** Single Page Application (SPA) style CRUD menggunakan Laravel Controller standar dan antarmuka reaktif Alpine.js dengan AJAX/Fetch tanpa reload halaman.

---

## 1. Ringkasan Tujuan
Fitur ini menyediakan portal pengelolaan data master Jabatan Tambahan (`jabatan_tambahan_master`) di panel Admin. Jabatan ini mencakup posisi struktural (contoh: Wakil Kepala Sekolah Kurikulum) dan fungsional (contoh: Wali Kelas, Pembina OSIS) yang dapat disandang oleh guru. Portal pengelola dibangun berlandaskan UX yang cepat dan responsif tanpa reload (*zero-reload*), dilindungi oleh proteksi integritas relasi ke data Guru, serta dipercantik dengan indikator statistik reaktif dan filter tab seketika.

---

## 2. Arsitektur Backend & RBAC Permissions

### 2.1 RBAC Permissions
Empat *permission* baru akan ditambahkan pada `PermissionSeeder.php` dengan prefiks `jabatan-tambahan-master.` untuk mencegah kebingungan dengan manajemen relasi jabatan di profil individu Guru:
- `jabatan-tambahan-master.view`: Hak akses melihat daftar master jabatan tambahan.
- `jabatan-tambahan-master.create`: Hak akses menambah master jabatan tambahan baru.
- `jabatan-tambahan-master.edit`: Hak akses mengubah nama atau kelompok jabatan.
- `jabatan-tambahan-master.delete`: Hak akses menghapus master jabatan tambahan.

### 2.2 Model & Rute
- **Model Utama**: `App\Models\JabatanTambahanMaster` (melayani tabel `jabatan_tambahan_master`).
- **Rute Web/Admin**: Dialokasikan pada `routes/admin.php` di bawah grup middleware `auth`, `verified`, serta prefiks URL `admin` dan name `admin.`:
  ```php
  Route::get('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'index'])->name('jabatan-tambahan-master.index');
  Route::post('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'store'])->name('jabatan-tambahan-master.store');
  Route::put('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'update'])->name('jabatan-tambahan-master.update');
  Route::delete('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'destroy'])->name('jabatan-tambahan-master.destroy');
  ```

### 2.3 Controller (`JabatanTambahanMasterController`)
Controller terletak di `App\Http\Controllers\Admin\JabatanTambahanMasterController.php`:
- **`index()`**:
  - Otorisasi: `$this->authorize('jabatan-tambahan-master.view')`.
  - Query: `JabatanTambahanMaster::withCount('guru')->orderBy('kelompok')->orderBy('nama')->get()`.
  - Jika request membutuhkan JSON (`$request->wantsJson()`), mengembalikan balikan JSON array koleksi data.
  - Secara default mengembalikan tampilan Blade: `admin.jabatan-tambahan-master.index` dengan membawa variabel `$jabatanList`.
- **`store(Request $request)`**:
  - Otorisasi: `$this->authorize('jabatan-tambahan-master.create')`.
  - Validasi:
    - `nama`: `['required', 'string', 'max:255', 'unique:jabatan_tambahan_master,nama']`
    - `kelompok`: `['required', Rule::in(['struktural', 'fungsional'])]`
  - Mengeksekusi penciptaan record baru dan merespons dengan JSON HTTP 201 (Created): `['message' => 'Jabatan tambahan berhasil dirilis', 'item' => $item->loadCount('guru')]`.
- **`update(Request $request, JabatanTambahanMaster $jabatanTambahanMaster)`**:
  - Otorisasi: `$this->authorize('jabatan-tambahan-master.edit')`.
  - Validasi: Sama seperti store, namun aturan unik mengecualikan ID data saat ini: `Rule::unique('jabatan_tambahan_master', 'nama')->ignore($jabatanTambahanMaster->id)`.
  - Memperbarui database dan membalikkan JSON HTTP 200: `['message' => 'Data jabatan berhasil diperbarui', 'item' => $jabatanTambahanMaster->fresh('guru')]`.
- **`destroy(Request $request, JabatanTambahanMaster $jabatanTambahanMaster)`**:
  - Otorisasi: `$this->authorize('jabatan-tambahan-master.delete')`.
  - **Relational Integrity Shield**: Memperoleh verifikasi jika `$jabatanTambahanMaster->guru()->exists()`. Jika `true`, aksi dibatalkan seketika dan merespons dengan HTTP 422 Unprocessable Entity berisikan keterangan error: `['message' => 'Jabatan tidak dapat dihapus karena saat ini masih disandang oleh N Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.']`.
  - Jika aman dari relasi aktif, mengeksekusi penghapusan dan merespons HTTP 200 JSON: `['message' => 'Jabatan telah dihapus permanen.']`.

---

## 3. Desain Antarmuka Frontend (Blade + Alpine.js Reactive SPA)

### 3.1 Struktur Tampilan (`resources/views/admin/jabatan-tambahan-master/index.blade.php`)
Halaman dibangun sepenuhnya berprinsip SPA menggunakan kombinasi HTML semantik, Tailwind CSS, dan sistem state reaktif Alpine.js:
- **Header & Widget Statistik Reaktif**:
  - Judul dengan ikon SVG identitas standar Pintera (tanpa emoji).
  - Tiga Kartu Statistik Live yang dikalkulasi secara reaktif via Alpine computed formulas (`items.length`, `items.filter(i => i.kelompok === 'struktural').length`, `items.filter(i => i.kelompok === 'fungsional').length`).
  - Tombol aksi utama `+ Tambah Jabatan` yang membuka modal input bersertakan ikon tambah SVG.
- **Tab Filter Navigasi**:
  - Memakai utility `scrollbar-none` dengan border bawah modern untuk berganti status tampilan secara instan: *Semua*, *Struktural*, dan *Fungsional*.
- **Tabel Interaktif**:
  - Menampilkan baris per item dengan format kolom: Nama Jabatan, Kelompok (dilengkapi tag visual berkolaborasi warna khas: Biru untuk Struktural, Hijau Zamrud untuk Fungsional), Badge Jumlah Guru, dan kolom Aksi Ikonik (Edit & Hapus) dengan tooltip standar yang rapi.
  - Menangani status kosong (*Empty State*) ketika tidak ada data atau hasil filter nihil dengan ilustrasi SVG dan pesan panduan.

### 3.2 State Machine Alpine.js & Interaksi AJAX
Komponen diikat oleh state obyek tunggal Alpine.js:
- **Data Attributes**: `items: @json($jabatanList)`, `filter: 'semua'`, `isModalOpen: false`, `modalMode: 'add'`, `formData: { id: null, nama: '', kelompok: 'fungsional' }`, `formErrors: {}`, `isLoading: false`, dan `toastMessage: null`.
- **Validasi Error Realtime**:
  - Bila pengiriman `fetch()` memblokir dengan status HTTP 422 dari backend, error ditangkap dan diserahkan ke objek `formErrors`. Input bersangkutan akan menyoroti viualisasi batas merah serta menampilkan keterangan spesifik tepat di bagian bawah field tersebut.
- **Mutasi Tanpa Refresh**:
  - Pada penambahan berhasil (201), item balikan dimasukkan langsung ke array `items` via method `items.push(data.item)`.
  - Pada pembaharuan berhasil (200), elemen array yang cocok digantikan secara langsung.
  - Pada penghapusan sukses (200), elemen array dibuang via `items = items.filter(i => i.id !== id)`.
- **Proteksi Error & Toast Notifications**:
  - Semua pesan kesuksesan maupun penolakan penghantaran (seperti proteksi hapus 422) dimunculkan sebagai notifikasi animasi *Toast Alert* melayang di pojok kanan layar, memastikan operator paham situasi tanpa hambatan loading atau *refresh*.

---

## 4. Rencana Verifikasi dan Pengujian (Verification & Testing Plan)

### 4.1 Automated Feature Testing
Sebuah berkas pengujian lengkap akan dirilis pada `tests/Feature/Admin/JabatanTambahanMasterCrudTest.php`. Pengujian diatur guna mengevaluasi spesifikasi berikut:
1. `it ('refuses access to unverified or unauthorized users without view permission')` - Otorisasi 403.
2. `it ('renders the master dashboard view cleanly with statistics and data')` - Respons HTTP 200 pada rute index.
3. `it ('allows authorized administrator to store new structural or functional master data via AJAX')` - Status 201 Created dengan assertion database `jabatan_tambahan_master`.
4. `it ('blocks duplicate position naming via AJAX validation')` - Status 422 error JSON pada kolom nama.
5. `it ('allows editing an existing position name and group cleanly')` - Status 200 OK dengan pembaruan database terpantau.
6. `it ('deletes unused position directly via AJAX')` - Status 200 OK dengan record musnah.
7. `it ('safeguards position currently linked to gurus from deletion')` - Mempertahankan record di database, menolak request dengan status 422 berpesan keterangan relasi aktif.

### 4.2 Manual Validation Checklist
- Menghidupkan *development server* (`npm run dev` & php artisan serve) untuk tes klinis:
  - Membaca kebergantungan animasi modal pada Chrome / Edge (terutama kemantapan scrollbar di filter tabs).
  - Melipat gandakan tes input kembar pada formulir modal guna memeriksa tampilan inline validation warnings tanpa reload.
  - Menekan hapus pada jabatan "Wali Kelas" yang terikat guru pada seeder demi menyaksikan hadirnya Toast Alert merah penahanan relasi yang informatif.
