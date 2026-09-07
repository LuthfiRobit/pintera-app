# Implementation Plan: Perbaikan Halaman Index Siswa (Header Scope, Kolom Tabel & Pagination)

- **Spec**: [`.agents/specs/2026-09-07-siswa-index-ui-perbaikan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-07-siswa-index-ui-perbaikan.md)
- **Branch**: `rbac-v2`
- **Tanggal**: 7 September 2026

---

## Task 1: Header Halaman Dinamis Scope Yayasan

### 1.1 Tulis Test (TDD Merah)
Tambahkan test di `tests/Feature/Admin/SiswaCrudTest.php`:
- Test 1: User scope yayasan tanpa memilih lembaga di sesi melihat `SISWA SEMUA LEMBAGA`.
- Test 2: User scope yayasan dengan memilih lembaga aktif di sesi melihat `SISWA [NAMA LEMBAGA UPPERCASE]`.
- Test 3: User scope lembaga melihat default `Siswa`.

### 1.2 Implementasi Controller & Blade
- Di `app/Http/Controllers/Admin/SiswaController.php:index()`:
  - Hitung `$isYayasan` dan `$activeLembaga`.
  - Pass ke view `admin.siswa.index`.
- Di `resources/views/admin/siswa/index.blade.php`:
  - Perbarui bagian header `<h1>` dan badge lembaga penanda kontekstual.

### 1.3 Verifikasi Hijau
- Jalankan `php artisan test --filter=SiswaCrudTest`.

---

## Task 2: Penggabungan Kolom NIS & Nama, dan Penambahan Kolom Lembaga

### 2.1 Eager Load Relasi Lembaga
- Di `app/Http/Controllers/Admin/SiswaController.php:index()`:
  - Ubah query menjadi `Siswa::with(['lembaga', 'kelas', 'kelasTerakhir', 'person'])`.

### 2.2 Perbarui Tampilan Tabel `_daftar.blade.php`
- Ubah `<th>NIS</th>` dan `<th>Nama</th>` menjadi `<th>Siswa</th>`.
- Tambahkan `<th>Lembaga</th>`.
- Pada `<tbody>`:
  - Render nama siswa di atas (font semibold) dan NIS di bawah (font-mono text-xs text-gray-500).
  - Render nama lembaga siswa (`$siswa->lembaga?->nama ?? '—'`).
  - Colspan baris kosong tetap 6.

### 2.3 Tulis Test & Verifikasi
- Tambahkan assersi di `tests/Feature/Admin/SiswaCrudTest.php` untuk memastikan kolom Siswa (Nama + NIS) dan Lembaga dirender dengan benar.

---

## Task 3: Perbaikan Pagination Responsif

### 3.1 Perbarui `resources/views/pagination/tailadmin.blade.php`
- Pisahkan menjadi dua breakpoint layout:
  - Mobile (`< sm`): Tombol "Sebelumnya" dan "Berikutnya" yang proporsional, indikator "Hal X / Y", dan teks ringkasan jumlah entri.
  - Desktop (`>= sm`): Tampilan horizontal lengkap dengan ringkasan entri di kiri dan deretan nomor halaman di kanan.

### 3.2 Verifikasi Visual & Fungsional
- Pastikan tidak ada syntax error Blade dan layout tabel tetap rapi di desktop maupun mobile.

---

## Task 4: Final Verification, Pint, Handoff Log, & Roadmap

### 4.1 Format Code
- Jalankan `vendor/bin/pint --dirty --format agent`.

### 4.2 Jalankan Test Suite Terkait
- Jalankan `php artisan test --filter=SiswaCrudTest`.
- Pastikan tidak ada regresi.

### 4.3 Tulis Handoff Log & Update Roadmap
- Tulis `.agents/logs/2026-09-07-siswa-index-ui-perbaikan.md`.
- Perbarui `PETA_PENGEMBANGAN.md`.
