# Spec — Redesain Visual Dashboard Orang Tua (Ref-Driven Modern UI)

- **Tanggal Spec**: 2026-08-25
- **Tujuan**: Mengubah tampilan dashboard Orang Tua dari layout kaku/linear menjadi layout 2-kolom modern, vibrant, dan fluid sesuai acuan gambar desain (Hero gradient banner, ring progress indicators, card avatar berwarna, dan sidebar jadwal/kalender kegiatan).
- **Branch**: `rbac-v2`

---

## 1. Requirement & Inspirasi Desain

Dari acuan gambar desain yang diberikan user:
1. **Hero Banner Vibrant Gradient**:
   - Kartu ucapan selamat datang bergelombang/gradient biru-indigo yang menyolok (`from-blue-600 via-indigo-600 to-sky-500`).
   - Teks ucapan hangat ("Halo, [Nama] 👋") dengan statistik ringkas di dalamnya (misal: "Seluruh anak terdaftar aktif").
2. **Layout 2-Kolom (Asimetris 8:4 / Main Content + Right Sidebar)**:
   - **Kolom Utama (Kiri - 8/12)**:
     - Hero Banner teratas.
     - Ringkasan Finansial & Akademik dalam bentuk kartu bergelombang / stat tiles berwarna pastel dengan badge icon ring (Presensi, Status Tagihan, Jumlah Anak).
     - Daftar Anak & Kasus Pendampingan yang disusun dengan avatar inisial berwarna (Oranye, Pink, Hijau, Biru) dan tag status badge.
   - **Sidebar Kanan (Kanan - 4/12)**:
     - Widget Kalender / Tanggal Mini (Header bulan & baris tanggal dengan lingkaran highlight tanggal aktif).
     - Timeline "Jadwal Pelajaran Anak Hari Ini" dengan badge jam/urutan berwarna pastel (Biru, Pink, Hijau, Oranye) lengkap dengan detail mapel, waktu, dan penanda chevron.

---

## 2. Reusabilitas & Komponen Pintera

- **`<x-hero-banner>`**: Dipercantik dengan style gradient royal blue / indigo modern.
- **`<x-stat-tile>`**: Dipertahankan dan dipadukan dengan background soft tint/pastel untuk KPI tiles.
- **`<x-panel>`**: Dipertahankan sebagai container card utama dengan rounded corners (`rounded-3xl` / `rounded-2xl`) dan border lembut.
- **`<x-badge>`**: Menggunakan tone warna Spatie/Pintera existing (`green`, `amber`, `rose`, `brass`).

---

## 3. Data Scope (Orang Tua Dashboard)

- `anakList`: Daftar anak yang terhubung ke orang tua (dengan kelas & NISN).
- `tagihanBelumLunas`: Nominal total tagihan belum lunas seluruh anak.
- `presensiAnakHariIni`: Status kehadiran anak hari ini.
- `jadwalAnakHariIni`: List jadwal pelajaran hari ini untuk seluruh anak.
- `kasusList` & `kasusStats`: Daftar kasus pendampingan siswa.

---

## 4. Acceptance Criteria

1. Tampilan Dashboard Orang Tua menggunakan layout 2-kolom (8/12 main content, 4/12 right sidebar di desktop).
2. Hero banner tampil dengan gaya visual modern (gradient background, typography kontras, visual greeting).
3. Sidebar kanan memuat widget kalender mini hari/bulan dan card timeline jadwal pelajaran anak dengan badge lingkaran nomor/waktu berwarna pastel.
4. Daftar anak dan kasus pendampingan memuat avatar inisial nama berwarna dinamis.
5. Seluruh test existing di `DashboardTest.php` dan `DashboardControllerTest.php` tetap **PASSING 100%**.
