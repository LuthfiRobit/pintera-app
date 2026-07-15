# Panduan Pengujian Manual — Portal Akun Pendaftar

**Fitur:** Akun mandiri untuk calon siswa (registrasi, verifikasi OTP, login, lupa password) yang otomatis tertaut ke seluruh pendaftaran SPMB milik email yang sama, ditampilkan di dashboard portal dengan tampilan navigasi sungguhan (sidebar+navbar+footer).

**Cakupan:** Ini menguji sub-project 1 dari 3 inisiatif Keuangan, yang sudah lolos automated test (300/300 passing) dan review berlapis (per-task + whole-branch). Panduan ini fokus pada hal yang **tidak bisa** diverifikasi otomatis: tampilan visual, alur klik sungguhan di browser, dan apakah penautan otomatis benar-benar terasa "otomatis" saat dipakai — bukan pada verifikasi dokumen/keputusan SPMB itu sendiri (itu sudah diuji di panduan M3).

**Yang perlu disiapkan sebelum mulai:**
- Server lokal berjalan (`php artisan serve` atau via Laragon).
- Akses ke `storage/logs/laravel.log` untuk membaca kode OTP (email tidak benar-benar terkirim di environment ini, tapi isinya tercatat di log).
- Database sudah di-`migrate:fresh --seed` — data demo M3 (termasuk beberapa pendaftaran dengan email contoh) sudah tersedia, dipakai di beberapa skenario di bawah.
- Buka semua sesi dalam mode private/incognito supaya sesi login admin (kalau ada) tidak bercampur dengan sesi portal.

---

## 1. Registrasi Akun Baru & Verifikasi OTP

- [ ] **1.1** Buka `/portal/register`. Isi nama, email baru (misalnya `siswa.baru@example.test`), password, konfirmasi password. Submit.
- [ ] **1.2** Diarahkan ke `/portal/verifikasi-otp`. Buka `storage/logs/laravel.log`, cari kode OTP 6 digit yang baru dikirim ke email tersebut.
- [ ] **1.3** Coba masukkan kode salah dulu — harus ditolak dengan pesan error yang jelas, tetap di halaman OTP.
- [ ] **1.4** Masukkan kode yang benar. Harus langsung masuk (login otomatis) dan diarahkan ke `/portal/dashboard`.
- [ ] **1.5** Karena email ini belum pernah dipakai mendaftar SPMB, dashboard harus menampilkan pesan kosong yang ramah ("belum ada pendaftaran yang tertaut"), bukan halaman error atau tabel kosong tanpa penjelasan.
- [ ] **1.6** Coba daftar lagi dengan email yang sama persis (`siswa.baru@example.test`) di tab baru. Harus ditolak dengan pesan "email sudah dipakai", bukan error database mentah.

---

## 2. Penautan Otomatis Arah A — Akun Dibuat Setelah Pernah Daftar SPMB

Data demo M3 sudah punya pendaftaran dengan email `wali.diterima@example.test` di **dua lembaga sekaligus** (SMP dan SMA Islam Al-Hikmah) — cocok untuk menguji satu akun yang terhubung ke lebih dari satu pendaftaran.

- [ ] **2.1** Logout dari akun sebelumnya (jika masih login). Daftar akun baru di `/portal/register` memakai email persis `wali.diterima@example.test` (nama bebas, password bebas).
- [ ] **2.2** Ambil kode OTP dari log, verifikasi.
- [ ] **2.3** Setelah masuk ke dashboard, harus tampil **dua** baris pendaftaran — satu dari SMP, satu dari SMA — bukan cuma satu, dan bukan tercampur data pendaftaran lain. Cek nama calon murid, lembaga, jalur/gelombang, kode pendaftaran, dan badge status ("Diterima", hijau) tampil benar di kedua baris.
- [ ] **2.4** Klik "Unduh Bukti (PDF)" pada salah satu baris. File PDF harus terbuka dengan data yang sesuai baris tersebut.

---

## 3. Penautan Otomatis Arah B — Daftar SPMB Setelah Sudah Punya Akun

- [ ] **3.1** Masih login sebagai akun dari langkah 2 (atau buat akun baru khusus untuk ini, verifikasi dulu). Catat emailnya.
- [ ] **3.2** Buka tab baru (tanpa logout dari portal), jalankan wizard pendaftaran SPMB publik dari awal (`/spmb/{slug-lembaga}`) sampai selesai submit, menggunakan **email yang sama** dengan akun portal langkah 3.1 di tahap verifikasi OTP wizard SPMB (kode OTP wizard SPMB berbeda dari OTP akun portal — cek log lagi untuk kode yang ini).
- [ ] **3.3** Setelah pendaftaran SPMB baru berhasil disubmit, kembali ke tab dashboard portal (`/portal/dashboard`) dan refresh. Pendaftaran baru ini harus **langsung muncul** di daftar tanpa langkah tambahan apapun (tidak perlu klaim manual, tidak perlu isi kode pendaftaran di portal).

---

## 4. Login, Logout, & Lupa Password

- [ ] **4.1** Logout dari portal (tombol "Keluar" di navbar). Harus kembali ke halaman login portal, bukan halaman login admin.
- [ ] **4.2** Login lagi di `/portal/login` dengan email+password yang benar. Berhasil masuk ke dashboard.
- [ ] **4.3** Coba login dengan password salah. Pesan error harus generik ("Email atau password salah") — tidak boleh membedakan apakah emailnya ada atau tidak.
- [ ] **4.4** Klik "Lupa password?" di halaman login, masukkan email akun yang sudah ada. Cek `storage/logs/laravel.log` untuk link reset password, buka link tersebut, isi password baru, submit. Coba login dengan password baru — harus berhasil.
- [ ] **4.5 — Akun belum terverifikasi:** Daftar akun baru, JANGAN verifikasi OTP-nya, coba langsung login ke `/portal/login` dengan email+password akun tersebut. Harus ditolak dan diarahkan ke halaman verifikasi OTP (dengan kode baru otomatis terkirim — cek log), bukan berhasil login begitu saja.

---

## 5. Isolasi Antar Akun

- [ ] **5.1** Siapkan dua akun portal berbeda (A dan B), masing-masing dengan minimal satu pendaftaran tertaut (pakai langkah 2 di atas dengan email demo yang berbeda, misalnya `wali.diterima@example.test` untuk A dan `wali.ditolak@example.test` untuk B).
- [ ] **5.2** Login sebagai akun A, buka dashboard, catat URL tombol "Unduh Bukti (PDF)" salah satu pendaftaran A (bentuknya `/portal/pendaftaran/{id}/bukti`).
- [ ] **5.3** Logout, login sebagai akun B. Dashboard B harus **hanya** menampilkan pendaftaran milik B — pendaftaran milik A tidak boleh muncul sama sekali.
- [ ] **5.4** Masih login sebagai B, coba akses langsung URL PDF milik A dari langkah 5.2 (ganti address bar). Harus mendapat halaman **404**, bukan berhasil mengunduh PDF milik akun lain.

---

## 6. Pemisahan Akun Portal & Akun Staff Admin

- [ ] **6.1** Coba login di `/portal/login` menggunakan kredensial akun **staff/admin** (misalnya `adm.smp@alhikmah.sch.id` / `password`). Harus ditolak — akun staff tidak boleh bisa masuk lewat portal calon siswa.
- [ ] **6.2** Sebaliknya, coba login di halaman admin (`/login`) menggunakan kredensial akun **portal** yang baru dibuat di atas. Harus ditolak juga.
- [ ] **6.3** Sambil login sebagai akun portal, coba akses langsung URL admin (`/admin/spmb-pendaftaran` atau `/dashboard`). Harus diarahkan ke halaman **login admin** (`/login`), bukan halaman login portal, dan bukan berhasil masuk.
- [ ] **6.4** Sebaliknya, sambil login sebagai staff admin di panel admin, coba akses `/portal/dashboard`. Harus diarahkan ke halaman **login portal** (`/portal/login`), bukan halaman login admin, dan bukan berhasil masuk.

---

## 7. Tampilan & Pengalaman Pengguna (UX)

- [ ] Cek dashboard portal punya struktur navigasi sungguhan — sidebar kiri, navbar atas (nama akun + tombol keluar), footer bawah — bukan cuma kartu tunggal di tengah halaman kosong seperti wizard SPMB.
- [ ] Cek warnanya tetap biru (`spmb-primary`/`spmb-accent`), **bukan** warna ink/brass/paper seperti panel admin.
- [ ] Cek halaman register/login/verifikasi-otp/lupa-password (sebelum login) memakai kartu terpusat sederhana — sama gayanya dengan wizard SPMB publik, konsisten.
- [ ] Cek tampilan di layar sempit (mode HP di DevTools) — sidebar bisa dibuka/tutup lewat tombol menu, tidak ada elemen terpotong.
- [ ] Cek badge status pendaftaran di dashboard (Menunggu Verifikasi/Diterima/Ditolak) memakai warna yang sama dengan yang dipakai di panel admin (amber/hijau/merah).

---

## Ringkasan Checklist Cepat

| # | Skenario | Hasil yang Diharapkan | ✅/❌ |
|---|----------|------------------------|-------|
| 1 | Registrasi baru + verifikasi OTP | Berhasil, dashboard kosong dengan pesan ramah | |
| 2 | Penautan otomatis arah A (akun dibuat setelah pernah daftar SPMB) | Dua pendaftaran lama langsung muncul di dashboard | |
| 3 | Penautan otomatis arah B (daftar SPMB setelah punya akun) | Pendaftaran baru langsung muncul tanpa klaim manual | |
| 4 | Login/logout/lupa password/akun belum terverifikasi | Semua berjalan sesuai skenario, tidak ada yang nyasar | |
| 5 | Isolasi antar akun portal | Tidak bisa lihat/unduh data akun lain, 404 saat coba paksa | |
| 6 | Pemisahan akun portal vs staff admin | Tidak bisa saling login, redirect ke halaman login yang benar | |
| 7 | Tampilan portal (struktur admin, warna SPMB) | Sidebar+navbar+footer sungguhan, warna biru konsisten | |

**Jika semua baris di atas ✅, fitur ini siap dianggap teruji secara manual.** Jika ada yang ❌, catat langkah persis untuk mereproduksinya sebelum dilaporkan.
