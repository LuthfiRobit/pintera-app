# Spesifikasi Desain: Modernisasi UI/UX Modul Mata Pelajaran & Standar Form Pintera

**Tanggal**: 2026-08-02  
**Modul**: Mata Pelajaran (II. Data Induk)  
**Status**: Draf Spesifikasi Disetujui (Approved Design)

---

## 1. Ikatan Standar Emas Pintera (Golden System Standard)
Dalam pengembangan modul ini dan seluruh fitur Pintera ke depannya, diatur kaidah wajib berikut:
1. **Ambang Batas Form (Form Input Threshold)**:
   - **≤ 3 Input**: Wajib menggunakan skema **Reactive SPA Modal** (tanpa muatan ulang halaman / *no full page reload*), seperti pada modul *Jabatan Tambahan Master*.
   - **> 3 Input**: Wajib menggunakan **Form Terpisah** (*Separate Page Forms* pada rute `/create` dan `/edit`) guna memberikan kenyamanan pengisian dan keterbacaan kolom yang kompleks.
2. **Komponen Pilihan (Dropdown Select Option)**:
   - Seluruh elemen `<select>` wajib menggunakan **Tom Select** (`tom-select`) guna menyeleraskan estetika, konsistensi antarmuka sistem Pintera, serta menyediakan fungsi pencarian instan pada daftar opsi.

---

## 2. Arsitektur & Struktur Tampilan Modul Mata Pelajaran
Modul Mata Pelajaran memiliki **6 inputan** (*kode, nama, no_urut, tipe, kelompok, status*), sehingga memedomani skema **Form Terpisah + Interaktivas AJAX Tabel ala Komponen Penilaian**.

### A. Komponen Halaman Utama (`resources/views/admin/mata-pelajaran/index.blade.php`)
1. **Header & Breadcrumb**: Judul halaman yang elegan bersanding dengan alur navigasi (*Beranda > Mata Pelajaran*).
2. **Kartu Statistik Eksekutif (3 KPI Compact Tiles)**: 
   Disusun secara horizontal di atas tabel menggunakan tata letak `grid grid-cols-1 gap-3 sm:grid-cols-3` dan komponen `<x-stat-tile>` berspesifikasi compact (ikon di kanan, angka besar yang jelas):
   - **Total Mata Pelajaran**: Ikon `menu_book` (Tone Brand / Biru), menghitung total record milik lembaga.
   - **Kurikulum Mapel (SD-SMK)**: Ikon `school` (Tone Success / Hijau), menghitung total record dengan tipe `mapel`.
   - **Aspek Perkembangan (PAUD/TK)**: Ikon `extension` (Tone Warning / Kuning/Amber), menghitung total record tipe `aspek_perkembangan`.
3. **Filter Card (Mobile-First Layout)**:
   - Dibungkus dengan modul JavaScript Alpine `x-data="mataPelajaranFilter({ ... })"`.
   - Tata letak responsif bermula dari mobile (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`).
   - Input pencarian (Debounce 400ms untuk Kode/Nama).
   - Tiga dropdown filter (Tipe, Kelompok, Status) yang diikat dengan `x-init="initFilterSelect($el)"` (Tom Select). Saat opsi dipilih, filter memicu AJAX untuk memperbarui tabel secara instan.
   - Tombol utama `+ Tambah Mata Pelajaran` diposisikan di sudut kanan atas *Filter Card*.
4. **Wadah Daftar Tabel (`_daftar.blade.php`)**:
   - Ditampung dalam kontainer `<div x-ref="daftarMataPelajaran">@include('admin.mata-pelajaran._daftar')</div>`.
   - Menggunakan header tabel minimalis yang memuat judul *"Daftar Mata Pelajaran"* dan dropdown navigasi **"Tampilkan [ 10 | 20 | 25 | 50 ] per halaman"**.
   - Kolom tombol `"Aksi"` (Edit / Aktifkan / Nonaktifkan) diposisikan secara *sticky* di paling kiri tabel.
   - Paginasi mengadopsi tampilan kustom `pagination.tailadmin`.

### B. Halaman Form Create & Edit (`_form.blade.php`, `create.blade.php`, `edit.blade.php`)
1. Dibungkus dengan modul Alpine `x-data="mataPelajaranForm()"`.
2. Tiga input teks konvensional: *Kode Mapel*, *Nama Mapel*, dan *No. Urut Rapor*.
3. Tiga input dropdown (*Tipe Kurikulum*, *Kelompok Mata Pelajaran*, dan *Status Keaktifan*) diikat dengan `x-init="initSelect($el)"` agar diubah otomatis menjadi kontainer Tom Select berpenampilan modern.

---

## 3. Arsitektur JavaScript & Asset Bundling (Vite)

### A. Modul Filter & AJAX Tabel (`resources/js/mata-pelajaran-filter.js`)
* Menginisialisasi Tom Select pada setiap dropdown filter dengan pengaturan `maxItems: 1` dan `allowEmptyOption: true`.
* Pada event `onChange` atau ketukan penekanan pada search bar, fungsi `muatUlangDaftar()` dieksekusi:
  - Menerapkan *fetch API* ke rute `admin.mata-pelajaran.index` dengan header `'X-Requested-With': 'XMLHttpRequest'`.
  - Mengambil string HTML potongan tabel dari server dan menyediakannya langsung di dalam `this.$refs.daftarMataPelajaran.innerHTML`.
  - Memutakhirkan parameter URL pada address bar browser menggunakan `window.history.pushState` tanpa merestock/reload halaman keseluruhan.

### B. Modul Form Interaktif (`resources/js/mata-pelajaran-form.js`)
* Menginisialisasi Tom Select pada elemen `<select>` di halaman `create` dan `edit` dengan konfigurasi standar yang mulus dan bebas lag.

### C. Registrasi di Asset Bundler (`resources/js/app.js`)
* Mengimpor kedua modul di atas dan mendaftarkannya pada ekosistem Alpine global Windows:
  ```javascript
  import { mataPelajaranFilter } from './mata-pelajaran-filter';
  import { mataPelajaranForm } from './mata-pelajaran-form';
  ...
  Alpine.data('mataPelajaranFilter', mataPelajaranFilter);
  Alpine.data('mataPelajaranForm', mataPelajaranForm);
  ```

---

## 4. Modifikasi Backend (`MataPelajaranController`)
* **Pengkalkulasian KPI**: Pada method `index()`, kalkulasi nilai indikator dilakukan menggunakan model scope lembaga dan didistribusikan ke variabel tampilan utama.
* **Respon AJAX Terisolir**: Sebelum mengembalikan tampilan utuh `admin.mata-pelajaran.index`, pengecekan dilakukan:
  ```php
  if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
      return view('admin.mata-pelajaran._daftar', [
          'mataPelajaranList' => $query->paginate($perPage)->withQueryString(),
          'perPage'           => $perPage,
      ]);
  }
  ```
* Ini menjaga transfer payload data sangat hemat waktu, responsif, serta kompatibel 100% dengan standar arsitektur *Komponen Penilaian*.

---

## 5. Rencana Verifikasi & Pengujian Teknis (Verification Plan)
1. **Build Kompilasi Vite**: Menjalankan eksekusi bundel asset (`npm run build` atau dev mode verifikasi) guna memastikan kode JavaScript dan style Tom Select terintegrasi sempurna di `app.js` dan `app.css`.
2. **Automated Testing (PHPUnit/Pest Feature Test)**:
   - Menginspeksi atau memutakhirkan berkas uji pengetes otomatis pada `Tests\Feature\Admin\MataPelajaranTest.php` (atau sejenisnya).
   - Menyetel pengujian kepastian kalkulasi KPI pada muatan awal rute index.
   - Memastikan pengiriman request HTTP GET bermode AJAX menghasilkan balasan rendered string partial `_daftar` berstatus `200 OK`.
   - Mengoperasikan uji otentikasi, pembuatan (*store*), pemutakhiran (*update*), dan keandalan validasi seluruh 6 kolom form Mata Pelajaran.
