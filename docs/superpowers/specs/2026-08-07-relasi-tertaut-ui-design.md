# Design Spec: Standarisasi UI Tab Relasi (Siswa & Orang Tua)

## Tujuan
Mengadopsi pola antarmuka dari tab "Riwayat Pendidikan" (milik Karyawan/Guru) untuk diterapkan pada tab relasi tertaut di halaman Siswa dan Orang Tua. Hal ini bertujuan untuk mencapai keseragaman desain 100% pada aplikasi.

## Scope (Ruang Lingkup)
1. `resources/views/admin/orang-tua/tabs/siswa.blade.php` (Tab Anak Tertaut pada Profil Orang Tua)
2. `resources/views/admin/siswa/_orang_tua.blade.php` (Parsial Orang Tua Tertaut pada Profil Siswa)
   *(Catatan: Ini dipanggil dari `admin/siswa/tabs/orang-tua.blade.php`)*

## Arsitektur UI yang Diadopsi (Opsi C)

Untuk kedua view di atas, kita akan mengubah strukturnya menjadi 3 bagian utama:

### 1. Header & Toggle Form (Action Bar)
- Terdapat judul tab di sebelah kiri.
- Terdapat tombol aksi di sebelah kanan (contoh: "Tautkan Anak" atau "Tautkan Orang Tua").
- Tombol ini menggunakan Alpine.js (`x-data="{ openAdd: false }"`, `@click="openAdd = !openAdd"`) untuk menampilkan form pencarian.

### 2. Form Pencarian & Penautan (Hidden by Default)
- Form pencarian NIK (di Siswa) atau NIS (di Orang Tua) yang awalnya tersembunyi.
- Dibungkus dengan container `bg-brand-50/20` dan animasi transisi ringan saat dibuka.
- Terdapat tombol "Batal" untuk menutup form.

### 3. Data Presentation (Empty State & Table)
- **Empty State**: Jika relasi kosong (`count() === 0`), akan tampil blok besar ke tengah dengan `<x-icon name="groups">` (atau ikon relevan), latar ikon abu-abu, dan teks panduan.
- **Tabel Data**: Jika ada relasi, daftar ditampilkan dalam elemen `<table>` (bukan lagi `<ul>`).
  - Kolom Tabel Anak: Nama Siswa, NIS, Rombel, Aksi (Hapus Tautan).
  - Kolom Tabel Orang Tua: Nama Orang Tua, Hubungan & Status, Kontak, Aksi (Jadikan Utama, Hapus Tautan).

## State Management (Alpine.js)
State yang dibutuhkan di dalam tab:
- `openAdd`: boolean (untuk form toggle).
- Logika pencarian Alpine lama (`orangTuaCari` atau `siswaCari`) tetap berjalan di dalam form wrapper, tidak ada perubahan pada logic JS yang sudah jalan, hanya perpindahan letak/bungkusan UI.
