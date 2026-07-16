# M8 — Dashboard & Laporan — Design Spec

**Tanggal:** 2026-07-16
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Ini adalah modul M8 dari PRD (bagian 11), potongan terakhir yang tersisa dari "Fase 1 (Pilot)" — SPMB (M0-M3) dan Keuangan (M4-M7, minus VA BRI yang sengaja ditunda) sudah selesai dan sudah bisa dipakai end-to-end. M8 tidak menambah kemampuan transaksional baru; ini murni **agregasi read-only** dari data yang sudah ada, untuk tiga audiens: Admin Keuangan & Kepala Sekolah (level lembaga), dan Yayasan Super Admin (konsolidasi lintas lembaga) — sesuai PRD bagian 2 ("Dashboard admin keuangan & yayasan untuk memonitor status pendaftaran dan penerimaan dana lintas lembaga") dan matriks hak akses bagian 7.

M9 (Notifikasi) sengaja **tidak** termasuk di sini — dua subsistem ini independen (satu agregasi read-only, satu job terjadwal + pengiriman email) dan akan jadi sub-project terpisah setelah M8 selesai.

## 2. Lingkup

**Termasuk:**
- Memperluas `App\Http\Controllers\Admin\DashboardController` (sudah ada sejak M0) dan 2 dari 3 view-nya (`admin.dashboard.lembaga`, `admin.dashboard.yayasan`) dengan data SPMB + Keuangan.
- Section "SPMB" dan section "Keuangan" pada dashboard lembaga, masing-masing independen — muncul kalau user `can('spmb-pendaftaran.view')` / `can('tagihan.view')`.
- Dashboard yayasan: konsolidasi SUM lintas semua lembaga, dengan tabel per-lembaga yang bisa diklik untuk "masuk" ke dashboard lembaga tsb.
- Chart.js sebagai dependency baru, dirender via Alpine.js `x-init`, data di-embed langsung dari controller (tidak perlu endpoint AJAX terpisah).

**Tidak termasuk (sengaja ditunda):**
- M9 Notifikasi (sub-project terpisah).
- Filter tanggal/tahun-ajaran interaktif dari sisi user — cakupan data mengikuti default tetap (lihat bagian 4), tidak ada dropdown pemilih periode.
- Export PDF/Excel.
- Dashboard/laporan untuk E-Sarpra/E-HRD/E-BK (fase depan, di luar pilot).
- View `admin.dashboard.guru` — tidak disentuh (guru bukan aktor SPMB/Keuangan per matriks hak akses PRD).

## 3. Tata Letak (dari sesi brainstorming visual)

**Dashboard lembaga** (admin keuangan / kepala sekolah) — "Tren + Donut":
- Baris kartu angka di atas (SPMB: Total Pendaftar/Menunggu/Diterima/Ditolak; Keuangan: Rp Terkumpul/Rp Belum Lunas/Pembayaran Menunggu Verifikasi).
- Grafik tren pendaftaran harian (garis, 30 hari terakhir) berdampingan dengan donut komposisi status tagihan.

**Dashboard yayasan** — "Kartu + Grafik Batang + Tabel":
- Kartu total konsolidasi (Total Pendaftar / Total Diterima / Total Rp Terkumpul, SUM lintas lembaga).
- Grafik batang: pendaftar per lembaga, berdampingan.
- Tabel satu baris per lembaga (Pendaftar/Diterima/Terkumpul), setiap baris bisa diklik.

## 4. Data & Metrik

Semua angka & grafik pada dashboard lembaga dibatasi **tahun ajaran aktif** milik lembaga tsb (`TahunAjaran::where('lembaga_id', ...)->where('status_aktif', true)->first()`), KECUALI grafik tren harian yang memakai jendela bergulir **30 hari terakhir** (independen dari tahun ajaran, supaya tetap terbaca meski tahun ajaran berjalan berbulan-bulan).

**Section SPMB:**
- Kartu: `COUNT(Pendaftaran)` per status (`menunggu_verifikasi`/`diterima`/`ditolak`) + total, untuk tahun ajaran aktif.
- Grafik tren: `COUNT(Pendaftaran)` per hari (`DATE(submitted_at)`), 30 hari terakhir, harus mencakup semua 30 titik berurutan termasuk hari dengan 0 pendaftaran (bukan cuma hari yang ada datanya).

**Section Keuangan:**
- Kartu **Rp Terkumpul**: `SUM(tagihan.total_tagihan)` where `status = 'lunas'`.
- Kartu **Rp Belum Lunas**: `SUM(tagihan.total_tagihan)` where `status IN ('belum_bayar', 'dicicil')` — tagihan yang baru sebagian lunas (cicilan) tetap dihitung penuh sebagai "belum lunas" sampai statusnya benar-benar `lunas`, konsisten dengan makna `tagihan.status` yang sudah ada (tidak menghitung ulang sisa cicilan per termin untuk kebutuhan dashboard).
- Kartu **Pembayaran Menunggu Verifikasi**: `COUNT(Pembayaran)` where `status = 'menunggu_verifikasi'`, scoped ke lembaga (dua jalur kepemilikan: langsung via `tagihan_id`, atau via `cicilan_id → skema_cicilan → tagihan`, sama seperti query di `PembayaranController::data()`) — kartu ini adalah link langsung ke halaman "Verifikasi Pembayaran".
- Donut: `COUNT(Tagihan)` per status (belum_bayar/dicicil/lunas) — menggabungkan kedua kategori (`pendaftaran` dan `daftar_ulang`) dalam satu hitungan, bukan dipisah per kategori (donut ini menunjukkan komposisi status secara umum, bukan perbandingan kategori).

**Dashboard yayasan:**
- Kartu total: SUM dari metrik SPMB+Keuangan di atas, lintas semua lembaga di bawah yayasan yang login (masing-masing dihitung dari tahun-ajaran-aktifnya sendiri). Agregasi pakai satu query `groupBy('lembaga_id')`, bukan loop per lembaga yang query berkali-kali.
- Grafik batang & tabel: satu baris per lembaga dengan metrik yang sama.

**Kasus kosong:** lembaga tanpa tahun ajaran aktif → semua angka 0, grafik tetap render kosong (bukan error). User yayasan tanpa lembaga sama sekali → dashboard yayasan tetap tampil dengan total 0 dan tabel kosong.

## 5. Gating per Permission & Drill-Down

Section SPMB dan Keuangan pada dashboard lembaga masing-masing independen, mengikuti permission yang SUDAH ADA (tidak ada permission baru):
- SPMB muncul kalau `auth()->user()->can('spmb-pendaftaran.view')`.
- Keuangan muncul kalau `auth()->user()->can('tagihan.view')`.

Ini artinya: admin_administrasi (SPMB-only) hanya lihat section SPMB; admin_keuangan & kepala_sekolah (punya kedua permission) lihat keduanya — sesuai matriks hak akses PRD bagian 7 ("Laporan keuangan lembaga": Admin Keuangan ✅, Kepala Sekolah 👁, Admin Administrasi tidak termasuk).

**Drill-down dashboard yayasan → lembaga:** memakai mekanisme `?switch_lembaga={id}` yang SUDAH ADA (ditangani `ResolveTenant` middleware, dipakai topbar switcher) — tidak perlu endpoint baru. Perubahan yang diperlukan: `DashboardController::index()` saat ini SELALU menampilkan view yayasan untuk user yayasan-scoped, tidak peduli `session('active_lembaga_id')`. Diubah jadi: kalau `active_lembaga_id` sudah di-set → tampilkan dashboard **lembaga** (punya lembaga itu); kalau belum (mode "semua lembaga") → tampilkan dashboard **yayasan** konsolidasi seperti sekarang. Ini menyamakan `/dashboard` dengan pola `active_lembaga_id` yang sudah dipakai konsisten di seluruh controller admin lain (`TagihanController`, `JenisTagihanController`, `PendaftaranAdminController`, dst). Baris tabel yayasan jadi `<a href="{{ route('dashboard') }}?switch_lembaga={{ $lembaga->id }}">`.

## 6. Rencana Pengujian

- Section SPMB/Keuangan pada dashboard lembaga muncul/tidak sesuai permission (admin_administrasi hanya SPMB; admin_keuangan & kepala_sekolah keduanya; dashboard guru tidak berubah — regresi).
- Angka-angka dihitung benar dari data uji buatan (bukan cuma "halaman render tanpa error") — termasuk kartu Keuangan yang benar membedakan lunas/belum-lunas, dan Pembayaran Menunggu Verifikasi yang benar menghitung dua jalur kepemilikan (tagihan-langsung & via-cicilan).
- Isolasi tenant: data lembaga lain tidak pernah masuk hitungan lembaga aktif user.
- Dashboard yayasan: SUM lintas lembaga benar, tabel per lembaga benar.
- Drill-down: klik baris tabel yayasan → `active_lembaga_id` ter-set dan dashboard lembaga yang benar (bukan yayasan) yang muncul pada request berikutnya; tanpa `active_lembaga_id`, tetap dashboard yayasan yang tampil.
- Tren 30 hari: array harus berisi 30 titik berurutan termasuk hari dengan 0 pendaftaran.
- Kasus kosong (lembaga tanpa tahun ajaran aktif) tidak error.

## 7. Non-Tujuan / Catatan

- M9 (Notifikasi) adalah sub-project berikutnya, dibangun terpisah dari M8 ini.
- Filter periode interaktif dan export PDF/Excel bisa jadi permintaan terpisah nanti kalau dibutuhkan — tidak diantisipasi sekarang (YAGNI).
