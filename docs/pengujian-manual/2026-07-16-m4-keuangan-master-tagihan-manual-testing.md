# Panduan Pengujian Manual — Keuangan: Master Tagihan & Mesin Invoicing

**Fitur:** Master jenis tagihan (per lembaga) + nominal per jalur, dan mesin invoicing yang otomatis membuat tagihan pendaftaran (saat submit SPMB) dan tagihan daftar ulang (saat keputusan diterima) — tanpa pernah membuat tagihan Rp0 palsu untuk jalur yang belum dikonfigurasi.

**Cakupan:** Ini menguji sub-project 2 dari 3 inisiatif Keuangan, yang sudah lolos automated test (333/333 passing) dan review berlapis (per-task + whole-branch). Panduan ini fokus pada hal yang **tidak bisa** diverifikasi otomatis: tampilan visual, alur klik sungguhan di browser, dan apakah prinsip "jangan pernah berbohong soal lunas" benar-benar terasa saat dipakai.

**Catatan — jenis tagihan sekarang sudah ter-seed.** Sejak pembersihan arsitektur seeder, `migrate:fresh --seed` sudah otomatis membuat "Biaya Pendaftaran" dan "Uang Pangkal" untuk SMP dan SMA, lengkap dengan nominal per jalur (termasuk Rp0 untuk jalur Afirmasi) — kecuali jalur **Prestasi**, yang sengaja dibiarkan tanpa nominal supaya kamu tetap bisa menguji perilaku "lewati saja, jangan buat tagihan palsu" di Bagian 2 tanpa perlu membuat konfigurasi kosong secara manual dulu.

**Yang perlu disiapkan sebelum mulai:**
- Server lokal berjalan, database sudah `migrate:fresh --seed`.
- Akses ke `storage/logs/laravel.log` untuk kode OTP (dipakai lagi di langkah 3, submit SPMB baru).
- Buka sesi dalam mode private/incognito.

**Akun yang dipakai** (semua password `password`):

| Peran | Email | Keterangan |
|---|---|---|
| Admin Keuangan SMP | `keuangan.smp@alhikmah.sch.id` | Akses penuh ke Jenis Tagihan & Tagihan |
| Kepala Sekolah SMP | `kepsek.smp@alhikmah.sch.id` | Hanya bisa lihat Tagihan (read-only) |

---

## 1. Memverifikasi Jenis Tagihan & Nominal yang Sudah Ter-seed

Login sebagai `keuangan.smp@alhikmah.sch.id`.

- [ ] **1.1** Cek sidebar kiri — harus ada grup **"IV. Keuangan"** dengan menu "Jenis Tagihan" dan "Tagihan". Klik "Jenis Tagihan".
- [ ] **1.2** Harus sudah ada 2 baris: "Biaya Pendaftaran" (kategori Pendaftaran) dan "Uang Pangkal" (kategori Daftar Ulang, bisa dicicil maks 3x) — bukan halaman kosong seperti sebelumnya.
- [ ] **1.3** Buka "Kelola Nominal" pada "Biaya Pendaftaran" — jalur **Reguler** harus terisi `150000`, jalur **Afirmasi** harus terisi `0` (gratis beneran, bukan kosong), jalur **Prestasi** harus **kosong** (sengaja belum dikonfigurasi — ini yang akan diuji di Bagian 2).
- [ ] **1.4** Ulangi pengecekan yang sama untuk "Uang Pangkal" — Reguler `3000000`, Afirmasi `0`, Prestasi kosong.

---

## 2. Menguji Mesin Invoicing — Tagihan Pendaftaran (Otomatis saat Submit SPMB)

Bagian ini membuktikan prinsip inti mesin invoicing: tagihan hanya dibuat kalau memang dikonfigurasi, dan tidak pernah "berpura-pura lunas" untuk jalur yang belum diatur.

- [ ] **2.1** Buka tab baru (tanpa logout dari admin), jalankan wizard SPMB publik untuk SMP (`/spmb/{slug-smp}`) sampai selesai, pilih jalur **Reguler**. Catat kode pendaftarannya.
- [ ] **2.2** Kembali ke tab admin, buka menu "Tagihan" — pendaftaran baru ini harus muncul dengan kategori "Pendaftaran", total **Rp 150.000**, status **"Belum Bayar"**.
- [ ] **2.3** Ulangi submit SPMB baru, kali ini pilih jalur **Afirmasi**. Cek halaman "Tagihan" lagi — pendaftaran ini juga harus muncul, kategori "Pendaftaran", total **Rp 0**, tapi statusnya langsung **"Lunas"** (bukan "Belum Bayar") — karena memang benar-benar dikonfigurasi gratis, bukan karena belum diatur.
- [ ] **2.4** Ulangi submit SPMB baru sekali lagi, kali ini pilih jalur **Prestasi** (yang sengaja tidak dikonfigurasi nominalnya — lihat langkah 1.3). Cek halaman "Tagihan" — pendaftaran ini **tidak boleh muncul sama sekali** di daftar tagihan. Ini bedanya dengan langkah 2.3: bukan "lunas otomatis", tapi memang tidak ada tagihan yang tercatat — supaya nanti kalau admin keuangan lupa mengatur nominal jalur Prestasi, itu terlihat jelas sebagai "belum ada tagihan", bukan menyamar jadi "sudah lunas".

---

## 3. Menguji Mesin Invoicing — Tagihan Daftar Ulang (Otomatis saat Diterima)

- [ ] **3.1** Buka menu "Verifikasi & Keputusan" (SPMB), buka salah satu pendaftaran dari langkah 2 yang jalurnya **Reguler** (langkah 2.1).
- [ ] **3.2** Di panel "Tagihan" pada halaman detail ini, harus sudah terlihat baris "Tagihan Pendaftaran" berstatus "Belum Bayar" (dari langkah 2.2), dan baris "Tagihan Daftar Ulang" masih "Belum ada tagihan".
- [ ] **3.3** Tetapkan keputusan "Diterima" pada pendaftaran ini. Setelah tersimpan, refresh halaman — panel "Tagihan" sekarang harus menampilkan baris "Tagihan Daftar Ulang" juga, dengan total **Rp 3.000.000**.
- [ ] **3.4** Buka pendaftaran lain (jalur Reguler juga), tetapkan keputusan **"Ditolak"**. Panel "Tagihan"-nya — baris "Tagihan Daftar Ulang" harus **tetap** "Belum ada tagihan" (tidak pernah dibuat untuk yang ditolak).

---

## 4. Menguji Buat Tagihan Susulan

Data demo M3 (dibuat langsung lewat seeder, bukan lewat wizard SPMB publik, sehingga tidak pernah melewati hook otomatis pembuatan tagihan) adalah kandidat sempurna untuk ini — pendaftaran-pendaftaran itu pasti belum punya tagihan apapun, meski jenis tagihannya sendiri sudah ter-seed.

- [ ] **4.1** Buka menu "Verifikasi & Keputusan", cari pendaftaran demo bernama **"Calon Diterima (SMP Islam Al-Hikmah)"** (dari data seed M3, jalur Reguler, status sudah Diterima sejak awal).
- [ ] **4.2** Di panel "Tagihan"-nya, baris "Tagihan Pendaftaran" dan "Tagihan Daftar Ulang" harus keduanya masih "Belum ada tagihan", masing-masing dengan tombol **"Buat Tagihan Susulan"**.
- [ ] **4.3** Klik "Buat Tagihan Susulan" pada baris "Tagihan Pendaftaran". Setelah refresh, baris ini harus terisi (Rp 150.000, Belum Bayar) memakai nominal yang sudah ter-seed sejak awal (lihat langkah 1.3) — membuktikan tombol ini memakai nominal TERKINI, bukan nominal saat pendaftaran itu pertama kali dibuat (yang waktu itu belum ada tagihan apapun sama sekali).
- [ ] **4.4** Klik "Buat Tagihan Susulan" pada baris "Tagihan Daftar Ulang" juga — harus terisi Rp 3.000.000.
- [ ] **4.5** Coba klik tombol yang sama sekali lagi (kalau linknya masih ada di riwayat/refresh halaman lalu submit ulang form via DevTools, atau cukup catat bahwa tombolnya sudah hilang sekarang karena tagihan sudah ada) — pastikan tidak ada cara membuat tagihan kedua untuk kategori yang sama pada pendaftaran yang sama.

---

## 5. Menguji Halaman Monitoring Tagihan

- [ ] **5.1** Buka menu "Tagihan". Semua tagihan yang sudah dibuat di langkah 2-4 harus muncul di sini (nama calon murid, kategori, total, status).
- [ ] **5.2** Ketik nama salah satu calon murid di kotak pencarian — daftar harus menyaring hanya yang cocok, tanpa reload halaman.
- [ ] **5.3** Coba cari dengan kode pendaftaran (bukan nama) — harus tetap ketemu.
- [ ] **5.4** Filter status ke "Lunas" — hanya tagihan Afirmasi (langkah 2.3) yang muncul. Ganti ke "Belum Bayar" — sisanya muncul.

---

## 6. Menguji Hak Akses

- [ ] **6.1** Logout, login sebagai `kepsek.smp@alhikmah.sch.id`. Buka menu "Tagihan" — halaman harus tetap bisa diakses (read-only).
- [ ] **6.2** Coba akses langsung `/admin/jenis-tagihan/create` sambil login sebagai kepala sekolah — harus ditolak (403 Forbidden), karena perannya cuma dapat izin `tagihan.view`, bukan kelola jenis tagihan.
- [ ] **6.3** Buka salah satu pendaftaran yang masih punya baris tagihan kosong (kalau ada) — tombol "Buat Tagihan Susulan" **tidak boleh muncul** untuk akun kepala sekolah (izinnya cuma dimiliki admin_keuangan).

---

## 7. Menguji Isolasi Antar Lembaga

- [ ] **7.1** Login sebagai `keuangan.sma@alhikmah.sch.id` (lembaga berbeda). Buka menu "Jenis Tagihan" — harus muncul 2 baris dengan nama **persis sama** dengan milik SMP ("Biaya Pendaftaran", "Uang Pangkal"), tapi ini instance terpisah milik SMA: buka "Kelola Nominal" pada "Biaya Pendaftaran" — jalur Reguler harus `200000` (bukan `150000` seperti SMP di langkah 1.3).
- [ ] **7.2** Buka menu "Tagihan" — daftar harus kosong (belum ada tagihan transaksional apapun untuk SMA sampai titik ini).
- [ ] **7.3** Ubah nominal jalur Reguler pada "Biaya Pendaftaran" milik SMA ini, misalnya jadi `250000`, lalu simpan. Login ulang sebagai `keuangan.smp@alhikmah.sch.id` dan cek lagi nominal "Biaya Pendaftaran" SMP (langkah 1.3) — pastikan nilainya **tetap** `150000`, tidak ikut berubah meski nama jenis tagihannya sama persis di kedua lembaga.

---

## Ringkasan Checklist Cepat

| # | Skenario | Hasil yang Diharapkan | ✅/❌ |
|---|----------|------------------------|-------|
| 1 | Jenis tagihan + nominal per jalur sudah ter-seed | Data tampil benar sesuai seed, tanpa perlu dibuat manual | |
| 2 | Invoicing arah pendaftaran: dikonfigurasi / gratis asli / belum dikonfigurasi | Tagihan normal / lunas otomatis / tidak ada tagihan sama sekali (3 hasil berbeda, bukan disamaratakan) | |
| 3 | Invoicing arah daftar ulang: diterima vs ditolak | Tagihan muncul hanya saat diterima | |
| 4 | Buat Tagihan Susulan | Mengisi data lama yang terlewat, pakai nominal terkini, tidak bisa dobel | |
| 5 | Monitoring tagihan (cari, filter) | Berfungsi tanpa reload halaman | |
| 6 | Hak akses (admin keuangan vs kepala sekolah) | Kepala sekolah read-only, ditolak di aksi kelola | |
| 7 | Isolasi antar lembaga | Data SMP dan SMA tidak tertukar meski nama jenis tagihan sama | |

**Jika semua baris di atas ✅, fitur ini siap dianggap teruji secara manual.** Jika ada yang ❌, catat langkah persis untuk mereproduksinya sebelum dilaporkan.
