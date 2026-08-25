# Implementation Plan — Redesain Visual Dashboard Orang Tua

- **Spec**: [`.agents/specs/2026-08-25-redesain-dashboard-orang-tua-uiux.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-25-redesain-dashboard-orang-tua-uiux.md)
- **Branch**: `rbac-v2`

---

## Proposed Tasks

### Task 1: Redesain View `resources/views/admin/dashboard/orang-tua.blade.php`
- Layout 2-kolom: `lg:grid-cols-12 gap-6`.
- Main content (`lg:col-span-7 xl:col-span-8`):
  - Modern Gradient Hero Banner dengan ilustrasi avatar/badge dan visual greeting.
  - KPI Stat Tiles Grid (Tagihan Belum Lunas, Anak Terdaftar, Presensi Anak).
  - Card "Anak Terdaftar & Profil Sekolah" dengan avatar inisial berwarna dinamis.
  - Card "Kasus Pendampingan" dengan status badges.
- Right Sidebar (`lg:col-span-5 xl:col-span-4`):
  - Widget Mini Kalender Minggu Ini (Header bulan, hari, tanggal aktif highlight).
  - Widget Timeline "Jadwal Pelajaran Hari Ini" dengan badge lingkaran jam/urutan berwarna pastel (Blue, Pink, Green, Orange) dan detail mapel/kelas.

### Task 2: Verifikasi & Test
- Jalankan `php artisan test tests/Feature/DashboardTest.php` untuk memverifikasi fungsionalitas dan kelulusan data assertion.

---

## Detailed Step Checklist

- [ ] **Task 1**: Update `resources/views/admin/dashboard/orang-tua.blade.php` dengan layout 2-kolom dan gaya visual modern acuan gambar.
- [ ] **Task 2**: Jalankan test suite `tests/Feature/DashboardTest.php` dan pastikan lulus.
- [ ] **Task 3**: Commit & update handoff log `.agents/logs/2026-08-25-redesain-dashboard-orang-tua-uiux.md`.
