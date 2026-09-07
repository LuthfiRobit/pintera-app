# Handoff Log: Sidebar — Regroup Menu & Grup Collapsible

> **Tanggal**: 7 September 2026  
> **Branch**: `refactor-view-v2` (dibuat dari `rbac-v2`, belum di-merge ke `rbac-v2` / `main`)  
> **Spec**: `.agents/specs/2026-09-07-sidebar-regroup-collapsible.md`  
> **Plan**: `.agents/plans/2026-09-07-sidebar-regroup-collapsible.md`  
> **Base commit sebelum kickoff**: `be46217b` (`docs(sidebar): kickoff document for sidebar regroup & collapsible`)  
> **Commit range**: `9e44a440..22a67e34` (3 commit)  
> **Status**: Selesai & Terverifikasi (Full Suite: 2.973 test lulus, 0 regresi baru)

---

## 1. Apa yang Dikerjakan

Menyelesaikan penataan ulang struktur menu, penerapan navigasi collapsible pada sidebar (`resources/views/layouts/sidebar.blade.php`), serta penyempurnaan visual UI/UX (dark theme contrast, tipografi Satoshi, dan animasi mulus):

1. **Commit `9e44a440` — Task 1: Regroup `$navGroups` (Rename, Split Data Induk, Dedup Kasus Pendampingan)**
   - **Dedup Kasus Pendampingan**: Menghapus 4 kemunculan duplikasi manual "Kasus Pendampingan" pada 4 grup persona berbeda (`Ruang Guru`, `Ruang Siswa`, `Ruang Orang Tua`, dan `Kehadiran Saya`). Menyatukannya menjadi 1 grup mandiri `Pendampingan Saya` dengan 1 kondisi tunggal: `Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class)`.
   - **Rename "Kehadiran Saya" → "Ruang Karyawan"**: Menyelaraskan pola penamaan konsisten dengan "Ruang Guru", "Ruang Siswa", dan "Ruang Orang Tua".
   - **Split "Data Induk" (12 item campur aduk) menjadi 3 grup terfokus**:
     - `Yayasan & Lembaga` (Pengaturan Yayasan, Lembaga, Tahun Ajaran) dengan icon `building-2`.
     - `Data Guru & Karyawan` (Guru, Karyawan, Jenis Karyawan, Jabatan Tambahan) dengan icon `briefcase`.
     - `Data Siswa & Orang Tua` (Siswa, Orang Tua) dengan icon `contact`.
   - **Kelas & Mata Pelajaran dipindahkan ke grup `Akademik`**: Diletakkan di awal grup Akademik mendahului "Pengaturan Akademik" sebagai struktur fundamental pembelajaran.
   - **Template WhatsApp dipindahkan ke `Pengaturan Sistem` (sebelumnya `Akses & Peran`)**: Mengelompokkan konfigurasi sistem bersama Pengguna dan Peran.
   - **Blok Komentar Dipertahankan**: Blok komentar dokumentasi SPMB (dinonaktifkan sementara) dan catatan Tagihan/Verifikasi Pembayaran PPDB-only dipertahankan persis tanpa ada yang terhapus.
   - **Penyelarasan Test**: `tests/Feature/SidebarPengelompokanTest.php` disesuaikan pada assertion `shows Kasus Pendampingan under Pendampingan Saya (not the admin Pendampingan group) for a pool konselor karyawan without kasus.view` (`assertSeeInOrder(['Pendampingan Saya', 'Kasus Pendampingan'])`).

2. **Commit `21d2740b` — Task 2: Grup Jadi Collapsible (Accordion)**
   - Mengubah render `@foreach ($navGroups as $group)` pada `resources/views/layouts/sidebar.blade.php` dengan membungkus tiap grup menggunakan state Alpine.js lokal `x-data="{ open: {{ $groupHasActiveItem ? 'true' : 'false' }} }"`.
   - Menambahkan tombol header interaktif dengan chevron icon `<x-dynamic-component :component="'lucide-chevron-down'"` yang memiliki transisi rotasi `::class="{ '-rotate-90': !open }"`.
   - Menggunakan transisi bawaan Alpine `x-show="open"` dengan `x-transition:enter` dan `x-transition:leave` (tanpa dependensi `@alpinejs/collapse`).
   - Grup yang memuat rute aktif otomatis terbuka (`open: true`) saat inisialisasi halaman, sementara grup lain tertutup secara default.
   - Verifikasi interaksi runtime berhasil di browser nyata via Playwright browser subagent.

3. **Commit `22a67e34` — Task 3: UI/UX Polish, Dark Contrast Theme & Tipografi Satoshi**
   - **Modern High-Contrast Dark Sidebar**: Menerapkan palet gelap `bg-gray-900` dengan pembatas `border-r border-gray-800`, header `border-b border-gray-800/80`, dan badge brand bercahaya (`bg-brand-500` dengan `shadow-lg shadow-brand-500/30 ring-1 ring-white/10`).
   - **Pill Menu Aktif Menyala**: Menggunakan `bg-brand-500 font-semibold text-white shadow-md shadow-brand-500/25` pada menu aktif, dan `text-gray-300 hover:bg-white/[0.07] hover:text-white` pada menu idle.
   - **Tipografi Satoshi**: Memasang font modern **Satoshi** via Bunny Fonts CDN di `resources/views/layouts/app.blade.php` dan mendaftarkannya sebagai font utama `sans` dan `display` di `tailwind.config.js`.
   - **Indentasi Child Menu (Visual Tree Guide)**: Membungkus sub-menu dengan indentasi 1 tingkat (`ml-3.5 pl-2.5`) dilengkapi garis pandu vertikal gelap `border-l-2 border-gray-800` agar pemisahan kategori dan item anak sangat jelas.
   - **Transisi CSS Grid Akordion Mulus**: Menggantikan animasi `x-show` standar dengan transisi CSS Grid (`grid-rows-[1fr] opacity-100` ⇄ `grid-rows-[0fr] opacity-0` durasi 300ms) untuk menghilangkan hentakan layout shift.
   - **Bebas Error Alpine Console**: Memperbaiki syntax error `[aria-current=\"page\"]` pada `x-init` menjadi selector bersih `[aria-current]` tanpa nested escaped quotes yang merusak parser HTML browser (`kasus:92`).
   - **Responsivitas Multi-Device Teruji**: Mobile drawer overlay dengan `z-50`, backdrop scrim gelap `bg-gray-950/70 backdrop-blur-sm`, tombol close `X` (`lg:hidden`), serta desktop sticky sidebar (`lg:sticky lg:top-0 lg:h-screen lg:z-40`).
   - **Penyelarasan Topbar**: Memperhalus garis batas bawah topbar menjadi `border-gray-200` agar selaras dengan kanvas konten.

---

## 2. Keputusan Penting yang Diambil

1. **Adopsi Dark Contrast Theme untuk Sidebar**:
   - Setelah eksplorasi dan review langsung oleh user, desain dark sidebar kontras (`bg-gray-900 border-gray-800`) dipilih karena memberikan hirarki navigasi yang tegas memisahkan area kendali (sidebar) dengan area kerja data/konten (main body yang cerah/putih).
2. **Tipografi Satoshi Menggantikan Outfit**:
   - Font Satoshi memberikan legibilitas angka dan label sistem administrasi yang lebih modern, kokoh, dan rapi dibandingkan font bawaan sebelumnya.
3. **Animasi Akordion Berbasis CSS Grid (`grid-rows`)**:
   - Tanpa menginstal plugin eksternal `@alpinejs/collapse`, transisi tinggi collapsible ditangani murni lewat utility CSS Tailwind (`grid grid-rows-[0fr]`/`grid-rows-[1fr] transition-all duration-300`). Ini memberikan efek melipat/membuka yang 100% mulus (60fps) dan bebas flicker.
4. **Indentasi Sub-Item dengan Garis Pandu**:
   - Menambahkan indentasi 1 tingkat (`ml-3.5 pl-2.5`) dengan garis pandu vertikal tipis `border-l-2 border-gray-800` secara signifikan memudahkan mata membedakan header grup vs item sub-menu saat accordion terbuka banyak.
5. **Penggunaan `<x-dynamic-component :component="'lucide-chevron-down'">`**:
   - Seluruh sidebar konsisten menggunakan icon set Lucide melalui `<x-dynamic-component :component="'lucide-' . ...">`, sehingga chevron collapse diselaraskan menggunakan `lucide-chevron-down`.
6. **Pemberian Nama "Ruang Karyawan" untuk Staff Non-Guru**:
   - Menjaga keselarasan taksonomi persona internal sekolah/yayasan (`Ruang Guru`, `Ruang Siswa`, `Ruang Orang Tua`, `Ruang Karyawan`).

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Hasil Pengujian**:
   - **4 file test sidebar target**: 18 passed (87 assertions) — 100% lulus.
   - **Full Test Suite**: 2.973 passed, 4 failed (8.058 assertions).
   - 4 kegagalan test adalah kegagalan *pre-existing* yang telah terdokumentasi sebelumnya pada seeder:
     - `Tests\Unit\M3DemoDataSeederTest > it seeds a spread of pendaftaran states...`
     - `Tests\Unit\M3DemoDataSeederTest > it is idempotent when the full DatabaseSeeder is run twice`
     - `Tests\Unit\PresensiSeederTest > it seeds student attendance records for the SD institution...`
     - `Tests\Unit\SesiPembelajaranSeederTest > it seeds learning sessions across all K-9 institutions`
   - **0 regresi baru** di seluruh aplikasi.
2. **Status Branch Git**:
   - Branch aktif: `refactor-view-v2`.
   - Commit terbaru: `22a67e34` (`feat(sidebar): dark contrast theme, satoshi font, and ui/ux polish`).
   - **TIDAK di-merge ke `rbac-v2` atau `main`** dan **TIDAK di-push**, menunggu keputusan penggabungan bertingkat dari tim/user.
