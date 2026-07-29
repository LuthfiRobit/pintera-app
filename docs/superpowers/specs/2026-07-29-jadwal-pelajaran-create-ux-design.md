# Jadwal Pelajaran — Halaman Create UX & Multi-Slot Design

## Goal

Rombak halaman "Tambah Jadwal" (`admin/jadwal-pelajaran/create.blade.php`) supaya: (1) semua dropdown jadi searchable (Tom Select), (2) Jam Pelajaran bisa dipilih lebih dari satu sekaligus (dikelompokkan per hari) untuk kasus satu mapel+guru menempati beberapa slot (mis. jam pelajaran ganda), (3) validasi batch yang sesuai untuk skenario multi-slot ini, (4) konteks Tahun Ajaran ditampilkan selain Semester, (5) toast/flash message terpasang seperti halaman lain, dan pembersihan kecil lain (format waktu, posisi keterangan opsional, pesan Pola Jam kosong).

## Requirements (hasil diskusi)

1. **Semua dropdown** (Jam Pelajaran, Mata Pelajaran, Guru Pengampu) memakai Tom Select — bukan cuma yang sempat disorot di analisis awal.
2. **Jam Pelajaran** jadi multi-select, dikelompokkan (optgroup) per hari — satu submit boleh memilih beberapa slot sekaligus (mis. Matematika Jam ke-1 & Jam ke-2 hari yang sama, guru yang sama).
3. **Validasi dua lapis** untuk skenario multi-slot:
   - **Lapis keamanan/integritas** (jam pelajaran harus milik Pola Jam kelas ini; guru/mapel/semester harus dari lembaga yang sama dengan kelas) — **gagal total** kalau ada satu saja yang melanggar, sama seperti sekarang. Ini bukan soal bentrok jadwal, tapi indikasi input tidak valid.
   - **Lapis bentrok jadwal** (slot ini sudah ada jadwalnya di kelas+semester yang sama, ATAU guru ini sudah mengajar kelas lain di jam+semester yang sama) — pola yang sama dengan checkbox multi-hari Pola Jam: slot yang tidak bentrok tetap dibuat, yang bentrok dilewati dan disebutkan alasannya di pesan hasil. Gagal total hanya kalau **semua** slot yang dipilih bentrok.
4. **Tahun Ajaran ditampilkan** di halaman, bukan cuma Semester — supaya konteks lengkap terlihat (saat ini Semester sendiri pun cuma jadi hidden input, tidak terlihat sama sekali).
5. **Toast/flash message** dipasang (`session('status')`, `$errors->any()`) — halaman ini saat ini TIDAK punya blok ini sama sekali, berbeda dari semua halaman admin lain.
6. (Dipertahankan dari analisis awal) Format waktu di opsi Jam Pelajaran tanpa detik, konsisten dengan halaman index.
7. (Dipertahankan) Keterangan "(opsional utk PAUD)" pada Mata Pelajaran dipindah jadi teks bantuan di bawah label, bukan di dalam label.
8. (Dipertahankan) Kalau kelas belum punya Pola Jam, tampilkan banner peringatan jelas dengan tautan ke halaman Pola Jam — bukan disembunyikan di dalam satu opsi dropdown.

## Arsitektur

### Backend — `JadwalPelajaranController`

**`create()`** — tambahan kecil: muat `$semester = Semester::find($semesterId)` dan `$tahunAjaran = $kelas->tahunAjaran` untuk ditampilkan (bukan cuma dipakai sebagai hidden input), dan kelompokkan `$jamPelajaranList` per hari (dalam urutan hari aktif lembaga, bukan `orderBy('hari')` mentah — **catatan: `orderBy('hari')` di kode saat ini mengurutkan secara alfabetis string enum, bukan urutan hari sebenarnya (jumat, kamis, minggu, rabu, sabtu, selasa, senin) — bug kecil yang ikut diperbaiki sebagai bagian dari restrukturisasi ini**, diganti memakai `Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? [])` seperti pola yang sudah dipakai di Pola Jam dan index Jadwal Pelajaran).

**`store()`** — perubahan validasi dan alur:
```
Validasi: jam_pelajaran_id sekarang array (required, min:1), jam_pelajaran_id.* integer.

1. Resolve kelas/guru/semester/mataPelajaran SEKALI (seperti sekarang) — abort(404) kalau tidak ada.
2. Cek lembaga_id cocok untuk guru/semester/mataPelajaran terhadap kelas (seperti sekarang) — back()->withErrors() kalau tidak cocok, TIDAK berubah.
3. Resolve SEMUA jam_pelajaran_id yang dipilih via JamPelajaran::whereIn(...)->where('pola_jam_id', $kelas->pola_jam_id)->get() — kalau jumlah hasil tidak sama dengan jumlah yang diminta (ada yang bukan milik pola jam kelas ini), abort(404) untuk SELURUH request (lapis keamanan, gagal total).
4. Loop tiap slot yang lolos langkah 3: cek duplikat (kelas+jam+semester) dan cek guru bentrok (guru+jam+semester) SATU PER SATU. Slot yang lolos kedua cek → dibuat. Slot yang bentrok → masuk daftar dilewati (dengan alasan: duplikat atau guru bentrok).
5. Kalau tidak ada satupun yang berhasil dibuat → error (bukan sukses), sebutkan semua slot yang dilewati dan alasannya.
6. Kalau ada yang berhasil → redirect sukses, sebutkan slot yang berhasil, dan (kalau ada) slot yang dilewati beserta alasannya.
```

### Frontend — `create.blade.php`

- Tambahkan blok toast (`session('status')`/`$errors->any()`) di atas, identik dengan pola di semua halaman admin lain.
- Tambahkan info konteks Tahun Ajaran + Semester (read-only, bukan input) di bagian atas form — mis. baris kecil "Tahun Ajaran: 2026/2027 · Semester: Ganjil".
- Jam Pelajaran: `<select multiple>` dengan `<optgroup label="{{ hari }}">` per hari, di-enhance jadi Tom Select multi-select (`maxItems: null` atau sesuai jumlah slot yang ada). Format opsi tanpa detik (`substr(...,0,5)`).
- Mata Pelajaran & Guru: `<select>` biasa di-enhance jadi Tom Select single-select, mengikuti pola `initXSelect` yang sudah ada di `resources/js/formulir-tambahan-form.js`/`data-diri-form.js`.
- Keterangan "(opsional utk PAUD)" jadi teks bantuan `<p class="text-xs text-gray-400">` di bawah label Mata Pelajaran, bukan di dalam label.
- Kalau `$jamPelajaranList` kosong (kelas belum punya Pola Jam): tampilkan banner peringatan (ikon + teks + tautan ke `admin.pola-jam.index`) menggantikan seluruh form, bukan satu opsi dropdown yang menjelaskan.

### JS baru

`resources/js/jadwal-pelajaran-create.js` — modul baru mengikuti konvensi yang sama (`initMataPelajaranSelect`, `initGuruSelect`, `initJamPelajaranSelect`), didaftarkan sebagai `Alpine.data('jadwalPelajaranCreateForm', ...)` di `app.js`. Tidak perlu logika fetch/AJAX apapun di sini — form ini tetap submit biasa (POST + redirect), cuma dropdown-nya yang di-enhance Tom Select. Jauh lebih sederhana dari modul filter index kemarin.

## Self-Review

- **Placeholder scan**: tidak ada — kontrak validasi, urutan langkah, dan pesan sudah konkret (detail kode persis ditulis di plan implementasi).
- **Konsistensi**: pola skip-dan-laporkan untuk lapis bentrok jadwal identik dengan Pola Jam multi-hari yang sudah dibangun dan disetujui sebelumnya — tidak menciptakan pola baru yang beda.
- **Cakupan scope**: murni halaman Create Jadwal Pelajaran. Tidak menyentuh halaman index (sudah selesai) atau kemampuan edit/hapus (tetap di luar scope, belum dikerjakan).
- **Bug kecil yang ikut ditemukan**: urutan hari di dropdown Jam Pelajaran saat ini alfabetis (bug), bukan kronologis — diperbaiki sebagai bagian dari restrukturisasi optgroup, bukan pekerjaan terpisah.
