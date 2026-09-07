# Handoff Log: Sidebar — Regroup Menu & Grup Collapsible

> **Tanggal**: 7 September 2026  
> **Branch**: `refactor-view-v2` (dibuat dari `rbac-v2`, belum di-merge ke `rbac-v2` / `main`)  
> **Spec**: `.agents/specs/2026-09-07-sidebar-regroup-collapsible.md`  
> **Plan**: `.agents/plans/2026-09-07-sidebar-regroup-collapsible.md`  
> **Base commit sebelum kickoff**: `be46217b` (`docs(sidebar): kickoff document for sidebar regroup & collapsible`)  
> **Commit range**: `9e44a440..21d2740b` (2 commit)  
> **Status**: Selesai & Terverifikasi (Full Suite: 2.973 test lulus, 0 regresi baru)

---

## 1. Apa yang Dikerjakan

Menyelesaikan penataan ulang struktur menu dan penerapan navigasi collapsible pada sidebar (`resources/views/layouts/sidebar.blade.php`) tanpa mengubah palet warna, tipografi, radius, ataupun token desain Tailwind/TailAdmin existing:

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

---

## 2. Keputusan Penting yang Diambil

1. **Tidak Menggunakan `@alpinejs/collapse`**:
   - Dikonfirmasi dari `package.json` dan skrip JS bahwa plugin `@alpinejs/collapse` belum terpasang di repository ini.
   - Implementasi collapsible menggunakan `x-show="open"` dan utility class `x-transition` bawaan Alpine tanpa menambah library pihak ketiga atau mengubah dependensi project.
2. **Penggunaan `<x-dynamic-component :component="'lucide-chevron-down'">`**:
   - Komponen `<x-icon>` di project ini (`resources/views/components/icon.blade.php`) menggunakan skema Material Symbols dan tidak memiliki aset `chevron-down` (akan jatuh ke fallback `@default` / placeholder ikon salah).
   - Seluruh sidebar konsisten menggunakan icon set Lucide melalui `<x-dynamic-component :component="'lucide-' . ...">`, sehingga chevron collapse diselaraskan menggunakan `lucide-chevron-down`.
3. **Pemberian Nama "Ruang Karyawan" untuk Staff Non-Guru**:
   - Walau isinya saat ini QR Kehadiran Saya dan Izin/Cuti Saya, penamaan "Ruang Karyawan" dipilih untuk menjaga keselarasan taksonomi persona internal sekolah/yayasan (`Ruang Guru`, `Ruang Siswa`, `Ruang Orang Tua`, `Ruang Karyawan`).
4. **Desain Visual Tidak Diubah (Strict Scope Boundary)**:
   - Sesuai arahan kickoff dan spec, warna, tipografi, radius kartu, dan shadow tidak diotak-atik sama sekali. Kebutuhan visual restyle dipisahkan sebagai pekerjaan terpisah yang menunggu preferensi konkret dari client.

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
   - **TIDAK di-merge ke `rbac-v2` atau `main`** dan **TIDAK di-push**, menunggu keputusan penggabungan bertingkat dari tim/user.
