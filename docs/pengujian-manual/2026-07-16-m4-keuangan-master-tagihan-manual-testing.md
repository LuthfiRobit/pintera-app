# Panduan Pengujian Manual — Keuangan: Master Tagihan & Mesin Invoicing

**Fitur:** Master jenis tagihan (per lembaga) + nominal per jalur, dan mesin invoicing yang otomatis membuat tagihan pendaftaran (saat submit SPMB) dan tagihan daftar ulang (saat keputusan diterima) — tanpa pernah membuat tagihan Rp0 palsu untuk jalur yang belum dikonfigurasi.

**Cakupan:** Ini menguji sub-project 2 dari 3 inisiatif Keuangan, yang sudah lolos automated test (333/333 passing) dan review berlapis (per-task + whole-branch). Panduan ini fokus pada hal yang **tidak bisa** diverifikasi otomatis: tampilan visual, alur klik sungguhan di browser, dan apakah prinsip "jangan pernah berbohong soal lunas" benar-benar terasa saat dipakai.

**Penting — tidak ada data jenis tagihan bawaan.** Berbeda dengan modul-modul sebelumnya, `migrate:fresh --seed` **tidak** membuat jenis tagihan/nominal apapun — tabelnya benar-benar kosong di awal. Ini disengaja: langkah pertama panduan ini adalah membuat konfigurasinya sendiri, supaya kamu melihat seluruh alur dari nol persis seperti admin keuangan sungguhan akan mengalaminya.

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

## 1. Membuat Jenis Tagihan & Nominal per Jalur

Login sebagai `keuangan.smp@alhikmah.sch.id`.

- [ ] **1.1** Cek sidebar kiri — harus ada grup baru **"IV. Keuangan"** dengan menu "Jenis Tagihan" dan "Tagihan". Klik "Jenis Tagihan".
- [ ] **1.2** Halaman kosong ("Belum ada jenis tagihan"). Klik "Tambah Jenis Tagihan".
- [ ] **1.3** Isi: Nama = "Biaya Pendaftaran", Kategori = "Pendaftaran", "Bisa dicicil" **tidak** dicentang. Simpan. Harus diarahkan langsung ke halaman "Kelola Nominal".
- [ ] **1.4** Di halaman nominal, harus muncul 3 baris jalur: **Reguler, Prestasi, Afirmasi** (jalur yang sudah ada dari data demo SMP). Isi nominal: Reguler = `150000`, Afirmasi = `0` (gratis beneran), **biarkan Prestasi kosong** (sengaja tidak dikonfigurasi — ini yang akan kita uji di bagian 2). Simpan.
- [ ] **1.5** Buka lagi halaman nominal jenis tagihan ini — pastikan tiga nilai tadi (150000, kosong, 0) tersimpan dan tampil kembali dengan benar setelah reload.
- [ ] **1.6** Buat satu jenis tagihan lagi: Nama = "Uang Pangkal", Kategori = "Daftar Ulang", "Bisa dicicil" **dicentang**, Maksimal Cicilan = `3`. Simpan, lalu set nominal untuk Reguler = `3000000` (Prestasi/Afirmasi boleh dikosongkan).

---

## 2. Menguji Mesin Invoicing — Tagihan Pendaftaran (Otomatis saat Submit SPMB)

Bagian ini membuktikan prinsip inti mesin invoicing: tagihan hanya dibuat kalau memang dikonfigurasi, dan tidak pernah "berpura-pura lunas" untuk jalur yang belum diatur.

- [ ] **2.1** Buka tab baru (tanpa logout dari admin), jalankan wizard SPMB publik untuk SMP (`/spmb/{slug-smp}`) sampai selesai, pilih jalur **Reguler**. Catat kode pendaftarannya.
- [ ] **2.2** Kembali ke tab admin, buka menu "Tagihan" — pendaftaran baru ini harus muncul dengan kategori "Pendaftaran", total **Rp 150.000**, status **"Belum Bayar"**.
- [ ] **2.3** Ulangi submit SPMB baru, kali ini pilih jalur **Afirmasi**. Cek halaman "Tagihan" lagi — pendaftaran ini juga harus muncul, kategori "Pendaftaran", total **Rp 0**, tapi statusnya langsung **"Lunas"** (bukan "Belum Bayar") — karena memang benar-benar dikonfigurasi gratis, bukan karena belum diatur.
- [ ] **2.4** Ulangi submit SPMB baru sekali lagi, kali ini pilih jalur **Prestasi** (yang sengaja tidak dikonfigurasi nominalnya di langkah 1.4). Cek halaman "Tagihan" — pendaftaran ini **tidak boleh muncul sama sekali** di daftar tagihan. Ini bedanya dengan langkah 2.3: bukan "lunas otomatis", tapi memang tidak ada tagihan yang tercatat — supaya nanti kalau admin keuangan lupa mengatur nominal jalur Prestasi, itu terlihat jelas sebagai "belum ada tagihan", bukan menyamar jadi "sudah lunas".

---

## 3. Menguji Mesin Invoicing — Tagihan Daftar Ulang (Otomatis saat Diterima)

- [ ] **3.1** Buka menu "Verifikasi & Keputusan" (SPMB), buka salah satu pendaftaran dari langkah 2 yang jalurnya **Reguler** (langkah 2.1).
- [ ] **3.2** Di panel "Tagihan" pada halaman detail ini, harus sudah terlihat baris "Tagihan Pendaftaran" berstatus "Belum Bayar" (dari langkah 2.2), dan baris "Tagihan Daftar Ulang" masih "Belum ada tagihan".
- [ ] **3.3** Tetapkan keputusan "Diterima" pada pendaftaran ini. Setelah tersimpan, refresh halaman — panel "Tagihan" sekarang harus menampilkan baris "Tagihan Daftar Ulang" juga, dengan total **Rp 3.000.000**.
- [ ] **3.4** Buka pendaftaran lain (jalur Reguler juga), tetapkan keputusan **"Ditolak"**. Panel "Tagihan"-nya — baris "Tagihan Daftar Ulang" harus **tetap** "Belum ada tagihan" (tidak pernah dibuat untuk yang ditolak).

---

## 4. Menguji Buat Tagihan Susulan

Data demo M3 (dibuat sebelum jenis tagihan ada sama sekali) adalah kandidat sempurna untuk ini — pendaftaran-pendaftaran itu pasti belum punya tagihan apapun.

- [ ] **4.1** Buka menu "Verifikasi & Keputusan", cari pendaftaran demo bernama **"Calon Diterima (SMP Islam Al-Hikmah)"** (dari data seed M3, jalur Reguler, status sudah Diterima sejak awal).
- [ ] **4.2** Di panel "Tagihan"-nya, baris "Tagihan Pendaftaran" dan "Tagihan Daftar Ulang" harus keduanya masih "Belum ada tagihan", masing-masing dengan tombol **"Buat Tagihan Susulan"**.
- [ ] **4.3** Klik "Buat Tagihan Susulan" pada baris "Tagihan Pendaftaran". Setelah refresh, baris ini harus terisi (Rp 150.000, Belum Bayar) memakai nominal yang baru saja kamu atur di langkah 1.4 — membuktikan tombol ini memakai nominal TERKINI, bukan nominal saat pendaftaran itu pertama kali dibuat (yang waktu itu belum ada sama sekali).
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

- [ ] **7.1** Login sebagai `keuangan.sma@alhikmah.sch.id` (lembaga berbeda). Buka menu "Jenis Tagihan" — daftar harus kosong (jenis tagihan yang dibuat di langkah 1 adalah milik SMP, tidak boleh terlihat di sini).
- [ ] **7.2** Buka menu "Tagihan" — daftar juga harus kosong (belum ada tagihan apapun untuk SMA sampai titik ini).
- [ ] **7.3** Buat jenis tagihan baru di sini (misalnya "Biaya Pendaftaran" juga, kategori Pendaftaran, nominal Reguler = `100000`) — pastikan ini tidak memengaruhi/tertukar dengan jenis tagihan SMP dari langkah 1, meski namanya sama persis.

---

## Ringkasan Checklist Cepat

| # | Skenario | Hasil yang Diharapkan | ✅/❌ |
|---|----------|------------------------|-------|
| 1 | Membuat jenis tagihan + nominal per jalur | Tersimpan dan tampil benar setelah reload | |
| 2 | Invoicing arah pendaftaran: dikonfigurasi / gratis asli / belum dikonfigurasi | Tagihan normal / lunas otomatis / tidak ada tagihan sama sekali (3 hasil berbeda, bukan disamaratakan) | |
| 3 | Invoicing arah daftar ulang: diterima vs ditolak | Tagihan muncul hanya saat diterima | |
| 4 | Buat Tagihan Susulan | Mengisi data lama yang terlewat, pakai nominal terkini, tidak bisa dobel | |
| 5 | Monitoring tagihan (cari, filter) | Berfungsi tanpa reload halaman | |
| 6 | Hak akses (admin keuangan vs kepala sekolah) | Kepala sekolah read-only, ditolak di aksi kelola | |
| 7 | Isolasi antar lembaga | Data SMP dan SMA tidak tertukar meski nama jenis tagihan sama | |

**Jika semua baris di atas ✅, fitur ini siap dianggap teruji secara manual.** Jika ada yang ❌, catat langkah persis untuk mereproduksinya sebelum dilaporkan.
