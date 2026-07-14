# Panduan Pengujian Manual — M2 SPMB Portal Publik

**Fitur:** Wizard pendaftaran publik SPMB (tanpa login) — entry point, verifikasi OTP email, data diri, formulir tambahan, upload dokumen, review & submit, cek status, dan unduh bukti pendaftaran.

**Cakupan:** Ini menguji hasil dari kedua plan M2 (data layer + wizard publik), yang sudah lolos automated test (226/226 passing) dan code review berlapis (per-task + whole-branch). Panduan ini fokus pada hal yang **tidak bisa** diverifikasi otomatis: tampilan visual, alur klik sungguhan di browser, dan pengalaman pengguna nyata.

**Yang perlu disiapkan sebelum mulai:**
- Server lokal berjalan (`php artisan serve` atau via Laragon).
- Akses ke `storage/logs/laravel.log` untuk membaca kode OTP (karena `MAIL_MAILER=log` di environment ini — email tidak benar-benar terkirim, tapi isinya tercatat di log).
- Login admin untuk menyiapkan data master (lembaga, tahun ajaran, jalur, gelombang, formulir tambahan, syarat dokumen).

---

## 1. Persiapan Data Master (via Admin Panel)

Sebelum menguji wizard publik, pastikan data berikut sudah ada dan berstatus aktif. Semua langkah ini dilakukan sambil login sebagai admin di `/admin/...`.

- [ ] **Lembaga** aktif dengan `slug` yang diketahui (cek di `/admin/lembaga`). Slug ini akan dipakai di URL publik, contoh: `/spmb/nama-sekolah`.
- [ ] **Tahun Ajaran** berstatus aktif untuk lembaga tersebut (`/admin/tahun-ajaran`) — hanya satu yang boleh aktif per lembaga.
- [ ] **Jalur PPDB** minimal satu, terhubung ke tahun ajaran aktif (`/admin/jalur-ppdb`).
- [ ] **Gelombang PPDB** minimal satu, dengan `tanggal_buka` di masa lalu dan `tanggal_tutup` di masa depan (agar terdeteksi "sedang buka") — buat via `/admin/gelombang-ppdb`.
- [ ] **Formulir Tambahan** (opsional, untuk menguji Task 3) — tambahkan 1-2 field custom (misalnya "Nilai Rata-rata Rapor") lewat halaman edit jalur (`/admin/jalur-ppdb/{id}/edit`).
- [ ] **Syarat Dokumen** (opsional, untuk menguji Task 3) — tambahkan 1-2 syarat dokumen (misalnya "Akta Kelahiran", wajib) lewat halaman yang sama.

> Catatan: jika field formulir tambahan / syarat dokumen sengaja dikosongkan, wizard harus tetap berjalan lancar (langkah tersebut otomatis dilewati dengan pesan "tidak ada formulir tambahan untuk jalur ini").

---

## 2. Alur Utama — Pendaftaran Berhasil (Happy Path)

Buka browser dalam mode private/incognito (supaya sesi bersih), lalu jalankan urutan berikut:

- [ ] **2.1** Buka `/spmb/{slug-lembaga}`. Halaman menampilkan daftar jalur yang tersedia dan nama gelombang yang sedang buka. Tampilan mengikuti gaya visual admin panel (bukan template generik) — cek warna, font, dan tidak ada sidebar/topbar admin di halaman ini.
- [ ] **2.2** Klik salah satu jalur. Diarahkan ke halaman "Verifikasi Email".
- [ ] **2.3** Masukkan email aktif, klik "Kirim Kode". Buka `storage/logs/laravel.log`, cari kode OTP 6 digit yang baru dikirim.
- [ ] **2.4** Masukkan kode OTP di halaman berikutnya. Kode salah harus ditolak dengan pesan error yang jelas (coba dulu sebelum memasukkan kode benar). Kode benar mengarahkan ke halaman "Data Diri".
- [ ] **2.5** Isi NIK (16 digit, boleh angka acak untuk uji coba) dan seluruh field wajib (nama, jenis kelamin, tempat/tanggal lahir, agama, alamat lengkap, minimal satu data orang tua/wali). Coba submit dengan field kosong dulu — pastikan pesan error muncul per-field, bukan halaman blank/500.
- [ ] **2.6** Submit data diri yang lengkap. Diarahkan ke "Formulir Tambahan" (jika ada field custom yang disiapkan di langkah 1, isi; jika kosong, halaman menampilkan pesan "tidak ada formulir tambahan").
- [ ] **2.7** Lanjut ke "Upload Dokumen". Upload file PDF/JPG/PNG kecil (di bawah 2MB) untuk tiap syarat dokumen yang wajib.
- [ ] **2.8** Lanjut ke halaman "Review". Pastikan data yang ditampilkan (nama, NIK, email) sesuai dengan yang diisi sebelumnya.
- [ ] **2.9** Klik "Kirim Pendaftaran". Diarahkan ke halaman "Pendaftaran Berhasil" dengan kode pendaftaran format `REG-2026-00001` (atau nomor urut berikutnya).
- [ ] **2.10** Cek `storage/logs/laravel.log` lagi — pastikan email konfirmasi berisi kode pendaftaran yang sama terkirim (tercatat di log).
- [ ] **2.11** Refresh halaman "Pendaftaran Berhasil" — harus tetap bisa diakses selama URL (termasuk parameter email) masih sama di address bar.

---

## 3. Cek Status & Unduh Bukti Pendaftaran

- [ ] **3.1** Dari halaman pilih-jalur (`/spmb/{slug}`), klik link "Sudah mendaftar? Cek status di sini".
- [ ] **3.2** Masukkan kode pendaftaran dan email dari langkah 2.9 di atas. Harus menampilkan ringkasan (nama, jalur, kode, tanggal submit) dan status "Menunggu Verifikasi" (badge warna amber/kuning).
- [ ] **3.3** Klik "Unduh Bukti Pendaftaran (PDF)". File PDF harus terbuka/terunduh dengan benar, berisi nama calon murid, jalur, gelombang, tanggal submit, dan status.
- [ ] **3.4** Coba cek status dengan kode benar tapi email salah — harus ditolak dengan pesan error yang sama persis seperti mencoba kode yang sama sekali tidak ada (tidak boleh ada perbedaan pesan yang membocorkan apakah kode itu valid atau tidak).

---

## 4. Skenario Keamanan & Edge Case

Bagian ini menguji langsung pengamanan yang dibangun selama development — penting untuk dicoba karena inilah yang paling sering luput dari uji otomatis di level UX nyata.

- [ ] **4.1 — Reuse NIK yang sah:** Ulangi pendaftaran baru (jalur/gelombang lain jika ada, atau lembaga yang sama) menggunakan **NIK yang sama** dan **email yang sama** seperti pendaftaran sebelumnya. Setelah NIK diketik di form data diri (klik keluar dari field NIK), form seharusnya otomatis terisi (prefill) dengan data yang sudah pernah dimasukkan sebelumnya.
- [ ] **4.2 — NIK dicuri / email tidak cocok:** Coba daftar dengan **NIK yang sama** seperti langkah 4.1, tapi **email yang berbeda** dari pendaftaran sebelumnya. Sistem harus **menolak** dengan pesan jelas ("NIK ini sudah pernah terdaftar. Gunakan email yang sama...") — form tidak boleh diam-diam melanjutkan dengan data kosong.
- [ ] **4.3 — Pendaftaran ganda (double submit):** Setelah berhasil mendaftar di langkah 2.9, coba klik tombol back di browser lalu submit ulang, ATAU ulangi seluruh alur wizard dari awal dengan NIK+email yang sama pada gelombang yang sama. Harus diarahkan kembali ke halaman review dengan pesan "Anda sudah terdaftar untuk gelombang ini" — **bukan** halaman error 500.
- [ ] **4.4 — File upload ditolak:** Coba upload file yang salah tipe (misalnya `.txt` atau `.exe`) atau file berukuran lebih dari 2MB. Harus ditolak dengan pesan error yang jelas di halaman itu juga (bukan crash).
- [ ] **4.5 — Sesi kadaluarsa / lompat langkah:** Buka tab browser baru (tanpa melalui alur OTP), lalu coba akses langsung URL `/spmb/{slug}/{id-jalur}/review` atau kirim POST langsung ke `/spmb/{slug}/{id-jalur}/submit`. Harus diarahkan kembali ke langkah awal (verifikasi email atau data diri) dengan pesan yang masuk akal — **bukan** halaman error 500 atau data kosong yang aneh.
- [ ] **4.6 — Gelombang tertutup:** Jika memungkinkan, ubah `tanggal_tutup` gelombang di admin ke tanggal kemarin, lalu buka `/spmb/{slug}` lagi. Harus menampilkan halaman "Pendaftaran belum/sudah tertutup", bukan daftar jalur. (Kembalikan tanggalnya setelah selesai uji.)
- [ ] **4.7 — Enumerasi kode pendaftaran:** Coba akses `/spmb/{slug}/berhasil/REG-2026-00001?email=sembarangan@test.com` (ganti dengan kode yang valid tapi email yang salah). Harus menampilkan halaman 404 biasa, **bukan** menampilkan data pendaftaran orang lain.

---

## 5. Tampilan & Pengalaman Pengguna (UX)

- [ ] Cek tampilan di layar sempit (mode HP di DevTools browser, atau HP sungguhan) — form harus tetap enak dipakai, tidak ada elemen terpotong.
- [ ] Cek bahwa tidak ada sisa styling/komponen admin panel (sidebar, topbar, menu navigasi admin) yang bocor ke halaman publik manapun.
- [ ] Cek konsistensi bahasa — semua teks harus dalam Bahasa Indonesia yang wajar, tidak ada campuran istilah teknis/Inggris yang aneh di layar pengguna.
- [ ] Cek tombol submit di setiap langkah — tidak ada tombol yang bisa diklik berkali-kali menyebabkan submit ganda tanpa sengaja saat koneksi lambat.

---

## Ringkasan Checklist Cepat

| # | Skenario | Hasil yang Diharapkan | ✅/❌ |
|---|----------|------------------------|-------|
| 2 | Alur pendaftaran lengkap dari awal sampai akhir | Sukses, dapat kode pendaftaran | |
| 3 | Cek status + unduh PDF bukti pendaftaran | Data tampil benar, PDF valid | |
| 4.1 | NIK+email sama → prefill data lama | Form terisi otomatis | |
| 4.2 | NIK sama, email beda → ditolak | Pesan blokir, bukan form kosong | |
| 4.3 | Submit ganda / sudah terdaftar | Pesan ramah, bukan 500 | |
| 4.4 | File upload salah tipe/ukuran | Ditolak dengan pesan jelas | |
| 4.5 | Lompat langkah / sesi kadaluarsa | Diarahkan ke langkah awal, bukan 500 | |
| 4.6 | Gelombang tertutup | Halaman "belum/sudah tertutup" | |
| 4.7 | Tebak kode pendaftaran orang lain | 404, bukan bocor data | |

**Jika semua baris di atas ✅, fitur ini siap dianggap teruji secara manual.** Jika ada yang ❌, catat langkah persis untuk mereproduksinya sebelum dilaporkan.
