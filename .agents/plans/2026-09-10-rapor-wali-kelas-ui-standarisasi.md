# Plan: Standarisasi UI/UX & Tabel Rapor Wali Kelas (Guru)

- **Spec**: `.agents/specs/2026-09-10-rapor-wali-kelas-ui-standarisasi.md`
- **Slug**: `2026-09-10-rapor-wali-kelas-ui-standarisasi`
- **Status**: Completed

## Checklist Implementasi

- [x] **Langkah 1: Routing Endpoint Opsi AJAX**
  - Menambahkan rute `GET /guru/rapor/opsi` di `routes/guru.php` (`guru.rapor.catatan.opsi`).
  - Verifikasi otorisasi permission `rapor.input-wali`.

- [x] **Langkah 2: Backend Controller & Partial Rendering**
  - Update `app/Http/Controllers/Guru/RaporController.php`:
    - Mengembalikan partial view `_daftar.blade.php` saat request merupakan AJAX / `X-Requested-With: XMLHttpRequest`.
    - Menghitung statistik 4 KPI cards (`totalSiswa`, `totalLengkap`, `totalBelumLengkap`, `isNilaiComplete`, `totalNilaiKosong`, `statusPengajuan`).
    - Menambahkan filter query `search` (nama/nis/nisn) dan `status_catatan` (`complete/lengkap`, `incomplete/perlu_dilengkapi`).
    - Menambahkan method `opsi(Request $request): JsonResponse` untuk mengembalikan opsi semester & kelas perwalian aktif.

- [x] **Langkah 3: Template Tabel Partial (`_daftar.blade.php`)**
  - Membuat `resources/views/portals/guru/rapor/catatan/_daftar.blade.php`:
    - Urutan kolom: `AKSI` (kiri) | `NAMA SISWA & NIS` | `STATUS CATATAN` | `RINGKASAN CATATAN / EKSKUL`.
    - Tombol Edit & PDF dengan styling outline card rounded (`border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold shadow-2xs`).
    - Badge status catatan pill badge, kutipan catatan, badge ekskul & prestasi.
    - Action bar pengajuan rapor kelas dengan modal konfirmasi dan disable guard.

- [x] **Langkah 4: Main View & Interaktivitas Frontend (`index.blade.php`)**
  - Update `resources/views/portals/guru/rapor/catatan/index.blade.php`:
    - 4 KPI summary cards di bagian atas.
    - Toolbar filter dengan 3 select TomSelect (Tahun Ajaran, Semester, Kelas).
    - Segmented pill filter tabs (`Semua Siswa`, `● Perlu Dilengkapi`, `● Catatan Lengkap`).
    - Input live search dengan icon dan tombol reset.
    - Alpine component `raporWaliKelasFilter` dengan pushState URL sync, async fetch AJAX, dan centered dark pill loading shimmer overlay.

- [x] **Langkah 5: Code Formatting & Testing**
  - Jalankan `vendor/bin/pint --dirty --format agent`.
  - Jalankan test `tests/Feature/Guru/RaporControllerTest.php`.
  - Tambahkan test untuk endpoint `opsi`, rendering AJAX partial `_daftar`, serta query search dan status filter.
  - Verifikasi seluruh 32 test lulus.

- [x] **Langkah 6: Visual & Functional Verification**
  - Browser subagent membuka `http://127.0.0.1:8000/guru/rapor?kelas_id=13&semester_id=3`.
  - Verifikasi 4 KPI cards, TomSelect controls, urutan kolom tabel, styling tombol aksi di kiri, live search, dan tab switching.
  - Tangkap tangkapan layar verifikasi.
