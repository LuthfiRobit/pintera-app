# Rombak Modul Akses dan Peran (Role & User Management)

## Konteks
Saat ini modul **Akses dan Peran** (Role Builder dan Manajemen Akun Staff) memiliki ketidaksesuaian arsitektur dan UI/UX dibandingkan dengan *Gold Standard* (Mata Pelajaran).

1. **Desain Visual (Tema Usang):** Menggunakan palet lama (`Tailadmin`) seperti `text-ink`, `bg-paper`, `text-brass`, `text-slate`.
2. **Ketiadaan KPI Cards:** Tidak ada *Statistic Cards* di bagian atas.
3. **Arsitektur Rendering & Pagination:**
   - **Manajemen Akun (User):** Masih menggunakan *Server-Side Rendering* (SSR) murni tanpa AJAX. Tidak ada form pencarian (*Search*), ketiadaan fitur *Filter*, dan menggunakan tombol navigasi halaman bawaan Laravel (`$users->links()`).
   - **Role Builder (Role):** Menggunakan Alpine SPA untuk *fetching* data JSON, tetapi struktur tabel dan *filter* dibuat secara khusus (`x-data="rolesTable"`). Ketiadaan *dropdown* pagination standar.

## Tujuan Desain
Menyeragamkan modul Akses dan Peran menjadi 100% identik dengan modul Master Data (Mata Pelajaran), yang berarti:
1. Menggunakan tema visual yang konsisten (Tailwind `gray-900`, `brand-500`, `shadow-card`, dll).
2. Menyediakan minimal 3 metrik pada bagian KPI Cards.
3. Menggunakan form *Dropdown Pagination* standar ("Tampilkan: 10 / hal").
4. Mengubah arsitektur menjadi AJAX Partial HTML Server-Side Rendering (menggunakan fungsi global `dataTableFilter` dan partial `_daftar.blade.php`).

## Rekomendasi Pendekatan (Architecture Shift)
Untuk **Role Builder**, alih-alih mempertahankan rendering murni JSON *Client-Side*, kita akan **menghapus endpoint JSON khusus** (`/admin/roles/data`) dan memindahkan seluruh tabel menjadi `_daftar.blade.php` (AJAX Partial HTML). Hal ini memastikan keseragaman yang sejati dari sisi kode maupun presentasi UI.

Untuk **Manajemen Akun Staff**, kita akan merombak `index()` controller untuk mendukung partial view, serta merombak antarmuka pencarian agar memiliki komponen navigasi yang modern.
