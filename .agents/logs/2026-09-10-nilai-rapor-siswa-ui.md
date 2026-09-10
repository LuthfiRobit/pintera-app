# Log: Standardisasi UI/UX Halaman Nilai & Rapor Siswa (Self-Service)

**Tanggal**: 2026-09-10  
**Branch**: `rbac-v2`  

## Apa yang dikerjakan

Revisi menyeluruh halaman **Nilai & Rapor Saya** (portal siswa/orang tua self-service).

### Perubahan view (nilai-rapor.blade.php)
- Header dengan breadcrumb dan badge kelas
- KPI Summary Cards: total mapel, rata-rata nilai, status rapor, semester
- TomSelect semester selector (onChange -> location.href, tidak perlu AJAX)
- Card download emerald dengan nama semester; amber info banner jika belum Disetujui
- Tabel nilai dengan kolom Ketuntasan (kktp_minimal), avatar inisial mapel, badge 3 warna
- Footer legend warna + rata-rata

### Perubahan controller (NilaiRaporSiswaController.php)
- Tambah query `pengajuanRaporSemua` (semua status) untuk amber info banner
- Eager-load `komponenPenilaian` agar `kktp_minimal` tersedia di view

## Keputusan penting
- Filter semester tidak pakai AJAX (scope fixed ke 1 siswa login, tidak ada cascading)
- Ketuntasan pakai `kktp_minimal` (default fallback 75)

## Hal yang masih perlu direview
- Apakah `kktp` atau `kktp_minimal` yang dipakai sekolah sebagai patokan?
- TomSelect init bergantung pada `window.TomSelect` dari layout parent

## Deep-Review & Perbaikan (Claude)

Review kode menemukan 2 masalah nyata, konsisten dengan pola perbaikan yang sudah dilakukan di Persetujuan Rapor dan Rapor Wali Kelas:

1. **Bug status "Ditolak" hilang dari KPI card "Status Rapor"**: chain `@if/@elseif` di card itu cuma menangani `Diverifikasi`/`Diajukan`/`Disetujui` -- kalau `PengajuanRapor` berstatus `Ditolak` (dikembalikan Kepsek untuk revisi), card ini jatuh ke `@else` dan salah menampilkan **"Belum Diajukan"**, padahal sebenarnya SUDAH pernah diajukan dan ditolak. Siswa/orang tua bisa salah paham mengira wali kelas belum mulai apa-apa. Diperbaiki dengan menambah cabang `Ditolak` -> "Perlu Revisi", ditambah regression test.
2. **Tombol "Unduh Rapor PDF" masih navigasi ke tab baru** (`target="_blank"`) -- sesuai preferensi user yang sudah ditegaskan di 2 halaman Rapor lain sesi ini (Persetujuan Rapor, Rapor Wali Kelas): PDF harus dibuka lewat modal `$store.imagePreview`, bukan navigasi. Diperbaiki dengan pola yang sama (`isPdf=true` eksplisit + `?inline=1`), dibungkus `x-data` bare karena halaman ini tidak punya root Alpine lain (pola sama seperti banner sukses/error di file yang sama).

Setelah perbaikan: 5 test lolos (4 lama + 1 baru), Pint bersih. Tidak ada emoji ditemukan di file ini (sudah dicek eksplisit).

**Git state**: sudah di-commit, branch `rbac-v2`.
