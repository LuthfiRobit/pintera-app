# Handoff Log: Redesain UI/UX Halaman Kenaikan Kelas

- **Tanggal:** 2026-09-11
- **File Spec:** [`.agents/specs/2026-09-11-kenaikan-kelas-ui-redesign.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-11-kenaikan-kelas-ui-redesign.md)
- **File Plan:** [`.agents/plans/2026-09-11-kenaikan-kelas-ui-redesign.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-11-kenaikan-kelas-ui-redesign.md)
- **Git Branch:** `rbac-v2` (committed: `c224d23d`)

---

## 1. Apa yang dikerjakan

1. **Header & Visual Context**:
   - Menambahkan header terpadu dengan judul `Kenaikan & Kelulusan Kelas`, subtitle deskriptif alur tahunan, breadcrumb `Beranda › Akademik › Kenaikan Kelas`, dan integrasi `<x-scope-badge>`.
2. **Card Parameter Tahun Ajaran**:
   - Memodernisasi filter card dengan `<x-select>` standar, grid 2 kolom dengan konektor visual `arrow_forward` dari Tahun Ajaran Sumber ke Tahun Ajaran Tujuan, tombol reset pilihan, dan tombol pencarian berikon.
3. **Empty / Initial State Onboarding**:
   - Menyediakan 3-Step Onboarding Stepper Cards saat parameter tahun ajaran belum dipilih, memandu operator sekolah langkah demi langkah.
4. **4 Pilar KPI Metric Cards**:
   - Menampilkan ringkasan metrik instan saat kelas lama dimuat: Total Kelas Sumber, Siswa Terdampak (aktif), Kelas Tingkat Akhir (rekomendasi lulus), dan Kelas Sudah Kosong (otomatis dilewati).
5. **Productivity Helpers & Live Search**:
   - Menambahkan tombol *"Terapkan Rekomendasi Otomatis"* untuk mengisi seluruh dropdown tindakan secara otomatis sesuai status terminal dan ketiadaan siswa.
   - Menambahkan live search filter nama kelas menggunakan Alpine.js tanpa reload.
   - Menambahkan mass-toggle *"Centang Semua Salin Jadwal"*.
6. **Table & Form Controls Migration + TomSelect Styling Enhancement**:
   - Mengintegrasikan **TomSelect** standar Pintera (`.ts-wrapper`, `.ts-control`, `.ts-compact`, dan floating `.ts-dropdown`) pada:
     - Filter Parameter Tahun Ajaran Sumber dan Tujuan.
     - Dropdown tindakan per baris (`lewati`, `naik`, `lulus`).
     - Dropdown pemilihan Kelas Tujuan (lengkap dengan search realtime).
     - Dropdown Salin Jadwal ke Semester.
   - Menggunakan konfigurasi `dropdownParent: 'body'` sehingga pop-up opsi melayang bebas di atas tabel tanpa terpotong oleh `overflow-x-auto`.
   - Menghilangkan tampilan dropdown kaku bawaan OS browser, digantikan dengan floating card ber-border halus, rounded 8px/10px, bayangan soft `0 20px 44px rgba(30, 58, 95, 0.14)`, chevron SVG `#98A2B3`, dan font Outfit seragam.
   - Mengganti styling status badge manual dengan `<x-badge>`.
   - Menata ulang callout peringatan kurikulum berbeda dan selisih tingkat yang mencolok dengan container alert dan ikon `<x-icon name="warning" />`.
7. **Sticky / Elevated Real-Time Submission Bar**:
   - Menghadirkan footer terpadu dengan live pill counters (*X Kelas Naik, Y Kelas Lulus, Z Dilewati, N Peringatan*) dan tombol submit dengan spinner loading.

8. **Perbaikan 3 Masalah UI/UX & Fungsionalitas Lanjutan**:
   - **Render Icon SVG:** Menambahkan implementasi ikon `tune`, `swap_horiz`, `auto_fix_high` / `magic`, dan `help_outline` / `help` ke dalam `resources/views/components/icon.blade.php`. Seluruh ikon kini ter-render sebagai SVG vektor murni tanpa fallback placeholder tanda tanya.
   - **Tooltip "Salin Jadwal ke Semester" Tanpa Terpotong:** Memperbarui `resources/views/components/tooltip.blade.php` agar menggunakan `<template x-teleport="body">` dan plugin Alpine Anchor (`x-anchor`), memposisikan tooltip melayang di `<body>` secara dinamis. Tooltip tidak lagi terpotong (`overflow: hidden` / `overflow-x: auto`) oleh container tabel atau berada di balik header. Trigger juga ditingkatkan menjadi `<button>` dengan dukungan klik toggle dan atribut native `title`.
   - **Engine Rekomendasi Otomatis Cerdas & Reaktif:**
     - Menjadikan tombol *"Terapkan Rekomendasi Otomatis"* (`#btn-rekomendasi-otomatis`) bekerja aktif mengisi:
       - **Tindakan:** Tingkat akhir diset `lulus`, non-tingkat akhir diset `naik`.
       - **Kelas Tujuan:** Otomatis mencocokkan kelas di tingkat berikutnya berdasarkan kesamaan akhiran/suffix (misal: Kelas 1-A dipetakan ke Kelas 2-A, Kelas 1-B ke Kelas 2-B, dst.). Untuk kelas lulus, kelas tujuan otomatis dikosongkan.
       - **Semester Tujuan:** Otomatis memilih semester pertama yang valid.
     - Memperbaiki binding reaktivitas Alpine `this.countNaik`, `this.countLulus`, dsb. sehingga bar Ringkasan di bagian bawah langsung terupdate secara real-time.
     - Meng-export `initRowSelect` secara global (`window.initRowSelect`) dan memanggilnya secara aman di Blade (`x-init="window.initRowSelect && window.initRowSelect($el)"`), mengeliminasi `ReferenceError: initRowSelect is not defined`.

---

## 2. Keputusan penting yang diambil

1. **Mempertahankan 100% Kontrak Pengujian UX**:
   - Pengujian `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` membedah substring HTML berdasarkan `strpos($html, $namaKelas)`. Untuk mencegah pemotongan window regex 3000 karakter, nama kelas tidak disisipkan ke dalam atribut tag pembuka `<tr ...>`, melainkan diletakkan pada tag `<td>` pertama (`data-nama="..."`). Hal ini menjaga seluruh 28 test tetap lulus (110 assertions) sembari memberikan ekstraksi nama kelas yang bersih untuk fungsi auto-recommendation.
2. **Teleportasi Tooltip ke Body via Floating UI**:
   - Sama seperti komponen `table-actions.blade.php`, tooltip di-teleportasi langsung ke `<body>` sehingga bebas dari segala bentuk clipping akibat `overflow-x-auto` pada tabel responsive.
3. **Pemberian Data Scope Lembaga & Yayasan**:
   - Menambahkan resolusi `isYayasan` dan `activeLembaga` di `KenaikanKelasController::index()` agar `<x-scope-badge>` menampilkan badge lembaga/semua lembaga secara dinamis bagi pengguna yayasan.

---

## 2a. Perbaikan Pasca-Review: Regresi `<x-tooltip>` Lintas Halaman

Review Claude atas log ini (sebelum commit) menemukan 1 temuan **Critical** yang tidak tercatat di draft awal: perubahan trigger `<x-tooltip>` dari `<div>` menjadi `<button type="button">` (untuk mendukung klik-toggle + `x-anchor` di Kenaikan Kelas) mengubah kontrak komponen **bersama** yang dipakai 15 file di seluruh aplikasi, bukan cuma Kenaikan Kelas. Minimal 7 pemanggil lain menaruh elemen interaktif (link, button, atau form) di dalam slot tooltip — HTML5 melarang `<button>` berisi elemen interaktif lain, dan pada 3 kasus (2 `<button>` bersarang, 1 `<form>` bersarang) browser otomatis menutup tombol terluar lebih awal, berisiko merusak DOM/handler klik tombol "Tambah Jenis Karyawan", "Tambah Jabatan", dan aksi generate-akun-massal di halaman Siswa.

**Perbaikan**: `resources/views/components/tooltip.blade.php` diberi prop opsional `asButton` (default `false`):
- 14 pemanggil existing (Users, Karyawan, Roles, Orang Tua, Jenis Karyawan Master, Jabatan Tambahan Master, Siswa, Komponen Penilaian, Rapor, dll) **tidak diubah** — tetap trigger `<span>` non-interaktif (hover/focus), aman membungkus elemen apa pun, sekaligus tetap mendapat bonus perbaikan positioning (`x-anchor` + teleport ke `<body>`, bebas terpotong `overflow`) tanpa risiko.
- Pemanggil di Kenaikan Kelas (`index.blade.php:296`, tooltip ikon bantuan kolom "Salin Jadwal ke Semester") diberi atribut `as-button` eksplisit — aman karena isinya cuma `<x-icon>`, bukan elemen interaktif lain.
- Atribut `title="{{ $text }}"` yang sempat ditambahkan ke trigger dihapus (menyebabkan tooltip native browser & custom tampil dobel).

**Verifikasi setelah perbaikan**: `npm run build` sukses, `vendor/bin/pint` bersih, test scoped Kenaikan Kelas (4 file: `KenaikanKelasControllerUxTest`, `KenaikanKelasControllerTest`, `ProsesKenaikanKelasActionTest`, `KenaikanKelasIndicatorTest`) — **36 passed**, identik dengan baseline sebelum redesain ini. Full suite proyek TIDAK dijalankan ulang untuk perubahan ini (di luar scope permintaan review).

---

## 3. Hal yang masih perlu direview manusia/Claude

1. **Kompilasi Aset Frontend di Lingkungan Produksi**:
   - Telah dijalankan `npm run build` (via `cmd.exe /c "npm run build"`) dan berhasil menghasilkan manifest Vite terbaru. Pastikan di server produksi aset di-compile dengan `npm run build`.
2. **Git State**:
   - Branch: `rbac-v2`.
   - File yang diubah:
     - `app/Http/Controllers/Admin/KenaikanKelasController.php`
     - `resources/css/app.css`
     - `resources/js/app.js`
     - `resources/js/kenaikan-kelas-form.js`
     - `resources/views/components/icon.blade.php`
     - `resources/views/components/tooltip.blade.php`
     - `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`
   - File baru:
     - `.agents/specs/2026-09-11-kenaikan-kelas-ui-redesign.md`
     - `.agents/plans/2026-09-11-kenaikan-kelas-ui-redesign.md`
     - `.agents/logs/2026-09-11-kenaikan-kelas-ui-redesign.md`
