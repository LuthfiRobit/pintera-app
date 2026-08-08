# Perombakan Form Pengguna Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Merombak halaman Create & Edit Pengguna (Akses & Peran) menjadi setara dengan Gold Standard SPA Profile.

**Architecture:** Mengekstrak form ke `_form.blade.php`, membuat mode lihat/edit interaktif di `tabs/profil.blade.php`, dan menerapkan Hero Profile Card pada kontainer utama `edit.blade.php`. Pembaruan `create.blade.php` untuk keselarasan antarmuka.

**Tech Stack:** Laravel Blade, Tailwind CSS, Alpine.js (x-data, x-show, x-transition)

## Global Constraints

- Standar visual wajib mengacu pada `admin/orang-tua/edit.blade.php`.
- Semua elemen input wajib menggunakan tag komponen `<x-text-input>`, `<x-input-label>`, `<x-input-error>`, dan `<x-select>`.
- Validasi data sisi-klien tidak boleh mengganggu respons *error validation* dari *server-side*.

---

### Task 1: Pembuatan `_form.blade.php` (Ekstraksi Input)

**Files:**
- Create: `resources/views/admin/users/_form.blade.php`

**Interfaces:**
- Consumes: `$targetUser` (null jika dari Create, object User jika dari Edit), `$roles` (Koleksi role), `$lembaga` (Koleksi lembaga untuk dropdown - opsional khusus create)

- [ ] **Step 1: Siapkan template form dengan komponen `<x-text-input>` dan `<x-select>`.** Pindahkan isi `<form>` dari `create.blade.php` (hanya input field-nya) ke `_form.blade.php`.
- [ ] **Step 2: Sesuaikan logic binding value.** Gunakan `old('name', $targetUser?->name)` agar berfungsi di mode Create dan Edit.
- [ ] **Step 3: Tangani input Password & Lembaga.** Sembunyikan field *Password* dan *Lembaga* jika `$targetUser` tidak null (mode Edit), atau buat opsional bersyarat, karena pada form Edit Pengguna saat ini tidak ada opsi merubah *Password* maupun *Lembaga* langsung di sana.

### Task 2: Pembuatan `tabs/profil.blade.php` (View & Edit Mode)

**Files:**
- Create: `resources/views/admin/users/tabs/profil.blade.php`

**Interfaces:**
- Consumes: Variabel Alpine `activeTab`, `editMode` dari file induk, `$targetUser` dan `$roles`.

- [ ] **Step 1: Buat *container* `x-show="activeTab === 'profil'"`.**
- [ ] **Step 2: Buat *View Mode* (`x-show="!editMode"`).** Buat kartu informasi profil (Hero/Detail Card) menggunakan `dl`/`dt`/`dd` untuk menampilkan data: Nama Lengkap, Email, Role, Tanggal Terdaftar. Tambahkan tombol "Edit Profil" untuk mengaktifkan `editMode = true`.
- [ ] **Step 3: Buat *Edit Mode* (`x-show="editMode"`).** Siapkan tag `<form method="POST" action="{{ route('admin.users.update', $targetUser) }}">` dengan `@method('PUT')`. 
- [ ] **Step 4: Integrasikan Form.** Di dalam form, panggil `@include('admin.users._form')` beserta tombol Simpan dan Batal (`@click="editMode = false"`).

### Task 3: Restrukturisasi Kontainer Utama (`edit.blade.php` & `create.blade.php`)

**Files:**
- Modify: `resources/views/admin/users/edit.blade.php`
- Modify: `resources/views/admin/users/create.blade.php`

**Interfaces:**
- Consumes: File Blade *layouting*

- [ ] **Step 1: Rombak `edit.blade.php`.** Tambahkan *state* Alpine `<div x-data="{ activeTab: 'profil', editMode: {{ $errors->any() ? 'true' : 'false' }} }">`.
- [ ] **Step 2: Bangun Hero Profile Card di `edit.blade.php`.** Buat header visual dengan latar gradien, kotak avatar inisial (misal: "AD" untuk Admin), dan *badge* status aktif/non-aktif.
- [ ] **Step 3: Tambahkan Navigasi Tab.** Meskipun baru 1 tab ("Profil & Identitas"), bangun menu horizontal *border-b* agar konsisten dengan standar Orang Tua.
- [ ] **Step 4: Rombak `create.blade.php`.** Ubah gaya *panel* lamanya ke desain *Premium Card* persis seperti di `admin/orang-tua/create.blade.php` (terdapat blok biru *Header* dengan ikon). Panggil `@include('admin.users._form')` di dalamnya.

### Task 4: Verifikasi & Build

**Files:**
- Test: Verifikasi Manual dan Build Asset

- [ ] **Step 1: Kompilasi *asset* Tailwind.** Jalankan `npm run build` di terminal.
- [ ] **Step 2: Jalankan *Test Suite*.** Jalankan `php artisan test --filter UserControllerTest` (bila tersedia) untuk memastikan form valid dan struktur data HTML tidak merusak pengiriman form `POST`/`PUT`.
- [ ] **Step 3: Lakukan *commit*.** Rekam semua perubahan *views* pengguna.
