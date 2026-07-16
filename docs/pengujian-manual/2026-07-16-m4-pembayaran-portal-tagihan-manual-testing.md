# Panduan Pengujian Manual — Pembayaran Manual & Portal Tagihan

**Fitur:** Skema cicilan (pembagian rata + sunting manual), pembayaran transfer manual dari Portal Akun Pendaftar (bayar lunas atau cicil, upload bukti), verifikasi admin (terima/tolak), jalur cadangan catat manual oleh admin, dan riwayat transaksi yang tidak pernah menghilangkan percobaan yang ditolak.

**Cakupan:** Ini menguji sub-project 3 dari 3 (terakhir) inisiatif Keuangan, yang sudah lolos automated test (385/385 passing) dan review berlapis (per-task + whole-branch, termasuk satu perbaikan Critical: sebelum diperbaiki, halaman admin sama sekali tidak punya cara untuk menerima/menolak pembayaran yang diunggah calon siswa — backend-nya benar tapi tidak ada tombolnya). Panduan ini fokus pada alur klik sungguhan di browser dan apakah **seluruh alur SPMB dari daftar sampai bayar** benar-benar bisa diselesaikan tanpa proses manual di luar aplikasi.

**Penting — ini menutup seluruh alur SPMB.** Setelah panduan ini, sebuah pendaftaran bisa berjalan dari submit sampai calon siswa dinyatakan aktif (lunas/cicil termin pertama) tanpa berpindah ke sistem lain. Sengaja dirancang untuk dimulai dari `migrate:fresh --seed` supaya kamu melihat seluruh rantai dari nol, termasuk membuat jenis tagihan sendiri (seperti panduan sub-project 2).

**Yang perlu disiapkan sebelum mulai:**
- Server lokal berjalan, database sudah `migrate:fresh --seed`.
- Akses ke `storage/logs/laravel.log` untuk kode OTP (dipakai untuk registrasi akun portal dan submit SPMB).
- Dua sesi browser terpisah (mode private/incognito) — satu untuk admin, satu untuk portal — supaya tidak saling menimpa sesi login.
- File gambar/PDF apa saja di komputer kamu untuk dipakai sebagai "bukti transfer" (isinya tidak penting, cukup untuk uji unggah).

**Akun yang dipakai** (semua password `password`):

| Peran | Email | Keterangan |
|---|---|---|
| Admin Keuangan SMP | `keuangan.smp@alhikmah.sch.id` | Kelola jenis tagihan, verifikasi pembayaran, catat manual, kelola cicilan |
| Kepala Sekolah SMP | `kepsek.smp@alhikmah.sch.id` | Hanya bisa lihat Tagihan (read-only), tidak punya akses verifikasi pembayaran |

Akun portal akan kamu buat sendiri di Bagian 1.

---

## 0. Persiapan Data

Login sebagai `keuangan.smp@alhikmah.sch.id`.

- [ ] **0.1** Buka "Jenis Tagihan", buat: Nama = "Biaya Pendaftaran", Kategori = "Pendaftaran", nominal Reguler = `150000`.
- [ ] **0.2** Buat satu lagi: Nama = "Uang Pangkal", Kategori = "Daftar Ulang", **"Bisa dicicil" dicentang**, Maksimal Cicilan = `3`, nominal Reguler = `900000` (sengaja angka yang tidak habis dibagi 3, untuk menguji pembulatan di Bagian 3).
- [ ] **0.3** Di tab/sesi lain, jalankan wizard SPMB publik untuk SMP (`/spmb/{slug-smp}`) sampai selesai, pilih jalur **Reguler**, gunakan email `siswa.lunas@example.test`. Catat kode pendaftarannya sebagai **Kandidat A**.
- [ ] **0.4** Ulangi sekali lagi wizard SPMB, jalur **Reguler**, email `siswa.cicil@example.test`. Catat kode pendaftarannya sebagai **Kandidat B**.
- [ ] **0.5** Sebagai admin, buka menu "Verifikasi & Keputusan", verifikasi dokumen dan tetapkan keputusan **"Diterima"** untuk Kandidat A dan Kandidat B.
- [ ] **0.6** Untuk masing-masing, buka panel "Tagihan" di halaman detail — pastikan **Tagihan Pendaftaran** (Rp 150.000) dan **Tagihan Daftar Ulang** (Rp 900.000, status "Belum Bayar") sudah otomatis muncul untuk keduanya.

---

## 1. Portal — Bayar Lunas, Ditolak, lalu Diterima (Kandidat A)

- [ ] **1.1** Buka `/portal/register` (sesi portal terpisah), daftar dengan email `siswa.lunas@example.test`. Verifikasi OTP dari log. Harus masuk ke `/portal/dashboard` dan pendaftaran Kandidat A langsung muncul (penautan otomatis, sudah diuji di panduan sub-project 1).
- [ ] **1.2** Klik menu sidebar **"Tagihan & Pembayaran"**. Tagihan Daftar Ulang Kandidat A harus tampil, status "Belum Bayar", dengan tombol **"Bayar Lunas"** dan (karena "Uang Pangkal" bisa dicicil) tombol/dropdown **"Mulai Cicil"** juga.
- [ ] **1.3** Pilih file apa saja, klik "Bayar Lunas". Harus kembali ke halaman ini dengan pesan sukses. Tombol "Bayar Lunas"/"Mulai Cicil" **harus hilang sekarang** (karena sudah ada pembayaran menunggu verifikasi) — status tagihan sendiri tetap "Belum Bayar" (belum diverifikasi admin).
- [ ] **1.4** Buka "Riwayat Transaksi" pada tagihan ini — harus muncul 1 baris berstatus "Menunggu verifikasi".
- [ ] **1.5** Pindah ke sesi admin. Buka menu sidebar **"Verifikasi Pembayaran"** (grup "IV. Keuangan"). Pembayaran Kandidat A harus muncul di antrian.
- [ ] **1.6** Klik "Tinjau →" — harus masuk ke halaman detail pendaftaran Kandidat A **tanpa error 403**. (Ini bagian yang sebelum diperbaiki tidak bisa diakses admin keuangan sama sekali.)
- [ ] **1.7** Di panel "Tagihan", baris Tagihan Daftar Ulang sekarang harus menampilkan kotak "Menunggu verifikasi bukti transfer" dengan link **"Lihat bukti transfer"** (klik, pastikan file yang tadi diunggah benar-benar terbuka) dan tombol **"Terima"**/**"Tolak"**.
- [ ] **1.8** Klik **"Tolak"**, isi alasan penolakan (wajib — coba submit kosong dulu, harus ditolak validasi), lalu submit dengan alasan misalnya "Bukti tidak jelas, mohon unggah ulang".
- [ ] **1.9** Kembali ke sesi portal, refresh. Tombol "Bayar Lunas"/"Mulai Cicil" harus **muncul lagi** (percobaan ditolak, bukan dead-end). Buka "Riwayat Transaksi" — baris lama tetap ada dengan badge "Ditolak" dan catatan alasannya, **tidak hilang atau tertimpa**.
- [ ] **1.10** Unggah bukti lagi, "Bayar Lunas". Kembali ke sesi admin, tinjau lagi, kali ini klik **"Terima"**.
- [ ] **1.11** Refresh panel admin — Tagihan Daftar Ulang Kandidat A sekarang berstatus **"Lunas"**, kotak verifikasi menghilang. Sesi portal juga menunjukkan status "Lunas" dan riwayat sekarang punya 2 baris (1 ditolak, 1 lunas) — keduanya tetap tercatat, tidak ada yang hilang.

---

## 2. Portal — Cicilan & Urutan Wajib (Kandidat B)

- [ ] **2.1** Daftar akun portal baru dengan email `siswa.cicil@example.test`, verifikasi OTP, buka "Tagihan & Pembayaran". Kali ini pilih **"Cicil 3x"** lalu klik "Mulai Cicil".
- [ ] **2.2** Halaman sekarang menampilkan 3 baris termin. Cek nominalnya: termin 1 & 2 = `Rp 300.000`, termin 3 = `Rp 300.000` juga kalau genap — untuk Rp 900.000/3 ini pas habis (900000/3=300000), jadi ketiganya sama. **Catatan:** kalau kamu mau menguji kasus sisa pembulatan yang ganjil, ulangi Bagian 0 dengan nominal Uang Pangkal `Rp 1.000.000` lalu cicil 3x — cek termin 1&2 = `Rp 333.333`, termin 3 = `Rp 333.334` (selisihnya masuk ke termin terakhir, total tetap persis Rp 1.000.000).
- [ ] **2.3** Hanya termin 1 yang punya form "Kirim Bukti" — termin 2 dan 3 tidak ada aksi apapun (masih "Belum Bayar" abu-abu), karena belum boleh dibayar dulu.
- [ ] **2.4** Unggah bukti untuk termin 1. Sesi admin: buka "Verifikasi Pembayaran", tinjau, **Terima**.
- [ ] **2.5** Refresh portal — termin 1 sekarang "Lunas", dan **termin 2 baru sekarang** muncul form "Kirim Bukti"-nya (sebelumnya tidak ada). Termin 3 masih terkunci.
- [ ] **2.6** Unggah bukti termin 2, tapi kali ini di sesi admin klik **"Tolak"** dengan alasan "Nominal tidak sesuai". Refresh portal — termin 2 form "Kirim Bukti" muncul lagi (boleh diunggah ulang), termin 1 tetap "Lunas" tidak berubah.
- [ ] **2.7** Unggah ulang bukti termin 2, admin **Terima**. Termin 3 sekarang terbuka.
- [ ] **2.8** Coba secara paksa (opsional, untuk yang teknis): kalau kamu bisa mengirim POST langsung ke endpoint bayar-cicilan untuk termin yang belum waktunya (misalnya pakai DevTools sebelum termin sebelumnya lunas), server harus menolaknya — bukan cuma disembunyikan di tampilan. Kalau tidak terbiasa dengan DevTools, lewati langkah ini; ini sudah tercakup di automated test.
- [ ] **2.9** Bayar & terima termin 3. Panel admin dan portal harus sama-sama menunjukkan Tagihan Daftar Ulang Kandidat B sekarang **"Lunas"** (bukan cuma termin terakhirnya saja) — total tagihan dianggap lunas begitu semua termin lunas.

---

## 3. Jalur Cadangan — Admin Catat Manual Langsung

Untuk kasus tunai/lapor langsung, tanpa menunggu unggahan calon siswa.

- [ ] **3.1** Buat Kandidat C baru (submit SPMB jalur Reguler, verifikasi dokumen, tetapkan Diterima) — cukup ulangi langkah 0.3–0.6 dengan email `siswa.manual@example.test`.
- [ ] **3.2** Di panel "Tagihan" Kandidat C, klik **"Catat Lunas (Tanpa Cicilan)"** pada baris Tagihan Daftar Ulang — **tanpa** calon siswa mengunggah apapun.
- [ ] **3.3** Status langsung **"Lunas"**, tanpa tahap "menunggu verifikasi". Kalau Kandidat C mendaftar akun portal dan cek riwayat transaksinya, baris ini harus tampil dengan keterangan "Dicatat admin" (bukan "Anda").

---

## 4. Admin Membuatkan & Menyunting Skema Cicilan

- [ ] **4.1** Buat Kandidat D (ulangi 0.3–0.6, email `siswa.skemaadmin@example.test`). Di panel Tagihan Kandidat D, pilih jumlah termin lalu klik **"Buat Skema"** (bukan dari portal, langsung dari sisi admin).
- [ ] **4.2** Skema dan 3 baris termin harus langsung terbentuk, sama seperti kalau calon siswa yang membuatnya sendiri.
- [ ] **4.3** Coba akses form sunting nominal manual (kalau ada di panel/rute terkait), ubah salah satu termin sehingga totalnya **tidak lagi sama** dengan Rp 900.000 — harus ditolak dengan pesan error, dan nominal lama tetap tersimpan (tidak ada yang berubah sebagian).
- [ ] **4.4** Ubah lagi dengan kombinasi yang totalnya **tetap Rp 900.000** (misalnya 500rb/200rb/200rb) — harus tersimpan.

---

## 5. Hak Akses

- [ ] **5.1** Logout dari admin, login sebagai `kepsek.smp@alhikmah.sch.id`. Buka menu "Tagihan" — masih bisa diakses (read-only, seperti sebelumnya).
- [ ] **5.2** Sidebar kepala sekolah **tidak boleh** punya menu "Verifikasi Pembayaran" — perannya tidak diberi izin ini.
- [ ] **5.3** Coba akses langsung URL antrian verifikasi pembayaran sambil login sebagai kepala sekolah — harus ditolak (403).
- [ ] **5.4** Buka salah satu detail pendaftaran yang punya pembayaran menunggu verifikasi — tombol "Terima"/"Tolak" dan "Catat Lunas"/"Buat Skema" semuanya **tidak boleh muncul** untuk kepala sekolah.
- [ ] **5.5** Login lagi sebagai `keuangan.smp@alhikmah.sch.id` — pastikan sekarang bisa membuka halaman detail pendaftaran manapun tanpa 403 (ini yang sempat rusak sebelum perbaikan terakhir).

---

## 6. Isolasi Antar Lembaga & Antar Akun Portal

- [ ] **6.1** Login sebagai `keuangan.sma@alhikmah.sch.id` (lembaga berbeda). Buka "Verifikasi Pembayaran" — antrian harus **kosong** (semua pembayaran di atas milik SMP).
- [ ] **6.2** Coba akses langsung URL detail pendaftaran Kandidat A/B/C/D (ganti ID di address bar) sambil login sebagai admin keuangan SMA — harus **404**, bukan halaman kosong atau data tertukar.
- [ ] **6.3** Login sebagai akun portal `siswa.lunas@example.test` (Kandidat A). Catat URL tombol "Kirim Bukti"/"Bayar Lunas" salah satu tagihannya kalau memungkinkan, lalu logout dan login sebagai akun portal `siswa.cicil@example.test` (Kandidat B). Dashboard "Tagihan & Pembayaran" B **hanya** boleh menampilkan tagihan milik B — tagihan Kandidat A tidak boleh terlihat sama sekali, dan mencoba mengakses rute pembayaran milik A langsung dari address bar (ganti ID tagihan) sambil login sebagai B harus **404**.

---

## Ringkasan Checklist Cepat

| # | Skenario | Hasil yang Diharapkan | ✅/❌ |
|---|----------|------------------------|-------|
| 0 | Setup jenis tagihan + 2 kandidat diterima | Tagihan pendaftaran & daftar ulang otomatis terbentuk | |
| 1 | Bayar lunas → ditolak → unggah ulang → diterima | Riwayat tidak pernah hilang, status akhir Lunas | |
| 2 | Cicilan 3x, urutan wajib, tolak salah satu termin | Termin berikutnya hanya terbuka setelah yang sebelumnya lunas; tagihan lunas total setelah semua termin lunas | |
| 3 | Catat manual langsung oleh admin | Langsung lunas tanpa tahap menunggu, tercatat "Dicatat admin" | |
| 4 | Admin buat skema + sunting nominal manual | Skema terbentuk; total yang salah ditolak, tidak tersimpan sebagian | |
| 5 | Hak akses (admin keuangan vs kepala sekolah) | Kepala sekolah tidak bisa verifikasi; admin keuangan sekarang bisa buka semua detail pendaftaran | |
| 6 | Isolasi lembaga & antar akun portal | Tidak ada data yang bocor/tertukar, 404 saat dipaksa | |

**Jika semua baris di atas ✅, alur SPMB dari daftar sampai bayar dianggap teruji lengkap secara manual.** Jika ada yang ❌, catat langkah persis untuk mereproduksinya sebelum dilaporkan.
