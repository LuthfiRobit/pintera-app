# Sub-Task 03c — Filter Lengkap & Kolom NIS Halaman Rekap Kehadiran — Implementation Plan

**Goal:** Menstandarisasi filter (Tahun Ajaran, Semester, Kelas) dan menyertakan kolom NIS pada halaman Rekap Kehadiran Guru Wali Kelas (`/guru/rekap-kehadiran`).

---

## Task 1: Update `PresensiAggregationService` & Unit Test

- Modify: `app/Domains/Akademik/Services/PresensiAggregationService.php`
- Modify: `tests/Unit/Services/PresensiAggregationServiceTest.php`

- [x] **Step 1: Update unit test `PresensiAggregationServiceTest.php` untuk memvalidasi `nis`**
- [x] **Step 2: Update `PresensiAggregationService.php` dengan field `nis` dan nullable `$semester`**
- [x] **Step 3: Jalankan `php artisan test tests/Unit/Services/PresensiAggregationServiceTest.php`**
- [x] **Step 4: Commit**

---

## Task 2: Update `RekapKehadiranController` & View `rekap.blade.php`

- Modify: `app/Http/Controllers/Guru/Akademik/RekapKehadiranController.php`
- Modify: `resources/views/portals/guru/akademik/jurnal-kbm/rekap.blade.php`
- Modify: `tests/Feature/Guru/RekapKehadiranControllerTest.php`

- [x] **Step 1: Update `RekapKehadiranController.php` untuk mendukung filter Tahun Ajaran, Semester, dan Kelas**
- [x] **Step 2: Update `resources/views/portals/guru/akademik/jurnal-kbm/rekap.blade.php` dengan filter UI dan kolom NIS**
- [x] **Step 3: Update feature test `tests/Feature/Guru/RekapKehadiranControllerTest.php`**
- [x] **Step 4: Jalankan scoped test: `php artisan test tests/Feature/Guru/RekapKehadiranControllerTest.php tests/Unit/Services/PresensiAggregationServiceTest.php`**
- [x] **Step 5: Commit**

---

## Task 3: Handoff Log & Verifikasi

- Create: `.agents/logs/2026-08-19-0545-akademik-03c-filter-dan-nis-rekap-kehadiran.md`

- [x] **Step 1: Jalankan scoped regression tests**
- [x] **Step 2: Tulis handoff log**
- [x] **Step 3: Commit**
