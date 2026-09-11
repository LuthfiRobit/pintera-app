# Implementation Plan: Redesain UI/UX Halaman Kenaikan Kelas

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengangkat estetika dan pengalaman pengguna (UI/UX) halaman Kenaikan Kelas ke standar Pintera Design System (mengadopsi `<x-select>`, `<x-badge>`, `<x-stat-tile>`, `<x-scope-badge>`, onboarding guidance, productivity helpers, dan live summary bar) tanpa mematahkan kontrak pengujian apapun.

**Architecture:** 
- Frontend Blade View: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`
- Alpine.js Component: `resources/js/kenaikan-kelas-form.js`
- Test Verification: `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` & `tests/Feature/Admin/KenaikanKelasControllerTest.php`

---

## Checklist Tasks

- [x] **Task 1: Update Alpine.js Form Logic (`resources/js/kenaikan-kelas-form.js`)**
  - Tambahkan reactive state: `searchQuery`, `countNaik`, `countLulus`, `countLewati`, `countPeringatan`.
  - Tambahkan method `initForm()` / `hitungRingkasan()` untuk menghitung rekapitulasi real-time.
  - Tambahkan method helper `terapkanRekomendasiOtomatis()` untuk bulk-select tindakan.
  - Tambahkan method helper `toggleSemuaSalinJadwal(checked)` untuk mass-check salin jadwal.
  - Pastikan method `konfirmasiDanKirim()` tetap berfungsi dengan dialog konfirmasi aman.

- [x] **Task 2: Redesain Header, Filter Card & Empty State Onboarding (`index.blade.php`)**
  - Implementasikan header baru dengan `<x-scope-badge>` dan deskripsi fitur.
  - Perbarui filter card dengan visual flow: Tahun Sumber ➔ Tahun Tujuan, menggunakan `<x-select>` dan tombol submit dengan icon.
  - Tambahkan Onboarding Guidance Card (3-langkah panduan) saat parameter tahun ajaran belum dipilih.
  - Tambahkan Banner informasi jika tahun ajaran tujuan belum dipilih.

- [x] **Task 3: Implementasi KPI Metric Cards (4 Pilar Metrik) (`index.blade.php`)**
  - Tambahkan baris 4 KPI Card ketika daftar kelas lama dimuat:
    1. Total Kelas Sumber (icon `domain`)
    2. Total Siswa Terdampak (icon `groups`)
    3. Kelas Tingkat Akhir / Lulus (icon `school`, tone amber)
    4. Kelas Sudah Kosong / Selesai (icon `check_circle`, tone green)

- [x] **Task 4: Redesain Tabel Pemetaan, Form Controls & Warning Callouts (`index.blade.php`)**
  - Ganti seluruh `<select>` tabel dengan `<x-select>`.
  - Ganti seluruh badge status manual dengan `<x-badge>`.
  - Perbarui pill tingkat kelas dengan styling modern.
  - Desain ulang callout peringatan kurikulum berbeda dan peringatan tingkat dengan container mini-alert dan icon `<x-icon name="warning" />`.
  - Tambahkan toolbar helper di atas tabel: Live Search, tombol "Terapkan Rekomendasi Otomatis", dan tombol "Centang Semua Salin Jadwal".
  - Integrasikan filter baris via `x-show="matchesSearch(namaKelas)"`.

- [x] **Task 5: Implementasi Sticky Real-Time Submission Bar (`index.blade.php`)**
  - Tambahkan bar rekapitulasi status dinamis di bawah tabel dengan counter real-time (Naik, Lulus, Lewati, Peringatan).
  - Tambahkan tombol submit `<x-primary-button>` dengan icon dan loading spinner.

- [x] **Task 6: Verifikasi Otomatis (Pest Tests) & Visual Inspection (Browser Subagent)**
  - Jalankan `vendor/bin/pest tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` dan `tests/Feature/Admin/KenaikanKelasControllerTest.php`.
  - Buka halaman di browser dan ambil screenshot untuk memastikan tampilan tajam, proporsional, dan sesuai standar Pintera.
  - Jalankan Laravel Pint untuk memastikan format kode rapi.
