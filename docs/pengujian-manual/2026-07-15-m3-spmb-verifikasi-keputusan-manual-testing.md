# Panduan Pengujian Manual — M3 SPMB Verifikasi & Keputusan

**Fitur:** Sisi admin untuk memverifikasi dokumen pendaftar, mengisi nilai seleksi (per calon murid & massal), menetapkan keputusan diterima/ditolak, menerbitkan SK Penetapan Hasil (PDF batch), dan referensi SK tersebut di bukti pendaftaran publik.

**Cakupan:** Ini menguji hasil dari kedua plan M3 (data layer + admin UI), yang sudah lolos automated test (279/279 passing), review berlapis per-task, dan whole-branch review + fix (nav sidebar + akses admin yayasan) + re-review. Panduan ini fokus pada hal yang **tidak bisa** diverifikasi otomatis: tampilan visual, alur klik sungguhan di browser, dan apakah hak akses dinamis benar-benar terasa dinamis saat dipakai.

**Yang perlu disiapkan sebelum mulai:**
- Server lokal berjalan (`php artisan serve` atau via Laragon).
- Database sudah di-`migrate:fresh --seed` — data demo M3 (sebaran pendaftaran menunggu verifikasi/diterima/ditolak, nilai, dan satu SK terbit) sudah otomatis tersedia untuk kedua lembaga demo (SMP & SMA Islam Al-Hikmah), tidak perlu setup tambahan.
- Semua password akun demo di bawah ini adalah `password`.

---

## 1. Akun yang Dipakai

| Peran | Email | Cakupan | Izin `spmb-pendaftaran.*` |
|---|---|---|---|
| Kepala Sekolah SMP | `kepsek.smp@alhikmah.sch.id` | Lembaga (SMP) | Semua 5 (view, verifikasi-dokumen, nilai-seleksi, tetapkan-keputusan, terbitkan-sk) |
| Admin Administrasi SMP | `adm.smp@alhikmah.sch.id` | Lembaga (SMP) | view, verifikasi-dokumen, nilai-seleksi saja — **tidak** punya tetapkan-keputusan/terbitkan-sk |
| Kepala Sekolah SMA | `kepsek.sma@alhikmah.sch.id` | Lembaga (SMA) | Semua 5 |
| Admin Yayasan | `admin.yayasan@alhikmah.sch.id` | Yayasan (lintas lembaga) | Semua 5, tapi butuh pengalih lembaga aktif (lihat bagian 5) |

> Catatan: pembagian izin di atas persis mengikuti pembagian peran nyata yang diminta di awal — panitia/operator (admin_administrasi) hanya mencocokkan & memverifikasi berkas serta mengisi nilai, sedangkan keputusan final dan penerbitan SK adalah wewenang kepala sekolah (atau yayasan). Ini **bukan** dihardcode ke nama role — cek juga bagian 4 untuk membuktikan izinnya benar-benar berbasis permission, bukan nama role.

---

## 2. Alur Utama — Verifikasi sampai Terbit SK (sebagai Kepala Sekolah)

Login sebagai `kepsek.smp@alhikmah.sch.id`.

- [ ] **2.1** Cek sidebar kiri, grup "III. SPMB" — harus ada menu **"Verifikasi & Keputusan"**. Klik menu ini.
- [ ] **2.2** Halaman daftar pendaftaran tampil (server-side datatable — cari, filter status, urutkan, mirip halaman "Peran"). Harus ada minimal 3 baris: "Calon Menunggu Verifikasi (SMP Islam Al-Hikmah)", "Calon Diterima (...)", "Calon Ditolak (...)".
- [ ] **2.3** Coba cari (search box) dengan mengetik "Menunggu" — daftar harus menyaring ke baris yang cocok saja, tanpa reload halaman.
- [ ] **2.4** Filter status ke "Menunggu Verifikasi" — hanya baris berstatus itu yang tampil. Ganti filter ke "Diterima" dan "Ditolak", cek konsisten.
- [ ] **2.5** Klik baris "Calon Menunggu Verifikasi". Halaman detail tampil: data calon murid, daftar dokumen dengan status campuran (beberapa "Diterima", beberapa "Ditolak" dengan catatan, beberapa "Belum Diverifikasi").
- [ ] **2.6** Klik "Terima" pada salah satu dokumen berstatus "Belum Diverifikasi". Badge harus langsung berubah jadi "Diterima" tanpa reload halaman (toast konfirmasi muncul).
- [ ] **2.7** Klik "Tolak" pada dokumen lain yang belum diverifikasi. Kotak alasan penolakan muncul. Coba klik "Kirim Penolakan" **tanpa** mengisi alasan — harus ditolak dengan pesan error jelas (bukan tersimpan diam-diam). Isi alasan, kirim ulang — badge berubah jadi "Ditolak" dan catatan tampil di bawah nama dokumen.
- [ ] **2.8** Di panel "Penilaian & Keputusan", isi nilai untuk salah satu jenis tes yang belum ada nilainya (kalau semua sudah ada nilai contoh, ubah salah satu angkanya). Klik "Simpan" — toast konfirmasi muncul, angka tersimpan tanpa reload.
- [ ] **2.9** Set "Keputusan" ke "Diterima", isi catatan opsional, klik "Tetapkan Keputusan". Badge status di atas berubah jadi "Diterima" (hijau).
- [ ] **2.10** Balik ke daftar pendaftaran (langkah 2.2) — baris yang baru diputuskan harus sudah menunjukkan status "Diterima" terbaru.
- [ ] **2.11** Dari daftar, klik tombol **"Input Nilai Massal"** di pojok kanan atas. Pilih salah satu jadwal seleksi dari dropdown. Grid peserta untuk jalur/gelombang seleksi tersebut tampil, dengan nilai yang sudah pernah diisi (termasuk yang baru saja disimpan lewat langkah 2.8, kalau jenis tesnya sama) sudah ter-pre-fill.
- [ ] **2.12** Ubah salah satu nilai di grid, klik "Simpan Semua Nilai". Toast konfirmasi muncul. Buka lagi halaman detail calon murid yang nilainya baru diubah (langkah 2.5) — pastikan nilai di sana ikut berubah (membuktikan input per-calon dan input massal menulis ke data yang sama, tidak terpisah).
- [ ] **2.13** Dari daftar, klik tombol **"Terbitkan SK"**. Pilih gelombang yang sesuai. Ringkasan tampil: jumlah pendaftaran final (siap masuk SK) vs belum final. Isi Nomor SK (contoh: `421.3/SK-PPDB.002/2026`, jangan sama dengan nomor SK demo yang sudah ada) dan tanggal terbit, klik "Terbitkan SK".
- [ ] **2.14** Setelah redirect kembali ke daftar, pesan sukses "SK berhasil diterbitkan" tampil. Buka lagi halaman "Terbitkan SK" dan pilih gelombang yang sama — jumlah "final" di ringkasan harus turun (karena pendaftaran yang baru saja tercakup SK tidak dihitung lagi untuk SK berikutnya).

---

## 3. Referensi SK di Bukti Pendaftaran Publik

- [ ] **3.1** Masih di admin, buka halaman detail "Calon Diterima (SMP Islam Al-Hikmah)" — data demo ini sudah tercakup satu SK sejak seeding. Catat kode pendaftarannya (`kode_pendaftaran`, format `REG-DEMO-...`) dan email pendaftarannya (`wali.diterima@example.test`).
- [ ] **3.2** Buka tab baru (mode privat/incognito, tanpa login), akses `/spmb/{slug-lembaga}/status` (atau lewat link "Sudah mendaftar? Cek status di sini" dari halaman awal SPMB publik SMP), masukkan kode & email dari langkah 3.1.
- [ ] **3.3** Unduh PDF bukti pendaftaran. Buka filenya — harus ada baris tambahan **"Ditetapkan berdasarkan SK No. ... tanggal ..."** yang sesuai dengan SK demo (`421.3/SK-PPDB.DEMO-{id lembaga}/2026`).
- [ ] **3.4** Bandingkan dengan calon murid berstatus "Menunggu Verifikasi" (belum final, belum masuk SK manapun) — unduh bukti pendaftarannya juga. PDF ini **tidak boleh** menampilkan baris referensi SK sama sekali.

---

## 4. Izin Dinamis — Bukan Hardcode Nama Role

Bagian ini paling penting untuk dicoba manual, karena inilah tujuan utama modul ini: hak akses ditentukan oleh permission yang diberikan lewat halaman "Peran" (Role Builder), bukan oleh nama peran itu sendiri.

- [ ] **4.1** Logout, login sebagai `adm.smp@alhikmah.sch.id` (Admin Administrasi — hanya punya view + verifikasi-dokumen + nilai-seleksi). Buka menu "Verifikasi & Keputusan" — daftar tetap tampil normal (punya izin `view`).
- [ ] **4.2** Buka detail salah satu pendaftaran. Tombol "Terima"/"Tolak" pada dokumen **harus tetap muncul** (punya izin verifikasi-dokumen), begitu juga input nilai (punya izin nilai-seleksi).
- [ ] **4.3** Di panel "Penilaian & Keputusan", bagian **"Tetapkan Keputusan" (dropdown status + tombol) tidak boleh muncul sama sekali** — akun ini tidak punya izin `tetapkan-keputusan`.
- [ ] **4.4** Cek sidebar dan/atau daftar pendaftaran — tombol **"Terbitkan SK" tidak boleh muncul** untuk akun ini.
- [ ] **4.5** Coba akses langsung URL `/admin/sk-ppdb/create` sambil login sebagai akun ini. Harus ditolak (403 Forbidden), bukan halaman kosong atau redirect diam-diam.
- [ ] **4.6 — Buktikan izinnya benar-benar dinamis:** Login sebagai admin yang punya akses ke halaman "Peran" (mis. `admin.yayasan@alhikmah.sch.id`), buka `/admin/roles`, edit peran **Admin Administrasi**, centang izin **"Tetapkan Keputusan"** (di modul "Verifikasi & Keputusan SPMB"), simpan. Login ulang sebagai `adm.smp@alhikmah.sch.id` — sekarang bagian "Tetapkan Keputusan" di halaman detail **harus muncul**, tanpa ada perubahan kode apapun. Setelah selesai uji, kembalikan izin ini seperti semula (uncheck lagi) supaya data peran tidak berubah permanen dari kondisi demo awal.

---

## 5. Admin Yayasan & Pengalih Lembaga

Ini menguji perbaikan yang baru saja dilakukan — sebelumnya akun yayasan diam-diam tidak bisa memakai fitur ini sama sekali.

- [ ] **5.1** Login sebagai `admin.yayasan@alhikmah.sch.id`. Buka menu "Verifikasi & Keputusan" **sebelum** memilih lembaga aktif di pengalih lembaga (pojok kanan atas, biasanya menampilkan "Semua Lembaga"). Daftar pendaftaran harus tampil **kosong** dengan wajar (tanpa error 500), dan halaman "Terbitkan SK" / "Input Nilai Massal" harus menampilkan pesan yang mengarahkan untuk memilih lembaga aktif lebih dulu.
- [ ] **5.2** Klik pengalih lembaga di topbar, pilih "SMA Islam Al-Hikmah". Buka lagi menu "Verifikasi & Keputusan" — sekarang daftar pendaftaran SMA harus tampil (3 baris demo SMA).
- [ ] **5.3** Buka detail salah satu pendaftaran SMA, coba verifikasi dokumen, isi nilai, dan tetapkan keputusan — semua harus berhasil persis seperti saat login sebagai kepala sekolah SMA (akun yayasan punya semua 5 izin).
- [ ] **5.4** Ganti pengalih lembaga ke "SMP Islam Al-Hikmah" — daftar pendaftaran harus berganti ke data SMP, bukan tetap menampilkan data SMA sebelumnya (bukti cache/state tidak nyangkut).

---

## 6. Isolasi Antar Lembaga (Tenant Isolation)

- [ ] **6.1** Login sebagai `kepsek.smp@alhikmah.sch.id`. Catat salah satu `id` pendaftaran SMP dari URL halaman detailnya (`/admin/spmb-pendaftaran/{id}`).
- [ ] **6.2** Login sebagai `kepsek.sma@alhikmah.sch.id` (akun lembaga lain). Coba akses langsung URL detail pendaftaran SMP dari langkah 6.1. Harus mendapat halaman **404**, bukan menampilkan data calon murid SMP.
- [ ] **6.3** Sambil masih login sebagai kepala sekolah SMA, coba kirim aksi (verifikasi dokumen/simpan nilai/tetapkan keputusan) langsung ke pendaftaran SMP tersebut (via DevTools Network tab, ulangi request dengan id SMP) — semua harus ditolak 404, bukan berhasil menyimpan ke data lembaga lain.

---

## 7. Skenario Non-Blocking & SK Susulan

- [ ] **7.1** Cari (atau buat lewat halaman admin lain) satu pendaftaran baru yang belum punya dokumen terverifikasi sama sekali dan belum ada nilai. Sebagai kepala sekolah, langsung tetapkan keputusan "Diterima" tanpa menyentuh dokumen/nilai sama sekali. Harus **berhasil** — modul ini sengaja tidak memblokir keputusan meski verifikasi dokumen/nilai belum lengkap (keputusan akhir tetap wewenang kepala sekolah).
- [ ] **7.2** Terbitkan SK lagi untuk gelombang yang sama seperti langkah 2.13 (SK "susulan") setelah ada pendaftaran final baru yang belum tercakup SK manapun (termasuk hasil langkah 7.1 jika gelombangnya sama). Buka lagi detail pendaftaran yang **sudah** tercakup SK pertama (langkah 2.13/3.1) — pastikan `sk_ppdb_id`-nya **tidak berubah** ke SK susulan ini (bisa dicek lewat bukti pendaftaran publik di bagian 3 — nomor SK yang tampil harus tetap yang pertama).

---

## 8. Tampilan & Pengalaman Pengguna (UX)

- [ ] Cek tampilan seluruh halaman M3 (daftar, detail, nilai massal, terbitkan SK) memakai gaya visual admin panel yang sama (warna ink/brass/paper, font Fraunces+Inter) — bukan gaya biru wizard publik SPMB.
- [ ] Cek tampilan di layar sempit (mode HP di DevTools) — tabel dan form tetap enak dipakai, tidak ada elemen terpotong.
- [ ] Cek semua toast konfirmasi (setelah verifikasi dokumen, simpan nilai, tetapkan keputusan) muncul dan hilang otomatis dengan wajar, tidak menumpuk.
- [ ] Cek tombol "Terbitkan SK" dan "Simpan Semua Nilai" tidak bisa diklik berkali-kali menyebabkan submit ganda saat koneksi lambat.

---

## Ringkasan Checklist Cepat

| # | Skenario | Hasil yang Diharapkan | ✅/❌ |
|---|----------|------------------------|-------|
| 2 | Alur lengkap verifikasi → nilai → keputusan → terbit SK | Semua langkah berhasil, tanpa reload halaman | |
| 3 | Referensi SK muncul di bukti pendaftaran publik | Muncul untuk yang sudah tercakup SK, tidak muncul untuk yang belum | |
| 4 | Izin dinamis (bukan hardcode role) | Tombol/aksi muncul-hilang sesuai izin, berubah saat izin di-toggle di halaman Peran | |
| 5 | Admin yayasan + pengalih lembaga | Kosong dengan wajar sebelum pilih lembaga, berfungsi penuh sesudahnya | |
| 6 | Isolasi antar lembaga | 404 saat lintas lembaga, bukan bocor data | |
| 7 | Keputusan non-blocking + SK susulan | Keputusan tetap bisa tanpa dokumen/nilai lengkap; SK lama tidak tertimpa SK baru | |

**Jika semua baris di atas ✅, fitur ini siap dianggap teruji secara manual.** Jika ada yang ❌, catat langkah persis untuk mereproduksinya sebelum dilaporkan.
