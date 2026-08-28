# Bottom Navigation Bar (Mobile/Tablet) — Design & Engineering Spec

**Tanggal**: 2026-08-28  
**Branch**: `rbac-v2` (atau branch baru yang ditentukan saat writing-plan)  
**Konteks**: Menyediakan navigasi bawah melayang (*Floating Pill Navigation Bar*) pada perangkat mobile dan tablet (< 1024px) dengan pendekatan visual **Icon-First / Minimalist Floating Control Bar**, terintegrasi langsung dengan Sidebar sebagai *Single Source of Truth* tanpa duplikasi drawer.

---

## 1. Latar Belakang & Prinsip Arsitektur

### 1.1 — Single Source of Truth (Sidebar sebagai SSoT)
Bottom Navigation Bar **BUKAN** sistem navigasi atau permission mandiri.
- **Sidebar** adalah *Single Source of Truth* untuk seluruh hierarki navigasi, modul aplikasi, dan evaluasi permission RBAC.
- **Bottom Nav** adalah **representasi visual minimalis 5-slot** untuk akses cepat fitur personal yang paling sering dibuka di mobile.
- **Slot 5 ("Menu")** memicu pembukaan off-canvas sidebar existing (`sidebarOpen = true`), memberikan akses 100% ke seluruh menu sekunder, musiman, dan manajerial tanpa drawer kedua.

### 1.2 — Diferensiasi Karakter Pengguna (Action vs Monitoring vs Information)
1. **Guru (*Action-Oriented*)**: Memiliki aksi fisik rutin (menunjukkan QR kehadiran 2x/hari saat datang dan pulang). Guru adalah **satu-satunya peran dengan circular FAB melayang (48–52px)** di Slot 3.
2. **Orang Tua (*Monitoring-Oriented*)**: Mayoritas interaksi adalah memantau capaian belajar, presensi, dan jadwal anak. Transaksi SPP bersifat periodik (bulanan). Oleh karena itu, Slot 3 (*Tagihan*) adalah **Tab Datar (Flat)** untuk menjaga aplikasi tetap bernuansa portal kemitraan pendidikan yang tenang.
3. **Siswa (*Information-Oriented*)**: Interaksi bersifat **100% Read-Only (Konsumsi Informasi)**. Seluruh 5 slot siswa adalah **Tab Datar (Flat)** tanpa tombol aksi semu.
4. **Admin & Staf Non-Personal**: **Tidak menggunakan Bottom Nav**; navigasi mobile tetap mengandalkan Topbar Burger Button dan Sidebar Mobile standar.

---

## 2. Pemetaan 5-Slot & Konteks Personal

### 2.1 — Presedensi Konteks Personal
Bottom Nav mengikuti urutan presedensi identitas:
1. `Auth::user()->hasRole('guru')` → Render Bottom Nav **Guru**
2. `Auth::user()->hasRole('siswa')` → Render Bottom Nav **Siswa**
3. `Auth::user()->orangTua !== null` → Render Bottom Nav **Orang Tua**
4. Selain ketiganya (Admin murni, Staff TU, Satpam, Yayasan) → **Tidak Menampilkan Bottom Nav**.

### 2.2 — Matriks Kurasi 5-Slot per Peran

| Slot | Guru | Orang Tua | Siswa | Tipe Visual |
|---|---|---|---|---|
| **Slot 1** | **Beranda** (`dashboard`) | **Beranda** (`dashboard`) | **Beranda** (`dashboard`) | Tab Flat |
| **Slot 2** | **Jurnal** (`guru.jurnal-kbm.index`) | **Nilai** (`dalam-pengembangan?fitur=nilai-anak`) | **Jadwal** (`dalam-pengembangan?fitur=jadwal-pelajaran`) | Tab Flat |
| **Slot 3** | **QR Saya** (`sdm.qr-saya`) | **Tagihan** (`keuangan.dashboard`) | **Presensi** (`dalam-pengembangan?fitur=presensi-saya`) | **Guru: FAB (48–52px)**<br>Orang Tua & Siswa: Tab Flat |
| **Slot 4** | **Nilai** (`guru.asesmen.index`) | **Presensi** (`dalam-pengembangan?fitur=riwayat-izin-sakit-anak`) | **Nilai** (`dalam-pengembangan?fitur=nilai-rapor`) | Tab Flat |
| **Slot 5** | **Menu** (`@click="sidebarOpen = true"`) | **Menu** (`@click="sidebarOpen = true"`) | **Menu** (`@click="sidebarOpen = true"`) | Drawer Trigger |

---

## 3. Spesifikasi Visual & Aksesibilitas (Icon-First Floating Pill)

### 3.1 — Surface & Container Geometri
```text
┌─────────────────────────────────────────────────────────────┐
│                 ORANG TUA & SISWA (FLAT)                    │
│   ╭─────────────────────────────────────────────────────╮   │
│   │                                                     │   │
│   │      [ 🏠 ]     [ 🏆 ]     [ 👛 ]     [ ✓ ]    [ ☰ ]  │   │
│   │                   •                                 │   │
│   │                                                     │   │
│   ╰─────────────────────────────────────────────────────╯   │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                       GURU (FAB)                            │
│   ╭─────────────────────────────────────────────────────╮   │
│   │                            ╭───╮                    │   │
│   │      [ 🏠 ]     [ 📝 ]     │QR │      [ 🏆 ]   [ ☰ ]  │   │
│   │                            ╰───╯                    │   │
│   │                                                     │   │
│   ╰─────────────────────────────────────────────────────╯   │
└─────────────────────────────────────────────────────────────┘
```

- **Bentuk**: Floating Pill penuh (`rounded-full`).
- **Dimensi**:
  - Tinggi Container: `h-16` (64px).
  - Lebar: `w-[calc(100%-24px)] max-w-3xl mx-auto` (horizontal lega di mobile dan sangat proporsional di tablet portrait).
  - Posisi: `fixed bottom-3 inset-x-3 z-20 lg:hidden`.
  - Formula Safe-Area: `style="bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));"`.
- **Material & Warna**:
  - Background: `bg-white/95 backdrop-blur-sm` (putih solid mengambang, bukan glassmorphism transparan berlebih).
  - Border: `border border-gray-200`.
  - Shadow: `shadow-elevated` / `shadow-[0_4px_20px_rgba(15,23,42,0.06)]`.
- **Grid**: `grid grid-cols-5 items-center h-full`.

### 3.2 — Ikon & Aksesibilitas (Icon-First)
- **Ukuran Ikon**: `w-6 h-6` (24px) dengan `stroke-width="2"`.
- **Ikon Set (Lucide)**:
  - Beranda: `lucide-layout-dashboard`
  - Jurnal: `lucide-file-pen`
  - QR Saya: `lucide-qr-code`
  - Nilai: `lucide-award`
  - Tagihan: `lucide-wallet`
  - Presensi: `lucide-clipboard-check`
  - Jadwal: `lucide-calendar-clock`
  - Menu: `lucide-menu`
- **Aksesibilitas (Semantic Label)**:
  - Label teks **tidak ditampilkan secara visual** agar navigasi tetap bersih, lega, dan modern.
  - Setiap button/link **wajib** memiliki `aria-label="..."` dan `<span class="sr-only">Label</span>` untuk screen reader.

### 3.3 — Active & Inactive States
- **Active State**:
  - Ikon: `text-brand-600`.
  - Indikator: Titik kecil 4px (`h-1 w-1 rounded-full bg-brand-600 mt-1`) terpusat di bawah ikon aktif.
  - Tanpa background pill per item (mencegah penumpukan rounded shape).
- **Inactive State**:
  - Ikon: `text-gray-500` (terbaca jelas, tidak terlalu pudar).
- **Interaksi Sentuh**:
  - `active:scale-[0.96] transition-transform duration-150 ease-out`.
  - `focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30`.

### 3.4 — Logika Penentuan Active State (Anti-Bug Multi-Placeholder)
Untuk mencegah seluruh item `dalam-pengembangan` aktif bersamaan, evaluasi aktif harus memvalidasi kombinasi rute **DAN** query parameter `fitur`:

```php
@php
    $isBerandaActive = request()->routeIs('dashboard');
    $isJurnalActive = request()->routeIs('guru.jurnal-kbm.*');
    $isQrActive = request()->routeIs('sdm.qr-saya*');
    $isNilaiGuruActive = request()->routeIs('guru.asesmen.*');
    $isTagihanActive = request()->routeIs('keuangan.*');

    // Placeholder query-aware active match:
    $isNilaiOrtuActive = request()->routeIs('dalam-pengembangan') && request()->query('fitur') === 'nilai-anak';
    $isPresensiOrtuActive = request()->routeIs('dalam-pengembangan') && request()->query('fitur') === 'riwayat-izin-sakit-anak';
    $isJadwalSiswaActive = request()->routeIs('dalam-pengembangan') && request()->query('fitur') === 'jadwal-pelajaran';
    $isPresensiSiswaActive = request()->routeIs('dalam-pengembangan') && request()->query('fitur') === 'presensi-saya';
    $isNilaiSiswaActive = request()->routeIs('dalam-pengembangan') && request()->query('fitur') === 'nilai-rapor';
@endphp
```

### 3.5 — Geometri FAB Guru (Slot 3)
- Ukuran: Lingkaran diameter **48px–52px** (`w-12 h-12` atau `w-[52px] h-[52px]`), `rounded-full`.
- Elevasi & Offset: `relative -translate-y-2 flex items-center justify-center bg-brand-500 text-white shadow-md hover:bg-brand-600 active:scale-95 transition-transform duration-150`.
- Tanpa ring putih 4px yang tebal dan tanpa label teks di bawahnya (satu focal circular action yang bersih).
- Ikon: `lucide-qr-code` (`w-6 h-6 text-white`).

---

## 4. Perilaku Responsif, Layout Clearance & Keyboard

### 4.1 — Breakpoints
- **`< 1024px` (`lg:hidden`)**: Bottom Nav aktif pada Smartphone Portrait/Landscape dan Tablet Portrait.
- **`>= 1024px` (`hidden lg:hidden`)**: Bottom Nav disembunyikan total; navigasi beralih ke Sidebar desktop permanen di kiri.

### 4.2 — Kompensasi Layout `<main>` (Anti Content-Clipping)
- Elemen `<main>` di `resources/views/layouts/app.blade.php` memiliki bottom clearance kondisional:
  ```blade
  @php
      $showBottomNav = Auth::check() && (
          Auth::user()->hasRole('guru') || 
          Auth::user()->hasRole('siswa') || 
          Auth::user()->orangTua !== null
      );
  @endphp

  <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 {{ $showBottomNav ? 'pb-28 lg:pb-6' : '' }}">
      {{ $slot }}
  </main>
  ```
- Nilai `pb-28` (~112px) menjamin seluruh tombol simpan/submit di bagian paling bawah form, pagination link di bawah tabel, dan footer tidak akan pernah tertutup oleh floating bar.

### 4.3 — Drawer Transition (Harmoni dengan Sidebar)
- Z-Index hierarchy:
  - Bottom Nav: `z-20`
  - Mobile Scrim (Backdrop): `z-30`
  - Sidebar Aside: `z-40`
- Ketika Slot 5 (*Menu*) ditekan (`sidebarOpen = true`), scrim `z-30` dan aside `z-40` otomatis berada di atas Bottom Nav (`z-20`), sehingga tidak terjadi bentrok visual.

### 4.4 — Perilaku Keyboard Mobile
- Bottom Nav tidak boleh menjadi penghalang terhadap input form yang sedang fokus.
- Menggunakan posisi `fixed` standar Tailwind. Jika ditemukan kasus khusus pada perangkat tertentu saat pengujian, optimasi keyboard dismiss akan ditangani pada iterasi UX terpisah tanpa over-engineering di fase awal.

---

## 5. Non-Goals (Eksplisit di Luar Scope)

- **Tidak** membuat controller atau endpoint baru untuk fitur-fitur placeholder Siswa/Orang Tua (tetap mengarah ke `route('dalam-pengembangan', ['fitur' => ...])` yang sudah ada).
- **Tidak** membuat komponen drawer off-canvas baru (tetap memanfaatkan `sidebar.blade.php` existing).
- **Tidak** mengubah skema database, migration, model, atau konfigurasi RBAC/spatie roles.
- **Tidak** mengubah logika otorisasi backend mana pun.

---

## 6. Acceptance Criteria & Rencana Pengujian

### 6.1 — Otorisasi & Visibilitas Role
1. **Guru**: Login sebagai user ber-role `guru` → Bottom Nav tampil dengan 5 item: `Beranda`, `Jurnal`, `QR Saya` (FAB), `Nilai`, `Menu`.
2. **Orang Tua**: Login sebagai user yang memiliki relasi `orangTua` → Bottom Nav tampil dengan 5 item: `Beranda`, `Nilai`, `Tagihan` (Flat), `Presensi`, `Menu`.
3. **Siswa**: Login sebagai user ber-role `siswa` → Bottom Nav tampil dengan 5 item: `Beranda`, `Jadwal`, `Presensi` (Flat), `Nilai`, `Menu`.
4. **Admin / Non-Personal**: Login sebagai user murni `admin`, `yayasan`, atau staf tanpa identitas personal → Bottom Nav **TIDAK dirender** di HTML.
5. **Multi-Role Precedence**: User dengan peran ganda `guru` + `wakasek_kurikulum` → Mendapatkan Bottom Nav Guru. Slot 5 tetap membuka sidebar lengkap dengan seluruh menu manajerial wakasek kurikulum.

### 6.2 — Navigasi & Active State
1. Akses halaman `/dashboard` sebagai Guru → Slot 1 (*Beranda*) aktif (ikon `text-brand-600` + dot indicator).
2. Akses halaman `/guru/jurnal-kbm` sebagai Guru → Slot 2 (*Jurnal*) aktif.
3. Akses halaman `/sdm/qr-saya` sebagai Guru → Menampilkan halaman QR code guru.
4. Akses halaman `/keuangan` sebagai Orang Tua → Slot 3 (*Tagihan*) aktif.
5. Akses salah satu placeholder siswa (`/dalam-pengembangan?fitur=jadwal-pelajaran`) → **HANYA Slot 2 (*Jadwal*) yang aktif**; Slot 3 (*Presensi*) dan Slot 4 (*Nilai*) tetap inaktif.

### 6.3 — Aksesibilitas & UI Interaction
1. Setiap item navigasi memiliki atribut `aria-label` yang sesuai dan elemen `<span class="sr-only">`.
2. Tombol Slot 5 (*Menu*) memiliki atribut `@click="sidebarOpen = true"` dan `aria-label="Buka menu"`.
3. Menekan tombol Slot 5 membuka off-canvas sidebar existing dan menampilkan seluruh menu lengkap.
4. Bottom Nav memiliki class `lg:hidden` sehingga tidak tampil pada viewport desktop (>= 1024px).
5. Bentuk container adalah `rounded-full` dengan lebar `max-w-3xl`.

---

## 7. Ringkasan File yang Terlibat

```text
resources/views/layouts/bottom-nav.blade.php   [BARU: Komponen Bottom Navigation Bar 5-slot Icon-First]
resources/views/layouts/app.blade.php          [MODIFIKASI: Include bottom-nav + pb-28 kondisional]
tests/Feature/BottomNavTest.php               [BARU: Test render, role precedence & active query matching]
```
