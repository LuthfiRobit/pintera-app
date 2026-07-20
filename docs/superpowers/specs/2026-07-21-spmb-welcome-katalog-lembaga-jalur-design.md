# Restrukturisasi Pendaftaran Calon Siswa — Sub-project 1: Welcome & Katalog Lembaga/Jalur — Design Spec

**Tanggal:** 2026-07-21
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Ini adalah sub-project 1 dari 4 dalam inisiatif "restrukturisasi alur pendaftaran calon siswa" — mengganti alur SPMB anonim yang ada sekarang (verifikasi email-OTP dulu sebelum ada akun, lalu wizard data-diri → formulir-tambahan → upload-dokumen → review) dengan alur **account-first**: welcome → pilih lembaga → pilih jalur → register akun → verifikasi → dashboard → wizard → pembayaran.

Sub-project ini secara spesifik menutup langkah 1-2 dari alur baru: halaman **welcome** (menampilkan seluruh lembaga sebagai card) dan halaman **detail lembaga** (menampilkan seluruh jalur lembaga itu sebagai card, lengkap dengan info tes/kuota/biaya). Kedua halaman ini murni publik (tanpa login), menggantikan `Spmb\PortalController::index` yang sekarang menggabungkan kedua konsep itu jadi satu halaman per-lembaga.

Sub-project 2 (registrasi akun + verifikasi), 3 (dashboard + wizard ter-otentikasi), dan 4 (pembayaran biaya pendaftaran + info jadwal tes) adalah spec/plan terpisah, belum dimulai.

## 2. Referensi Visual

Desain sudah dieksplorasi penuh lewat mockup interaktif di **https://claude.ai/code/artifact/a1987ae5-0050-440d-af88-08cfe01415af** ("Pintera SPMB — Alur Pendaftaran Lengkap Mockup") — layar 1 ("Welcome") dan layar 2 ("Detail Lembaga & Jalur") adalah cakupan sub-project ini secara spesifik. Layar 3-7 di artifact yang sama (Register, OTP, Masuk, Dashboard, Wizard) adalah referensi untuk sub-project 2-3, ditunjukkan di sini murni supaya arah keseluruhan alur terlihat konsisten satu sama lain.

Sistem token visual: palet navy (`--rg-primary:#1E3A5F`, `--rg-primary-deep:#16283F`, `--rg-primary-tint:#E7EEF5`, `--rg-ink:#101828`, `--rg-ink-soft:#667085`, `--rg-ink-faint:#8A93A3`, `--rg-success-bg:#ECFDF3`/`--rg-success-ink:#027A48`, `--rg-warn-bg:#FFFAEB`/`--rg-warn-ink:#B54708`), font Outfit, ikon SVG outline (stroke-width 1.8-2.4) — diadaptasi dari artifact desain acuan proyek ("Pintera — Arah Desain (TailAdmin-style)", https://claude.ai/code/artifact/3684b529-993e-4198-b528-4ec290ba86d9), memakai varian navy khusus portal calon siswa yang sudah ditetapkan artifact itu (terpisah dari indigo panel admin).

**Wajib responsif di semua ukuran layar** — ini prinsip yang berlaku untuk seluruh inisiatif, bukan cuma sub-project ini: navbar berubah jadi hamburger menu di ≤720px, grid card runtuh ke 1 kolom di breakpoint yang sesuai, dan elemen horizontal-scroll (kalau ada) harus benar-benar bisa di-scroll penuh (bukan cuma keliatan overflow), bukan cuma "keliatan responsif" secara visual.

## 3. Lingkup

### 3.1 Halaman Welcome (baru)

Route baru `GET /spmb` (tanpa parameter lembaga — global, bukan per-yayasan, karena sistem saat ini cuma punya 1 Yayasan). Struktur (lihat mockup layar 1):

1. **Navbar**: logo+brand, nav link (Beranda/Lembaga/Alur Pendaftaran/Bantuan — link internal ke section di halaman yang sama via anchor, kecuali "Bantuan" yang bisa jadi placeholder sampai ada halaman bantuan sungguhan), tombol "Masuk" (ghost — kalau `Route::has('portal.login')` sudah ada dari sub-project 2 arahkan ke sana, kalau belum arahkan balik ke `#lembaga` dengan pesan sama seperti tombol Daftar Jalur Ini di §3.2) + "Daftar Akun" (solid, **selalu** scroll ke section lembaga via anchor `#lembaga` — tidak pernah menebak lembaga/jalur mana yang dimaksud, karena akun baru selalu dimulai dari memilih lembaga/jalur dulu terlebih dahulu, sesuai keputusan "tidak ada opsi pilih lembaga di wizard").
2. **Hero**: eyebrow badge (musim penerimaan aktif), headline, deskripsi, 2 CTA ("Lihat Lembaga & Jalur" primer → scroll ke `#lembaga`, "Cek Status Pendaftaran" outline → ke `CekStatusController` yang sudah ada, styling lama tidak berubah di sub-project ini). Panel navy di kanan: ringkasan (jumlah lembaga, jumlah sedang buka, jumlah jalur aktif — dihitung real dari DB, bukan hardcode), catatan gelombang yang sedang berjalan (ambil dari `GelombangPpdb` yang `tanggal_buka <= now() <= tanggal_tutup`, lembaga & tanggal tutup terdekat), tombol "Mulai Pendaftaran" → scroll ke `#lembaga`.
3. **Section Lembaga**: filter chip jenjang (Semua/SMP/SMA/dst — dihitung dinamis dari `bentuk_pendidikan` yang benar-benar ada di data, bukan hardcode 2 pilihan), grid 3 kolom card. Tiap card: jenjang badge, status badge (ikon+teks, **bukan warna saja** — "Dibuka"/"Ditutup" berdasarkan ada-tidaknya `GelombangPpdb` yang sedang buka untuk lembaga itu, di jalur manapun), nama lembaga, alamat singkat (`kecamatan`, `kabupaten_kota`), meta row (jumlah jalur aktif / biaya pendaftaran termurah di antara jalurnya / tanggal tutup gelombang terdekat), CTA "Lihat Jalur"/"Lihat Detail" → `GET /spmb/{lembagaSlug}`. **Lembaga tanpa gelombang aktif tetap tampil** (dengan badge tertutup, opacity berkurang), tidak disembunyikan — sesuai keputusan yang sudah dikunci.
4. **Section Alur Pendaftaran**: 4 langkah statis (Daftar Akun → Isi Data & Dokumen → Bayar Biaya Daftar → Ikuti Seleksi) — murni informatif, tidak ada logic/data dinamis, jadi preview alur keseluruhan bagi pengunjung baru.
5. **Footer**: brand+deskripsi, navigasi, kontak (ambil dari `Yayasan` — telepon/email/alamat), copyright.

### 3.2 Halaman Detail Lembaga & Jalur (menggantikan `PortalController::index`)

Route `GET /spmb/{lembagaSlug}` (URL yang sama dengan `PortalController::index` sekarang, tapi kontennya diganti total — bukan menambah parameter baru). Struktur (lihat mockup layar 2):

1. **Navbar**: sama seperti welcome, tapi nav link "Lembaga" jadi active state, bukan "Beranda".
2. **Breadcrumb**: Beranda (`GET /spmb`) → nama lembaga saat ini.
3. **Header profil lembaga**: panel navy (gradient sama seperti hero panel welcome) — ikon jenjang, nama lembaga, alamat, akreditasi, tahun ajaran aktif, status gelombang (badge + tanggal tutup gelombang yang sedang buka; kalau tidak ada gelombang buka, badge "Ditutup" tanpa tanggal).
4. **Tip box**: penjelasan singkat bahwa jalur yang dipilih di halaman ini otomatis tersimpan (session) sampai pendaftaran pertama berhasil dikirim — persiapan mental pengunjung sebelum lanjut ke sub-project 2's register flow.
5. **Section jalur**: grid 3 kolom (`JalurPpdb::where('status_aktif', true)` untuk `TahunAjaran` aktif lembaga itu — bukan `JalurPpdb` dari tahun ajaran lama/tidak aktif), diurutkan berdasarkan `id` ascending (urutan seeding/pembuatan). Jalur bernama persis "Reguler" (kalau ada di lembaga itu) ditandai `.featured` dengan ribbon "Paling Umum" — aturan ini berbasis nama, bukan posisi/urutan, supaya tidak arbitrer; kalau lembaga tidak punya jalur bernama "Reguler", tidak ada card yang di-featured sama sekali. Tiap card jalur:
   - Nama + deskripsi jalur.
   - Kuota (dari `GelombangPpdb.kuota` yang sedang buka untuk jalur itu — kalau tidak ada gelombang buka untuk jalur itu spesifik, tampilkan "Belum ada gelombang buka" alih-alih angka).
   - Tahap seleksi: chip nama-nama `JenisTesMaster` dari `SeleksiPpdb` yang terkait jalur+gelombang aktif itu (kalau jalur seperti Afirmasi tidak punya `SeleksiPpdb` sama sekali, tampilkan "Tanpa tes tambahan" sebagai teks biasa, bukan chip kosong).
   - **Blok biaya pendaftaran — 3 kondisi berbeda, wajib dibedakan** (bukan cuma dekorasi, ini mencerminkan perilaku `TagihanGenerator` yang sudah ada): (a) ada `NominalTagihanJalur` dengan nominal > 0 → tampilkan nominal asli (format Rupiah); (b) ada `NominalTagihanJalur` dengan nominal = 0 → tampilkan "Gratis" dengan warna sukses; (c) tidak ada baris `NominalTagihanJalur` sama sekali untuk kombinasi jenis-tagihan-pendaftaran × jalur ini → tampilkan "Menunggu Konfirmasi Admin" dengan warna warning, BUKAN disembunyikan atau dianggap error.
   - Tombol "Daftar Jalur Ini" → **fungsional penuh di sub-project ini**, bukan ditunda: menyimpan `lembaga_id`+`jalur_id` yang dipilih ke session (key session `spmb_pilihan.lembaga_id`/`spmb_pilihan.jalur_id`, dipilih agar tidak bentrok dengan `PendaftaranWizardSession`'s existing `spmb_wizard.*` key namespace — sub-project 2/3 nanti membaca key ini saat register/dashboard perlu tahu konteks pilihan). Setelah menyimpan session, redirect: kalau route bernama `spmb.register` sudah terdaftar (dicek via `Route::has('spmb.register')` — akan true begitu sub-project 2 selesai, tanpa perlu ubah kode di sub-project ini lagi), redirect ke sana; kalau belum terdaftar (kondisi sekarang, sebelum sub-project 2 ada), redirect balik ke halaman detail lembaga dengan flash message "Fitur pendaftaran akan segera hadir." Session tetap tersimpan meski redirect balik, supaya begitu sub-project 2 rilis, pilihan yang sudah dibuat pengunjung tidak hilang.
6. **Footer**: sama seperti welcome.

## 4. Non-Tujuan

- Halaman register, verifikasi OTP, login, dashboard, wizard, dan pembayaran — sub-project 2, 3, 4 masing-masing terpisah.
- Redesign visual `CekStatusController` — dikonfirmasi jadi tugas tersendiri setelah sub-project ini, bukan bagian dari scope ini.
- Perubahan pada `PortalController` di admin panel (beda konsep, beda controller) — tidak disentuh.
- Multi-yayasan / yayasan-scoped routing — sistem saat ini cuma 1 Yayasan, welcome page tetap global.

## 5. Rencana Pengujian

- Welcome page: jumlah lembaga di grid cocok dengan `Lembaga::count()`; lembaga dengan gelombang aktif dapat badge "Dibuka", tanpa gelombang aktif dapat badge "Ditutup" (bukan disembunyikan); filter chip jenjang menyaring dengan benar; angka ringkasan di hero panel (jumlah lembaga/sedang buka/jalur aktif) dihitung benar dari DB.
- Detail lembaga: jalur yang tampil cuma dari tahun ajaran aktif lembaga itu (bukan tahun ajaran lama/duplikat); kuota, tes, dan **ketiga kondisi biaya pendaftaran** (nominal asli / gratis / belum dikonfigurasi) masing-masing punya test tersendiri, khususnya kasus jalur Prestasi (deliberately unconfigured di seed data sub-project 2) harus tampil "Menunggu Konfirmasi Admin", bukan error atau Rp0.
- Responsivitas: breakpoint navbar-hamburger, grid-collapse, dan (kalau ada elemen horizontal-scroll di halaman ini) area itu bisa di-scroll penuh — dicek minimal lewat pengujian manual di beberapa lebar viewport, dicatat di dokumen pengujian manual milik sub-project ini.
- Regresi: route lama `GET /spmb/{lembagaSlug}` tidak lagi memanggil `Spmb\PortalController::index` versi lama — pastikan tidak ada test lama yang masih menguji behavior lama tanpa diperbarui (test lama untuk `PortalController::index`, kalau ada, perlu diperbarui atau dihapus sesuai isi controller baru).
