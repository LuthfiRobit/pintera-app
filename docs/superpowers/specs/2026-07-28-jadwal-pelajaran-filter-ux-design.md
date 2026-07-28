# Jadwal Pelajaran — Filter UX Tanpa Reload Design

## Goal

Rombak interaksi filter di halaman index Jadwal Pelajaran (`admin/jadwal-pelajaran/index.blade.php`) dari GET-form-dengan-tombol-submit-dan-reload-halaman menjadi filter yang otomatis memuat ulang daftar jadwal tanpa reload, dengan urutan dan komponen field yang lebih tepat untuk jumlah data yang bisa besar. Juga membatasi hari yang ditampilkan di daftar jadwal ke hari aktif lembaga saja.

**Catatan scope:** ini adalah lanjutan dari analisis UI/UX yang sempat dibagi jadi bagian fungsional (A — tidak ada edit/hapus jadwal) dan bagian UI/UX murni (B). **Bagian A sengaja di-skip untuk pekerjaan ini** — tidak ada perubahan pada kemampuan edit/hapus jadwal yang sudah ada.

## Requirements (hasil diskusi)

1. Urutan filter: **Tahun Ajaran → Semester → Kelas** (sebelumnya Tahun Ajaran → Kelas → Semester).
2. **Tahun Ajaran** dan **Kelas** memakai Tom Select (searchable) karena berpotensi >7-10 opsi (aturan baku proyek ini). **Semester** tetap `<select>` native (jumlah opsi selalu kecil, biasanya 2).
3. **Tombol "Tampilkan" dihapus.** Mengganti nilai filter manapun langsung memicu pembaruan otomatis.
4. **Tidak ada reload halaman** untuk perubahan filter apapun.
5. **Hari yang ditampilkan** di daftar jadwal (pengelompokan per hari) dibatasi ke hari aktif lembaga kelas yang dipilih (`Hari::aktifDari($lembaga->hari_libur_mingguan)`), bukan 7 hari penuh — pola yang sama persis dengan yang sudah dipakai di halaman Pola Jam.
6. (Dari analisis awal, dikonfirmasi tetap dalam scope) Tampilkan pesan penjelas yang jelas saat Kelas dan/atau Semester belum lengkap dipilih, menggantikan kondisi diam-diam tidak menampilkan apapun seperti sekarang.
7. (Dikoreksi dari analisis awal setelah dicek ulang terhadap halaman Kelas) Posisi tombol "+ Tambah Slot Jadwal" di header kartu Filter **TIDAK diubah** — itu sudah pola yang konsisten dipakai di halaman index lain (mis. Kelas), bukan penyimpangan.

## Arsitektur

**Pendekatan: server merender potongan HTML, JavaScript menempelkannya — bukan templating penuh di JavaScript.**

Ada 2 pendekatan yang dipertimbangkan:
- **(A) Templating client-side**: fetch mengembalikan JSON berisi data mentah, JavaScript/Alpine merender ulang tampilan daftar jadwal (pengelompokan per hari, badge waktu, dll) lewat `x-for`. Ini pola yang sudah dipakai di `jenisTagihanTable` (`resources/js/jenis-tagihan-table.js`) untuk daftar flat sederhana.
- **(B) Fragment HTML dari server**: fetch mengembalikan potongan HTML yang sudah jadi (dirender Blade), JavaScript cuma menempelkannya menggantikan isi container lama.

**Dipilih (B)**, karena tampilan daftar jadwal di sini bukan daftar flat sederhana — ada pengelompokan bertingkat per hari + pengurutan per urutan slot + beberapa badge kondisional (mata pelajaran bisa null, dll). Menulis ulang logika ini di Alpine/JS berarti dua sumber kebenaran (Blade untuk muat halaman pertama kali, JS untuk update berikutnya) yang gampang tidak sinkron seiring waktu. Dengan potongan HTML dari server, satu-satunya logika tampilan tetap di Blade.

Untuk mengisi ulang opsi Kelas (Tom Select) dan Semester (native select) saat Tahun Ajaran berganti, dipakai JSON biasa (bukan HTML) karena datanya flat sederhana (id+nama) dan Tom Select memang butuh data terstruktur untuk API `addOption()`-nya, bukan HTML `<option>` mentah.

### Endpoint baru

**`GET admin/jadwal-pelajaran/opsi`** (nama route: `admin.jadwal-pelajaran.opsi`)
- Query: `tahun_ajaran_id` (required, integer).
- Resolusi: `TahunAjaran::find($tahunAjaranId)` (tenant-scoped otomatis) + `abort_if(null, 404)` — pola standar proyek ini untuk FK ke model tenant-scoped, bukan `exists:` mentah.
- Response JSON:
  ```json
  {
    "kelasList": [{"id": 12, "nama": "6A"}, {"id": 13, "nama": "6B"}],
    "semesterList": [{"id": 5, "nama": "Ganjil 2026/2027"}, {"id": 6, "nama": "Genap 2026/2027"}]
  }
  ```

### Endpoint yang diperluas

**`GET admin/jadwal-pelajaran`** (route existing, `admin.jadwal-pelajaran.index`) — perilaku baru:
- Kalau request adalah AJAX fetch (dideteksi lewat header `X-Requested-With: XMLHttpRequest`, dicek via `$request->ajax()`) **dan** `kelas_id`+`semester_id` keduanya ada → response HANYA berisi potongan HTML daftar jadwal (bukan halaman penuh), `Content-Type: text/html`.
- Kalau bukan request AJAX (navigasi biasa/muat halaman pertama kali) → tetap merender halaman penuh seperti sekarang, memakai `@include` ke partial yang sama untuk bagian daftar jadwal (supaya tidak ada duplikasi kode Blade antara respons penuh dan respons fragment).
- Logika filter (`$kelasId`, `$semesterId`, query ke `JadwalPelajaran`) tidak berubah — hanya cara meresponnya yang berbeda.

### File baru

**`resources/views/admin/jadwal-pelajaran/_daftar.blade.php`** — partial berisi seluruh isi blok "2. Daftar Jadwal Pelajaran per Hari" yang sekarang ada di `index.blade.php` (dari `@if ($jadwalList->isEmpty())` dst.), menerima variable `$jadwalList` dan `$hariAktif` (array `Hari` enum case, bukan lagi `\App\Enums\Hari::cases()` hardcoded). Dipakai baik oleh `index.blade.php` (lewat `@include`) maupun dirender langsung sebagai string untuk respons fragment AJAX.

Kalau `$kelasId`/`$semesterId` belum lengkap dua-duanya, partial ini menampilkan pesan penjelas (bukan kosong tanpa keterangan): *"Pilih Tahun Ajaran, Semester, dan Kelas untuk menampilkan jadwal pelajaran."* dengan ikon, gaya serupa empty-state yang sudah ada.

### Perhitungan hari aktif

Di `JadwalPelajaranController::index()`, saat `$kelasId` terisi: muat `$kelas = Kelas::with('lembaga')->find($kelasId)`, lalu `$hariAktif = $kelas ? \App\Enums\Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? []) : \App\Enums\Hari::cases()`. Partial `_daftar` melakukan `@foreach ($hariAktif as $hari)` menggantikan `@foreach (\App\Enums\Hari::cases() as $hari)` yang sekarang.

## Frontend (Alpine + Tom Select)

Modul JS baru `resources/js/jadwal-pelajaran-filter.js`, mengikuti konvensi yang sudah ada di `resources/js/data-diri-form.js`/`resources/js/formulir-tambahan-form.js` (factory function, didaftarkan lewat `Alpine.data('jadwalPelajaranFilter', jadwalPelajaranFilter)` di `resources/js/app.js`) dan konvensi fetch di `resources/js/jenis-tagihan-table.js` (header `Accept`/`X-CSRF-TOKEN`, `Alpine.store('toast')` untuk pesan error, `try/catch/finally`).

Tanggung jawab modul ini:
1. `initTahunAjaranSelect(el)` — pasang Tom Select pada select Tahun Ajaran. `onChange` memicu `gantiTahunAjaran(value)`.
2. `initKelasSelect(el)` — pasang Tom Select pada select Kelas (awalnya bisa kosong/disabled kalau belum ada Tahun Ajaran terpilih). `onChange` memicu `muatUlangDaftar()`.
3. `gantiTahunAjaran(tahunAjaranId)` — fetch `admin.jadwal-pelajaran.opsi?tahun_ajaran_id=...`, lalu:
   - Kosongkan & isi ulang opsi Tom Select Kelas lewat API-nya (`clearOptions()` + `addOption()` + `refreshOptions()`).
   - Kosongkan & isi ulang `<option>` select Semester biasa (manipulasi DOM langsung, karena bukan Tom Select).
   - Reset nilai Kelas & Semester yang terpilih ke kosong.
   - Kosongkan isi container daftar jadwal (tampilkan pesan "pilih Semester dan Kelas" dari partial, atau panggil ulang `muatUlangDaftar()` yang akan menampilkan itu karena kelas_id/semester_id kosong).
   - Perbarui URL lewat `history.pushState` (`tahun_ajaran_id` saja, `kelas_id`/`semester_id` dihapus dari query string).
4. `muatUlangDaftar()` — dipanggil saat Semester atau Kelas berubah. Kalau kelas_id DAN semester_id sudah terisi: fetch `admin.jadwal-pelajaran.index?...` dengan header `X-Requested-With: XMLHttpRequest`, ambil teks HTML dari response, tempelkan sebagai `innerHTML` container daftar jadwal. Perbarui URL lewat `history.pushState` dengan ketiga parameter filter yang sedang aktif.
5. Container daftar jadwal ditandai `x-ref="daftarJadwal"` di Blade supaya JS bisa menargetkannya langsung.

**Muat halaman pertama kali** (termasuk saat datang dari link redirect setelah submit halaman Create, yang membawa `kelas_id`+`semester_id` di query string) tetap dirender penuh oleh Blade di server seperti sekarang — TIDAK ada fetch tambahan saat halaman baru dimuat. Fetch hanya terjadi akibat interaksi user mengubah filter setelah halaman sudah terbuka. Ini menjaga waktu muat awal tetap cepat dan URL tetap bisa langsung dibuka/dibagikan.

## Self-Review

- **Placeholder scan**: tidak ada TBD — kontrak endpoint, format JSON, dan tanggung jawab tiap fungsi JS sudah konkret.
- **Konsistensi internal**: partial `_daftar.blade.php` dipakai identik oleh kedua jalur (muat halaman penuh & fragment AJAX) — tidak ada logika tampilan ganda.
- **Cakupan scope**: murni interaksi halaman index (filter + hari aktif) dan sedikit pembersihan pesan kosong. Tidak menyentuh halaman Create maupun fungsional A (edit/hapus) — sesuai kesepakatan.
- **Koreksi tercatat**: posisi tombol "+ Tambah Slot Jadwal" awalnya dianggap perlu dipindah, dikoreksi setelah membandingkan dengan pola halaman Kelas — TIDAK diubah.
