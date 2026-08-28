# Handoff Log: Bottom Navigation Bar (Mobile/Tablet)

- **Slug Task**: `2026-08-28-bottom-navigation-bar`
- **Spec**: `.agents/specs/2026-08-28-bottom-navigation-bar.md`
- **Plan**: `.agents/plans/2026-08-28-bottom-navigation-bar.md`
- **Branch**: `refactor-view-v2`
- **Tanggal Selesai**: 29 Agustus 2026

---

## 1. Apa yang Dikerjakan

Telah diimplementasikan komponen **Bottom Navigation Bar (Mobile/Tablet)** dengan pendekatan visual **Icon-First Minimalist Floating Pill** untuk pengguna mobile dan tablet (< 1024px) pada akun personal (Guru, Orang Tua, dan Siswa):

1. **Komponen Blade `resources/views/layouts/bottom-nav.blade.php`**:
   - Surface floating horizontal ber-radius `rounded-full`, lebar `w-[calc(100%-24px)] max-w-3xl mx-auto`, tinggi `h-16` (64px).
   - Material `bg-white/95 backdrop-blur-sm`, `border border-gray-200`, `shadow-elevated`.
   - 5-slot grid terdistribusi rata (`grid-cols-5`) dengan ikon Lucide 24px (`w-6 h-6`, stroke 2px).
   - Menghilangkan label teks visual agar layout bersih dan tidak sesak, namun tetap menyertakan aksesibilitas penuh via `aria-label` dan `<span class="sr-only">`.
   - **Slot 3 Guru (FAB)**: Tombol lingkaran melayang `w-12 h-12` berlatar `bg-brand-500` dengan offset `-translate-y-2` dan ikon QR putih.
   - **Slot 3 Orang Tua & Siswa**: Tab datar sejajar (*flat navigation*).
   - **Slot 5 (Menu)**: Memicu pembukaan off-canvas sidebar existing (`@click="sidebarOpen = true"`).
   - **Active State Match**: Memvalidasi kombinasi nama rute dan query parameter `fitur` untuk rute `dalam-pengembangan` agar tidak terjadi multi-active indicator. Indikator aktif berupa dot kecil 4px (`h-1 w-1 rounded-full bg-brand-600 mt-1`) di bawah ikon aktif `text-brand-600`.
2. **Integrasi Layout `resources/views/layouts/app.blade.php`**:
   - Menambahkan `@include('layouts.bottom-nav')` di dalam root Alpine `div`.
   - Menambahkan clearance kondisional pada area `<main>`: `{{ $hasBottomNav ? 'pb-28 lg:pb-6' : '' }}` untuk mencegah konten dan tombol submit tertutup bar.
3. **Pengujian Komprehensif `tests/Feature/BottomNavTest.php`**:
   - 5 skenario Feature Test: render Guru (5 slot + QR FAB), render Orang Tua (5 flat slots), render Siswa (5 flat slots), eksklusi akun non-personal, dan validasi active state query parameter `fitur`.

---

## 2. Keputusan Penting yang Diambil

- **Icon-First Floating Control Bar**:
  - Berdasarkan review visual, label teks visual ditiadakan dari tampilan UI utama agar menyerupai floating control bar yang lega dan modern, namun tetap 100% accessible via `aria-label` dan screen reader.
- **Diferensiasi FAB vs Flat Navigation**:
  - Guru (*Action-Oriented*) adalah satu-satunya peran dengan FAB di Slot 3 (`sdm.qr-saya`).
  - Orang Tua (*Monitoring-Oriented*) dan Siswa (*Information-Oriented*) menggunakan navigasi datar (100% Flat) untuk menjaga suasana portal pendidikan yang tenang.
- **Single Source of Truth (Tanpa Drawer Duplikat)**:
  - Bottom Nav murni menjadi shortcut mobile 5-slot. Seluruh kewenangan RBAC dan daftar menu lengkap tetap berada di Sidebar existing yang dibuka via Slot 5.
- **Active State Query Parameter Match**:
  - Rute placeholder `dalam-pengembangan` diperiksa spesifik berdasarkan `request()->query('fitur') === '...'` untuk menghindari bug visual di mana seluruh tab placeholder aktif secara bersamaan.

---

## 3. Daftar Commit Terkait

1. `2d7ea5eb` - `feat(ui): tambah Bottom Navigation Bar (Icon-First Floating Pill) untuk Guru, Orang Tua, dan Siswa`

---

## 4. Hasil Verifikasi

- **Feature Tests**:
  - `tests/Feature/BottomNavTest.php`: **5 passed (35 assertions)**
  - `tests/Feature/SidebarPengelompokanTest.php`: **8 passed (30 assertions)**
- **Full Test Suite (`php artisan test --compact`)**:
  - **2442 passed, 4 skipped, 0 failed (6700 assertions)**
- **Formatting**:
  - `vendor/bin/pint --dirty --format agent`: Clean / Formatted.

---

## 5. Hal yang Masih Perlu Direview Manusia / Claude

- **State Git**:
  - Branch: `refactor-view-v2`
  - Status: Commit lokal `2d7ea5eb` telah dibuat (belum di-push ke remote).
- **Pengujian Visual Langsung di Browser**:
  - Desain responsif telah diuji secara markup dan unit/feature test. Pengguna disarankan menjalankan `npm run dev` atau melihat langsung di perangkat mobile/tablet untuk merasakan transisi dan ergonomi sentuhan.
