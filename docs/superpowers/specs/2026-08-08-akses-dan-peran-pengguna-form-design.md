# Spesifikasi Desain: Perombakan Form Pengguna (Users)

## Latar Belakang
Sesuai arahan, halaman Create dan Edit pada modul Pengguna (Users / Akses & Peran) akan dirombak agar setara dengan **Gold Standard SPA Profile** yang telah diterapkan pada modul Orang Tua. Hal ini bertujuan untuk menyelaraskan *UI/UX*, memberikan navigasi berbasis *tabs* yang siap untuk ekspansi (skalabilitas relasi di masa depan), serta memoles tampilan dengan gaya desain UI Premium (Hero Cards, Gradients, Alpine.js *reactivity*).

## Arsitektur & Struktur File
Perombakan ini akan menyentuh/membuat file-file berikut di dalam direktori `resources/views/admin/users/`:

1. **`edit.blade.php`** (Dirombak Total)
   - Bertindak sebagai wadah (*container*) utama dengan arsitektur **Hero Profile Card** di bagian atas (menampilkan inisial, nama, email, dan status *badge*).
   - Memiliki navigasi Tab di bawah Hero Card. Saat ini hanya ada satu tab aktif: **Profil & Identitas**, namun *layout*-nya sudah mendukung penambahan tab baru dengan mudah (seperti modul Orang Tua).
   - Menyertakan direktif Alpine.js `x-data="{ activeTab: 'profil', editMode: ... }"` untuk transisi mode Lihat vs Edit secara instan tanpa memuat ulang halaman (*SPA-like*).

2. **`create.blade.php`** (Dirombak Total)
   - Mengadopsi desain form pendaftaran *premium card*.
   - Menyajikan *header* informatif dengan ikon dan *helper text* sebelum formulir dimulai.
   - Akan menyertakan form partial (opsional) atau membangun input langsung yang senada dengan Gold Standard.

3. **`tabs/profil.blade.php`** (File Baru)
   - Parsial yang dipanggil dari `edit.blade.php`.
   - Mengendalikan tampilan **Mode Lihat** (*View Mode* - berupa *Description List* statis) dan **Mode Edit** (*Edit Mode* - berisi `<form>` pembaruan data).

4. **`_form.blade.php`** (File Baru)
   - Memisahkan input form murni (Nama, Email, Role, Password [khusus Create/opsional]) menggunakan komponen-komponen standar terbaru seperti `<x-text-input>` dan `<x-select>`.

## Keputusan Desain & UX
- **Data Minimalis, UI Maksimal**: Meskipun data `User` relatif sedikit, profilnya akan tetap ditampilkan secara elegan di *View Mode* menggunakan `grid` dan ikon Lucide/Material yang seirama dengan bagian web lainnya.
- **Konsistensi Visual**: Penggunaan palet warna `brand-500`, *badge* `emerald` untuk akun aktif, serta *shadow-card* untuk semua kartu kontainer.
- **Validasi Klien**: Pesan error *server-side* tetap tertangkap dan langsung membuka *Edit Mode* secara otomatis (jika ada *error validation* saat submit).

## Skema Kerja Implementasi
1. Ekstraksi form Pengguna ke `_form.blade.php`.
2. Pembuatan `tabs/profil.blade.php` dengan dual-mode (View/Edit).
3. Pemasangan *Hero Profile Card* dan Tab System pada `edit.blade.php`.
4. Restrukturisasi `create.blade.php`.
5. Uji coba fungsional (Create, Edit) dan kompilasi *asset* akhir.
