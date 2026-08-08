# Spesifikasi Desain: Perombakan UI/UX Edit Role (Option 2 - Sticky Sidebar)

## Latar Belakang & Tujuan
Halaman pengubahan peran (Edit Role) saat ini (`admin/roles/edit.blade.php`) beserta komponen *Permission Matrix*-nya dinilai tertinggal dalam standar desain (*UI/UX Pro Max*). Skema warna masih menggunakan variabel lama (`ink`, `brass`), dan komponen pembungkus menggunakan `<x-panel>` yang terkesan usang. 
Tujuan dokumen ini adalah menetapkan rancang bangun (arsitektur) desain baru yang modern, bersih, fungsional, dan interaktif (micro-interactions) dengan pendekatan *Sticky Sidebar Premium*.

## Arsitektur Layout (Option 2: Sticky Sidebar)

Alih-alih menggunakan navigasi berbasis *tab*, halaman ini akan mempertahankan struktur 2-kolom (`lg:grid-cols-[minmax(0,320px)_1fr]`), namun dengan evolusi yang signifikan:

1. **Kolom Kiri (Hero Card / Sticky Sidebar)**:
   - Akan berfungsi sebagai jangkar (anchor) form identitas.
   - Posisi akan dibuat menempel (`sticky top-6`) saat jendela digulir ke bawah, memastikan tombol `Simpan` dan Form Nama selalu dalam jangkauan pengguna.
   - Desain akan menyerupai *Hero Card* vertikal dengan efek *glassmorphism* atau gradien halus.
   - Memuat elemen: 
     - *Input* Nama Role & *Scope Level*.
     - Label status jika Role terkunci (*Protected*).
     - Tombol Aksi Utama (Simpan, Batal) yang lebih tebal (`font-bold`, `rounded-xl`, berbayang).

2. **Kolom Kanan (Permission Matrix Area)**:
   - Area matriks akan diberi jarak napas (*whitespace*) yang lapang.
   - Modul akan dipisahkan dalam bungkus (*card*) *rounded-2xl* atau *border-gray-200*.
   - *Checkboxes* akan menggunakan skema sentuhan aksen `brand-500` yang lebih terlihat (*focus ring* modern).
   - Panel peringatan audit (jika ada selisih permission di database vs kode) akan ditransformasi dari `signal-amber` menjadi komponen *soft-alert* standar (`bg-amber-50 text-amber-700`).
   - *Toolbar Matrix* (Pilih Semua, Kosongkan, Sync) akan ditempatkan pada baris tajuk (*header*) bergaya modern, dengan tombol berbingkai (`border-gray-200 hover:bg-gray-50`).

## Panduan Styling (Superpowers: `ui-styling` & `ui-ux-pro-max`)

- **Palet Warna Utama**: Tinggalkan `text-ink` dan `bg-paper`. Gunakan `text-gray-900` (untuk judul), `text-gray-500` (subteks), `bg-brand-600` (tombol primary), `border-gray-200` (garis), `bg-white` (kontainer utama).
- **Pembungkus Utama (Card)**: Ubah `<x-panel>` menjadi `<div class="rounded-2xl border border-gray-200 bg-white shadow-card">`.
- **Tipografi**: Gunakan kelas `font-display` pada tajuk matriks dan tajuk *sticky sidebar*.
- **Micro-interactions**: Implementasi `hover:shadow-elevated` pada kartu matriks modul, serta efek transisi pada tombol.
- **Konsistensi Komponen Form**: Implementasikan standar `<x-text-input>` terbaru dan desain khusus untuk elemen interaktif (Dropdown, Checkbox).

## Lingkup Perubahan File
1. `resources/views/admin/roles/edit.blade.php`: Perombakan tata letak grid dan *styling sidebar*.
2. `resources/views/admin/roles/_permission-matrix.blade.php`: Pembaruan gaya *card*, *checkbox*, dan *toolbar action*.
