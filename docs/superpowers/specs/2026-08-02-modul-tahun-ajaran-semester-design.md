# Spesifikasi Desain: Modernisasi Modul Tahun Ajaran & Semester (Data Induk)
**Tanggal**: 2026-08-02  
**Status**: Disetujui  
**Penulis**: Antigravity AI (ber kolaborasi dengan USER)

---

## 1. Latar Belakang & Tujuan
Modul Tahun Ajaran & Semester merupakan bagian fundamental dari data induk (*master data*) sekolah pada portal pintera-app. Desain antarmuka saat ini membutuhkan penyesuaian untuk memenuhi standar baru UI/UX sistem pendidikan ini, termasuk penerapan:
- **3-Input Standard**: Entitas dengan $\le 3$ input diubah menjadi SPA Modal pop-up (tanpa jeda navigasi halaman terpisah).
- **Informative Executive Cards**: Desain kartu bergabungan yang rapi dan elegan terinsipirasi dari modul Komponen Penilaian.
- **Unified Semester Management**: Konsep 1 Form Terpadu dalam 1 Modal yang memungkinkan konfigurasi langsung untuk kedua Semester (Ganjil dan Genap) secara berbarengan menggunakan operasi *Batch Upsert*.

---

## 2. Arsitektur Domain & Alur Pemrosesan (Backend)

### 2.1. Tahun Ajaran Controller (`TahunAjaranController.php`)
- **Penambahan kapabilitas `update`**: Mendaftarkan rute `PUT /admin/tahun-ajaran/{tahunAjaran}` (`admin.tahun-ajaran.update`). Method ini memproses modifikasi pada kolom `nama`, `tanggal_mulai`, dan `tanggal_selesai` langsung dari modal edit SPA.
- **Penetapan Skema Validasi**:
  ```php
  'nama'            => ['required', 'string', 'max:20'],
  'tanggal_mulai'   => ['required', 'date'],
  'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
  ```

### 2.2. Semester Controller (`SemesterController.php`) - *Batch Upsert*
- Menghentikan pembuatan semester satuan tunggal (*single-item inline store*) dan mengarahkannya ke **Batch Upsert Endpoint** pada rute `POST /admin/semester` (`admin.semester.store`).
- **Skema Validasi Unified**:
  ```php
  'tahun_ajaran_id'        => ['required', 'integer', 'exists:tahun_ajaran,id'],
  'ganjil_kode_dapodik'    => ['nullable', 'string', 'max:10'],
  'ganjil_tanggal_mulai'   => ['required', 'date'],
  'ganjil_tanggal_selesai' => ['required', 'date', 'after:ganjil_tanggal_mulai'],
  'genap_kode_dapodik'     => ['nullable', 'string', 'max:10'],
  'genap_tanggal_mulai'    => ['required', 'date', 'after:ganjil_tanggal_selesai'],
  'genap_tanggal_selesai'  => ['required', 'date', 'after:genap_tanggal_mulai'],
  ```
- **Logika Transaksi Upsert**:
  Dalam bungkus `DB::transaction`, sistem menjalankan operasi `updateOrCreate`:
  1. **Semester Ganjil**: Kondisi pencarian `['tahun_ajaran_id' => $id, 'urutan' => 1]`. Parameter yang diperbarui/diset: `nama = 'Ganjil'`, `lembaga_id`, `kode_dapodik`, `tanggal_mulai`, `tanggal_selesai`.
  2. **Semester Genap**: Kondisi pencarian `['tahun_ajaran_id' => $id, 'urutan' => 2]`. Parameter yang diperbarui/diset: `nama = 'Genap'`, `lembaga_id`, `kode_dapodik`, `tanggal_mulai`, `tanggal_selesai`.

---

## 3. Komponen Antarmuka & Desain Visual (Frontend)

### 3.1. Compact Horizontal Statistic Cards (KPI Tile)
Pada puncak halaman Index, disajikan 3 kartu statistik horisontal seragam:
1. **Total Tahun Ajaran**: Jumlah keseluruhan entitas per lembaga.
2. **Tahun Ajaran Aktif**: Menampilkan nama tahun ajaran berkondisi `status_aktif = true` (atau "Belum Ada").
3. **Semester Berjalan**: Menampilkan nama semester yang sedang aktif beserta pasangan tahun ajarannya (misal: "Ganjil 2026/2027").

### 3.2. Informative Executive Cards (Daftar Tahun Ajaran)
Setiap rekod Tahun Ajaran tidak lagi diisi list teks kuno, melainkan **Card Executive** berkelas:
- **Header Kartu**:
  - Judul dengan font Display bergaris tegas (misal: **2026/2027**).
  - Badge Indikator Status: Hijau berkilau untuk *Aktif* (`bg-success-50`), Abu-abu lembut untuk *Nonaktif / Selesai*.
  - Rentang tanggal durasi keseluruhan yang dilengkapi ikon kalender.
  - Deretan Tombol Aksi Kanan: Tombol *Aktifkan* (bila nonaktif), tombol *Edit* (membuka SPA Modal Tahun Ajaran), dan tombol *Atur Semester* (membuka SPA Modal Unified Semester).
- **Body Kartu (2-Column Semester Grid)**:
  - Dibagi memakai kelas Tailwind `grid grid-cols-1 md:grid-cols-2 gap-4`.
  - Boks Kiri (Semester Ganjil) & Boks Kanan (Semester Genap).
  - Apabila semester belum diatur: Menghadirkan visual kosong nan mengundang, dilengkapi tombol ajakan *Atur Semester Ganjil & Genap Sekarang*.
  - Apabila semester telah dikonfigurasi: Menampilkan Kode Dapodik, tanggal operasional spesifik semester, indikator *Aktif/Nonaktif*, serta tombol *Aktifkan Semester*. (Tombol di-disable dan dilatih dengan tooltip informatif bila Tahun Ajaran utamanya tidak aktif).

### 3.3. SPA Modal Pop-ups (Zero Page Reload)
- **Modal 1: Manajemen Tahun Ajaran (`_modal-tahun-ajaran.blade.php`)**:
  - Berperan tunggal untuk Tambah & Edit Data (3 input: Nama, Tanggal Mulai, Tanggal Selesai).
- **Modal 2: Konfigurasi Batch Semester (`_modal-semester.blade.php`)**:
  - Menyediakan 1 Form dengan 2 panel pendamping secara vertikal atau horisontal (Panel Ganjil & Panel Genap) agar admin menyelesaikan kalender kurikulum tahunan secara serentak dalam hitungan detik.

---

## 4. Strategi Pengujian & Verifikasi Mutu

### 4.1. Verifikasi Otomatis (PHPUnit / Pest)
- Memastikan tidak ada *regression* terhadap seeder yang bertaut ke semester aktif (`LembagaDataPeriodikSeederTest`, `TagihanSeederTest`, dsb).
- Menyatukan atau menyempurnakan `TahunAjaranActivationTest` dan membuat `TahunAjaranSemesterCrudTest` guna membuktikan ketepatan eksekusi *Batch Upsert*, perpindahan kepemilikan antar lembaga (tenant context), dan aturan *lock* di mana semester tunduk terhadap keaktifan Tahun Ajaran induknya.

### 4.2. Verifikasi Manual (UI & Rendering)
- Mengevaluasi tata letak *Compact Horizontal KPI Cards* pada berbagai rasio layar.
- Melompat bolak-balik antara pembukaan dan penutupan 2 Modal SPA di halaman utama tanpa terjadi kebocoran DOM atau masalah *layering* (z-index).
