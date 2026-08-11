# Spesifikasi Desain: Perombakan UI/UX Jenis Tagihan (Gold Standard)

## Tujuan
Standarisasi halaman Induk (`index.blade.php`) dan Formulir (`form.blade.php`) Jenis Tagihan agar sesuai dengan **Gold Standard SPA Profile** yang telah diimplementasikan pada modul Siswa, Pengguna, dan Peran. Menggunakan prinsip keindahan visual (*UI/UX Pro Max*) dan fungsionalitas asinkron (*Partial SPA*).

## Spesifikasi Arsitektur

### 1. Halaman Induk (Index)
- **Konsep**: Partial SPA
- **Mekanisme**: Memisahkan kerangka tabel ke dalam file mandiri `_daftar.blade.php`.
- **Integrasi**: Menggunakan fungsi Alpine.js untuk memuat data tabel secara *AJAX Fetch* (`@ajax-start.window` dan `@ajax-end.window`) tanpa me-*reload* halaman secara penuh.
- **Visual**:
  - Penambahan komponen pencarian responsif.
  - Membungkus tabel dengan `rounded-2xl border-gray-200 shadow-card`.
  - Warna status tagihan (Dipakai/Belum) harus menggunakan lencana (*badge*) bernuansa `brand-50 text-brand-600` dan `gray-100 text-gray-600`.

### 2. Halaman Formulir (Create/Edit)
- **Konsep**: Opsi 1 (Sticky Sidebar Premium)
- **Layout**: Grid 2-Kolom (`lg:grid-cols-[minmax(0,340px)_1fr]`).
- **Kolom Kiri (Sticky Top)**:
  - Berfungsi sebagai "Hero Card" sekaligus pusat aksi.
  - Menampilkan Judul Form, status aktif, opsi cicilan, kategori, dan tombol "Simpan Perubahan".
  - Properti `sticky top-6` agar tombol simpan tetap terjangkau saat kolom kanan bergulir panjang.
- **Kolom Kanan (Konten Dinamis)**:
  - Berisi seksi-seksi yang lebih kompleks: "Mode Generate Otomatis", "Target Sasaran", "Tarif Berdimensi", dan "Keringanan".
  - Dibungkus dalam komponen kartu (`bg-white shadow-card`) yang terpisah satu sama lain dengan jarak lega (`space-y-6`).
  - Interaksi `x-for` Alpine.js yang sudah ada tidak boleh rusak, hanya dibungkus dalam *markup* yang lebih *premium*.

## Batasan (Constraints)
- Tetap gunakan Alpine.js `jenisTagihanTable` dan `jenisTagihanForm` yang ada.
- Jangan merusak logika validasi atau struktur JSON `sasaran` dan `tarif` bawaan.
