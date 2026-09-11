# Plan: 5 Perbaikan UI/UX Lanjutan Modul Jadwal Pelajaran

> **Goal:** Selesaikan 5 feedback perbaikan UI/UX dari pengguna untuk modul Jadwal Pelajaran:
> 1. Tata letak tombol Reset Filter (tanpa ruang kosong).
> 2. Eliminasi KPI Card.
> 3. Perlebar modal create & edit ke `sm:max-w-2xl`.
> 4. Terapkan TomSelect untuk semua dropdown yang tersisa.
> 5. Sinkronkan visual "Tampilan Daftar" persis seperti "Daftar Harian" di Pola Jam.

---

## Tasks Checklist

- [x] **Task 1: Pindahkan Tombol Reset Filter ke Header & Hapus Divider Kosong**
  - [x] Edit `resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php`: hapus blok pembungkus `border-b border-gray-100 pb-2` dan pindahkan tombol Reset Filter ke dalam container tombol aksi header.
  - [x] Verifikasi visual bahwa tidak ada spasi vertikal/garis kosong berlebih saat filter belum atau sudah dipilih.

- [x] **Task 2: Hapus KPI Card dari Daftar Jadwal Pelajaran**
  - [x] Edit `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php`: hapus blok `@php $totalMapel = ...` dan grid 2 kartu KPI.
  - [x] Edit `tests/Feature/Admin/JadwalPelajaranCrudTest.php`: sesuaikan test lama yang meng-assert "Mata Pelajaran Aktif" menjadi assertion komponen jadwal lainnya.

- [x] **Task 3: Perlebar Modal Form Create & Edit ke `sm:max-w-2xl`**
  - [x] Edit `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php`: ganti `sm:max-w-xl` menjadi `sm:max-w-2xl`.

- [x] **Task 4: Universal TomSelect untuk Seluruh Select yang Tersisa**
  - [x] Modal Form (`_modal-form.blade.php` & `jadwal-pelajaran-filter.js`): pasang `x-init="initModalRuanganSelect($el)"` pada select `ruangan_id`, tambahkan method di JS, sinkronkan di `openCreateModal` dan `openEditModal`.
  - [x] Modal Duplicate (`_modal-duplicate.blade.php` & `jadwal-pelajaran-filter.js`): pasang `initDuplicateSemesterSelect` dan `initDuplicateKelasSelect`, perbarui `initDuplicateTahunAjaranSelect` agar mengoperasikan opsi via API TomSelect.
  - [x] Fallback Pages (`jadwal-pelajaran-create.js`, `create.blade.php`, `edit.blade.php`): tambahkan `initRuanganSelect` pada form fallback dan pasang pada select `ruangan_id`.

- [x] **Task 5: Perbarui "Tampilan Daftar" Mengadopsi Style "Daftar Harian" Pola Jam**
  - [x] Edit `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php`:
    - Tab filter hari horizontal di bagian atas dengan badge jumlah sesi.
    - Konten per hari (`hariAktif`) dengan badge nomor urutan mono, pill rentang jam dengan durasi menit, label slot jam, mapel bold, nama guru ber-icon person, nama ruangan ber-icon meeting_room.
    - Tombol aksi Edit dan Hapus dengan styling badge ber-border (`rounded-lg border border-gray-200 ...` dan `border-error-200 bg-error-50/30 ...`) serta tooltip `<x-tooltip>`.
  - [x] Pastikan fallback pesan kosong per hari tampil rapi jika hari tersebut belum memiliki slot jadwal.

- [x] **Task 6: Verification, Pint, Frontend Build & Handoff**
  - [x] Jalankan `npm run build` untuk mengompilasi asset JS & CSS.
  - [x] Jalankan test `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`.
  - [x] Jalankan `vendor/bin/pint --dirty --format agent`.
  - [x] Verifikasi visual di browser dengan `browser_subagent`.
  - [x] Tulis Handoff Log di `.agents/logs/2026-09-12-jadwal-pelajaran-iterasi-ui.md`.
