# Specification: Redesain UI/UX Halaman Kenaikan Kelas (Standar Pintera)

## 1. Tujuan & Latar Belakang
Halaman Kenaikan Kelas (`portals/lembaga/akademik/kenaikan-kelas/index.blade.php`) telah selesai diaudit dari sisi backend dan transaksi data. Namun, dari segi estetika antarmuka dan pengalaman pengguna (UI/UX), halaman ini masih memiliki format dasar/mentah yang belum selaras dengan standar Design System Pintera (`.ai/rules/components.md`, `.ai/rules/views.md`, dan benchmark dari `komponen-penilaian` serta `sarpras/pengadaan`).

Redesain ini bertujuan untuk:
1. Menyelaraskan seluruh elemen visual ke standar Pintera (Header standar, Breadcrumb, `<x-scope-badge>`, `<x-select>`, `<x-badge>`, `<x-stat-tile>`, dsb.).
2. Menyajikan ringkasan metrik (4 Pilar KPI Stat Cards) saat data tahun ajaran dimuat.
3. Menyediakan onboarding empty state yang jelas dan membimbing pengguna langkah demi langkah.
4. Menghadirkan alat produktivitas massal (*productivity helpers*): *Terapkan Rekomendasi Otomatis*, *Toggle Salin Jadwal*, dan *Pencarian Cepat Nama Kelas*.
5. Menyediakan Submission Bar interaktif dengan rekapitulasi status real-time (*X Naik, Y Lulus, Z Lewati, N Peringatan*).

## 2. Ruang Lingkup (Scope)

### In-Scope:
- **View Refactoring**: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`.
- **Form Component Adoption**: Migrasi seluruh tag `<select>` mentah ke `<x-select>`, dan badge inline ke `<x-badge>`.
- **Header & Scope**: Penggunaan `<x-scope-badge>`, subtitle deskriptif, dan breadcrumb standar.
- **KPI Metrics Bar**: 4 stat cards ringkas (Total Kelas, Total Siswa, Kelas Tingkat Akhir, Kelas Selesai/Kosong).
- **Interactive Onboarding State**: State panduan awal saat tahun ajaran belum dipilih.
- **Productivity Helpers Toolbar**: Quick-action untuk otomatisasi pemilihan tindakan, centang jadwal, dan live filter.
- **Interactive Sticky Submission Bar**: Rekapitulasi real-time jumlah kelas berdasarkan tindakan dan tombol konfirmasi yang elegan.
- **JavaScript Enhancements**: Pembaruan `resources/js/kenaikan-kelas-form.js` untuk mendukung reaktivitas Alpine.js.

### Out-of-Scope:
- Perubahan logika backend transaksi atau migrasi database (logika `ProsesKenaikanKelasAction` sudah tuntas dan tidak diubah).
- Notifikasi email/WhatsApp.

## 3. Asumsi & Batasan (Constraints)
- Semua kontrak pengujian yang ada di `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` (seperti attribute `name="mapping[id][tindakan]"`, data attributes `data-kurikulum`, `data-tingkat`, expression JS `kurikulumAsal`, `selisihIndexTingkat`, dsb.) **wajib dipertahankan 100%**.
- Komponen `<x-select>` merender tag `<select>` native dengan class standar Pintera, sehingga kompatibel penuh dengan regex parser di pengujian.
- Seluruh pengujian Pest harus tetap berstatus hijau (100% passing).

## 4. Kriteria Keberhasilan (Acceptance Criteria)
1. **Adopsi Komponen Pintera**: Seluruh input pilihan menggunakan `<x-select>` dan seluruh label status menggunakan `<x-badge>`.
2. **Visual Hierarchy & Header**: Terdapat header informatif, badge scope lembaga/yayasan jika aktif, dan breadcrumb.
3. **KPI Metrics**: 4 pilar metrik tampil rapi di atas tabel ketika tahun ajaran dipilih.
4. **Onboarding Guidance**: Saat belum memilih tahun ajaran, tampil panduan alur 3-langkah kenaikan kelas.
5. **Productivity Helpers**:
   - Tombol "Terapkan Rekomendasi" mengisi pilihan tindakan secara instan.
   - Input live search memfilter baris kelas secara instan di sisi klien.
   - Opsi "Salin Semua Jadwal" mencentang semua baris sekaligus.
6. **Submission Bar**: Ringkasan status interaktif tampil jelas dengan counter real-time.
7. **Zero Regression**: Semua test di `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` dan `tests/Feature/Admin/KenaikanKelasControllerTest.php` lolos tanpa error.
