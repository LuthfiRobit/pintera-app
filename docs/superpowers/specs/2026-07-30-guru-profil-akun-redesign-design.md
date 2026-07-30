# Perbaikan Modul Data Guru (Profil + Pembuatan Akun) — Design

**Tanggal:** 2026-07-30
**Status:** Approved

## Latar Belakang

`admin/guru` (`app/Http/Controllers/Admin/GuruController.php`, `resources/views/admin/guru/*`)
masih memakai gaya desain lama (`<x-panel>`, token `text-ink`/`bg-paper`/`text-brass`,
`<x-slot name="header">`) — bagian dari "OLD bucket" yang dicatat di
`reference_design_direction_artifact`. Form create/edit hanya mengekspos 4 dari ~25 kolom
profil yang ada di model `Guru`, dan alur pembuatannya terbalik: admin harus lebih dulu
membuat `User` dengan role `guru` di tempat lain (`admin/users`), baru di sini "memilih" User
tsb dari dropdown untuk ditautkan jadi profil Guru. Tidak ada cara menonaktifkan/mengubah
status guru dari UI (`route()->resource(...)->except(['show','destroy'])`).

## Tujuan

Modernisasi `admin/guru` ke pola desain terbaru (index filter+table, form berkelompok, toast,
confirm dialog) sekaligus menyederhanakan pembuatan akun: satu form membuat `User` dan `Guru`
sekaligus, dengan email sebagai username dan NIP sebagai password awal.

## Cakupan

**Termasuk:** profil inti `Guru` (kolom-kolom di tabel `guru`), pembuatan akun `User` terpadu,
index/create/edit baru, aksi ubah status (`aktif`/`non_aktif`/`mutasi`/`pensiun`).

**Di luar cakupan (menyusul terpisah):** Riwayat Pendidikan, Sertifikasi, dan Jabatan Tambahan
(3 tabel relasi) — belum ada UI sama sekali sebelum maupun sesudah perubahan ini. Import data
guru massal (Excel) — hanya input manual satu-per-satu untuk saat ini.

## Alur Pembuatan Akun

`GuruController::store()` tidak lagi menerima `user_id` dari dropdown. Sebagai gantinya, dalam
satu `DB::transaction()`:

1. Validasi seluruh field profil (lihat "Field Form" di bawah).
2. Buat `User`: `name` = `nama`, `email` = `email` (form), `password` = nilai `nip` (di-hash
   otomatis lewat cast `password => 'hashed'` yang sudah ada di model `User`), `lembaga_id` =
   `lembaga_id` admin yang login, `email_verified_at` = `now()`, `is_active` = `true`. Assign
   role `guru`.
3. Buat `Guru` ditautkan ke `User` tsb, `lembaga_id` sama, `status_aktif` default `aktif`.

Field NIP dan Email di form create disertai keterangan singkat di bawah masing-masing input:
- NIP: *"NIP ini otomatis menjadi password login guru."*
- Email: *"Email ini menjadi username login guru."*

**Tidak ada tampilan kredensial khusus setelah submit** — cukup toast standar "Data guru &
akun berhasil dibuat.", karena keterangan di atas sudah menjelaskan polanya di muka.

**Edit tidak pernah menyentuh password.** Mengubah NIP di form edit hanya mengubah kolom
`guru.nip` — tidak mereset ulang password akun `User` yang sudah ada (menghindari guru yang
sudah pernah ganti password sendiri jadi terkunci tanpa sadar). Mengubah Email di form edit
**disinkronkan** ke `users.email` (bukan hanya `guru.email`), karena itu tetap satu-satunya
username login guru tsb — keduanya harus tetap konsisten.

## Field Form (dikelompokkan, dipakai bersama oleh create & edit lewat satu partial)

**Akun & Identitas** *(wajib kecuali disebutkan)*:
`nama`, `nik` (16 digit, unik via `nik_hash` — validasi yang sudah ada di controller saat ini
dipertahankan persis), `nip` (wajib — beda dari skema DB yang nullable, ditegakkan di layer
validasi Request, bukan migrasi), `email` (wajib, unik terhadap `users.email` DAN
`guru.email`), `jenis_kelamin` (enum L/P), `jenis_ptk` (enum 4 nilai yang sudah ada),
`status_kepegawaian` (enum 5 nilai yang sudah ada).

**Data Pribadi** *(semua opsional)*: `nuptk`, `tempat_lahir`, `tanggal_lahir`, `agama`,
`kewarganegaraan` (default `WNI` kalau kosong), `no_hp`.

**Alamat** *(semua opsional)*: `alamat_jalan`, `rt`, `rw`, `desa_kelurahan`, `kecamatan`,
`kabupaten_kota`, `provinsi`, `kode_pos`.

**Kepegawaian** *(semua opsional)*: `golongan_pangkat`, `tmt_tugas`, `tmt_pns`.

`status_aktif` **tidak** muncul di form create (selalu `aktif` di awal) — hanya bisa diubah
lewat aksi Ubah Status terpisah di halaman index (lihat di bawah), tidak lewat form edit biasa,
supaya perubahan status selalu lewat satu jalur yang jelas dan bisa diberi konfirmasi.

## Halaman Index

Mengikuti pola `admin/siswa/index.blade.php`:
- Kartu filter: pencarian (nama/NIP/NIK), filter `jenis_ptk`, filter `status_aktif`, tombol
  Reset — submit otomatis via `@input.debounce.500ms`/`@change`, mengikuti pola yang sama.
- Tombol utama "+ Tambah Data Guru" ke halaman create.
- Tabel: Nama, NIP, Jenis PTK, Status Kepegawaian (badge), Status Aktif (badge warna sesuai
  nilai: `aktif` = hijau, `non_aktif`/`mutasi`/`pensiun` = abu/amber), Aksi.
- Kolom Aksi: link Edit + dropdown "Ubah Status" berisi 3 opsi lain (tidak termasuk status
  yang sedang aktif), masing-masing memicu `confirmDialog(...)` sebelum submit — pola yang
  sama seperti dipakai di halaman Komponen Penilaian/Kenaikan Kelas.
- Toast standar (`session('status')` / `$errors->any()`) di paling atas.

## Aksi Ubah Status

Route baru: `PATCH admin/guru/{guru}/status` → `GuruController::updateStatus()`.
- Permission: `guru.edit` (tidak ada permission baru).
- Validasi: `status_aktif` harus salah satu dari `aktif`, `non_aktif`, `mutasi`, `pensiun`.
- Update hanya kolom `status_aktif`, redirect ke index dengan toast "Status guru berhasil
  diperbarui."

## Testing

Rewrite penuh `tests/Feature/Admin/GuruControllerTest.php` (kalau belum ada, buat baru),
menutupi:
- `store()` membuat `User` DAN `Guru` sekaligus dalam satu request; password `User` yang
  ter-hash cocok dengan nilai NIP yang dikirim (`Hash::check($nip, $user->password)`); role
  `guru` ter-assign.
- Validasi: NIK 16 digit + unik (kasus yang sudah ada, dipertahankan), NIP wajib, email wajib
  + unik terhadap `users.email` yang sudah dipakai user lain.
- `update()` mengubah profil tanpa mengubah password `User`; mengubah email di form
  menyinkronkan `users.email`.
- `updateStatus()` mengubah `status_aktif`, ditolak untuk nilai di luar 4 enum yang sah,
  memerlukan permission `guru.edit`.
- Index: filter pencarian/jenis_ptk/status_aktif masing-masing menyaring hasil dengan benar.

## Di Luar Cakupan

- Riwayat Pendidikan, Sertifikasi, Jabatan Tambahan (3 tabel relasi) — spec/plan terpisah.
- Import Excel data guru massal.
- Reset password manual oleh admin (di luar saat pembuatan akun awal).
- Halaman "show" (detail read-only) — resource route tetap `except(['show'])`.
