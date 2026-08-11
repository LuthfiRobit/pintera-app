# Rencana Implementasi: UI/UX Jenis Tagihan (Gold Standard)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Menerapkan standar antarmuka "Partial SPA" untuk `index.blade.php` dan arsitektur "Sticky Sidebar Premium" untuk `form.blade.php` di modul Jenis Tagihan.

---

### Task 1: Perombakan Halaman Induk (Partial SPA)

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/index.blade.php`
- Create: `resources/views/admin/jenis-tagihan/_daftar.blade.php`

- [ ] **Step 1: Ekstraksi Tabel.** Pindahkan kode `<div class="overflow-x-auto"><table...>` beserta seluruh perulangan alpine (`<template x-for...>`) dari `index.blade.php` ke file baru `_daftar.blade.php`.
- [ ] **Step 2: Setup Partial Container.** Pada `index.blade.php`, buat kontainer penampung (misal: `<div id="table-container">`) yang memanggil `@include('admin.jenis-tagihan._daftar')`. *Catatan: Meskipun saat ini datanya di-load statis ke Alpine, pemisahan struktur sangat penting untuk pengembangan lanjutan.*
- [ ] **Step 3: Styling Premium.** Pastikan bingkai tabel di `index.blade.php` menggunakan `rounded-2xl border-gray-200 bg-white shadow-card`. Tambahkan form pencarian responsif di sebelah kanan atas judul daftar.
- [ ] **Step 4: Update Breadcrumbs.** Sesuaikan breadcrumb dengan pola sebaris standar.

### Task 2: Perombakan Halaman Form (Sticky Sidebar)

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/form.blade.php`

- [ ] **Step 1: Grid Layout Utama.** Ubah `<div class="mx-auto max-w-3xl">` menjadi `<div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,340px)_1fr] items-start">`.
- [ ] **Step 2: Sticky Sidebar (Kolom Kiri).** Buat divisi dengan kelas `sticky top-6`. Pindahkan input esensial ke sini: Judul Form, Kategori (`x-model="form.kategori"`), opsi "Bisa dicicil", Status Aktif, dan **Tombol Simpan**. Bungkus dalam *Premium Card* ber-ikon.
- [ ] **Step 3: Konten Dinamis (Kolom Kanan).** Di kolom sebelah kanan, bariskan sisa konfigurasi secara berurutan:
  - Card 1: Pengaturan Mode Generate (jika tidak PPDB).
  - Card 2: Target Sasaran (`sasaranMode`).
  - Card 3: Tarif Berdimensi.
  - Card 4: Keringanan Khusus.
- [ ] **Step 4: Micro-interactions.** Pastikan *checkbox* menggunakan `text-brand-500 focus:ring-brand-500` dan tombol dinamis menggunakan desain yang tidak terlihat kaku.

### Task 3: Verifikasi

- [ ] **Step 1: Build CSS.** Jalankan `npm run build` untuk mengompilasi desain Tailwind baru.
- [ ] **Step 2: Commit.** Masukkan hasil modifikasi form dan index ke Git history.
