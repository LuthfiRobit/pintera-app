# Manual Book Modul Akademik — Design

**Tanggal:** 2026-07-30
**Status:** Approved

## Latar Belakang

Modul presensi & asesmen akademik (lihat memory `project_presensi_asesmen_akademik` dan
`project_komponen_penilaian_rekap_rapor`) sudah selesai dibangun dari data master sampai
rekap rapor. Belum ada dokumentasi operasional untuk pengguna akhir (admin akademik, guru,
kepala sekolah, admin yayasan) yang menjelaskan cara memakainya dari nol.

Sebagai persiapan, seluruh branding data seed dev sudah diganti dari "Al-Hikmah" menjadi
**"Yayasan Permata"** (commit `adec145`) supaya konsisten dipakai sebagai skenario contoh
di manual book ini dan reproducible lewat `migrate:fresh --seed`.

## Tujuan

Membuat manual book (panduan penggunaan) modul akademik, dari setup lembaga oleh admin
yayasan sampai asesmen & rapor oleh guru, dengan screenshot aktual dari aplikasi berjalan.

## Struktur & Lokasi File

```
docs/manual-book/akademik/
  00-setup-lembaga.md          (yayasan admin)
  01-data-master.md            (admin_akademik)
  02-penjadwalan.md            (admin_akademik)
  03-presensi-jurnal.md        (guru)
  04-asesmen-nilai.md          (guru)
  05-rekap-rapor.md            (admin_akademik / kepala_sekolah)
  06-kenaikan-kelas.md         (admin_akademik)
  lampiran-lintas-lembaga.md   (yayasan admin)
  images/
    00-01.png, 00-02.png, ...  (penomoran <nomor-bab>-<urutan>.png)
```

Satu file Markdown per bab. Gambar disimpan lokal di `images/` dan direferensikan dengan
path relatif — portable kalau nanti dikonversi ke Artifact/PDF.

## Template Isi Tiap Bab

Setiap file bab mengikuti pola yang sama:

1. **Untuk siapa** — role yang relevan (mengacu ke role riil: `yayasan_super_admin`,
   `admin_akademik`, `guru`, `kepala_sekolah`).
2. **Prasyarat** — data apa yang harus sudah ada dulu sebelum bab ini bisa dikerjakan
   (mis. Bab 2 Penjadwalan butuh Pola Jam & Kelas dari Bab 1).
3. **Langkah-langkah** — bernomor; langkah kunci (state UI berubah / hasil penting)
   disertai satu screenshot.
4. **Kesalahan umum** — jebakan yang sudah diketahui dari histori pengembangan proyek ini,
   ditulis ulang dalam bahasa pengguna, contoh:
   - Filter Kelas/Semester tanpa memilih Tahun Ajaran dulu bisa menampilkan data tahun
     yang salah (dulu bug berulang di Komponen Penilaian & Rekap Rapor).
   - Menghapus Pola Jam yang masih dipakai Kelas akan diblokir dengan pesan error, bukan
     dihapus paksa.
   - Menutup tahun ajaran (Kenaikan Kelas) tidak bisa dibatalkan — pastikan nilai &
     presensi sudah lengkap dulu.

## Skenario Data

Yayasan Permata punya 2 lembaga (SMP Permata, SMA Permata). Seluruh walkthrough & screenshot
memakai **SMP Permata** sebagai satu-satunya contoh — SMA Permata hanya disebut sepintas di
Bab 0 (ilustrasi multi-lembaga) dan Lampiran (fitur lintas-lembaga, mis. Kalender Akademik
Nasional).

Akun yang dipakai untuk login saat pengambilan screenshot (sudah ada dari
`EssentialUserSeeder`, password `password`, tidak perlu seeder baru):
- `superadmin@sistem.test` — role `yayasan_super_admin`, dipakai di Bab 0 & Lampiran
- `akademik@sistem.test` — role `admin_akademik`, dipakai di Bab 1, 2, 5, 6
- `guru@sistem.test` — role `guru`, dipakai di Bab 3 & 4

## Pipeline Screenshot

- Tambahkan `playwright` sebagai dev dependency (`npm install -D @playwright/test` atau
  `playwright`) + `npx playwright install chromium`. Ini dependency baru untuk proyek —
  dev environment ini belum punya browser automation tool sama sekali (dicatat di memory
  proyek sebelumnya).
- Satu script Node (`scripts/manual-book-screenshots.mjs`), **tidak** masuk ke build
  produksi maupun CI:
  - Login via form HTML asli (bukan session-inject langsung ke DB), supaya alur yang
    di-screenshot benar-benar mencerminkan pengalaman user nyata.
  - Viewport tetap 1440×900 untuk semua screenshot, supaya konsisten.
  - Daftar target (role, URL, nama file output, aksi UI sebelum screenshot seperti isi
    form/klik filter) didefinisikan sebagai array config di dalam script — reproducible,
    tinggal re-run kalau UI berubah.
  - Output disimpan langsung ke `docs/manual-book/akademik/images/`.
- Dijalankan manual per bab (`node scripts/manual-book-screenshots.mjs --bab=01`), bukan
  otomatis di test suite.

## Alur Pengerjaan

Bab-per-bab, bukan semua teks dulu baru semua screenshot di akhir:

1. Tulis teks bab (langkah-langkah + kesalahan umum) berdasarkan pemahaman kode/fitur yang
   sudah ada.
2. Tambahkan target screenshot bab tsb ke config script, jalankan, verifikasi hasil gambar.
3. Sisipkan gambar ke markdown bab tsb.
4. Lanjut ke bab berikutnya.

Urutan pengerjaan bab: 00 → 01 → 02 → 03 → 04 → 05 → 06 → lampiran (mengikuti urutan
operasional, sama seperti urutan tahap pembangunan modulnya).

## Di Luar Cakupan

- SMA Permata sebagai skenario walkthrough penuh (hanya disebut sepintas).
- Export manual book ke PDF cetak.
- Video atau GIF — hanya screenshot statis.
- Portal Siswa/Wali Murid, Diagnostik/Formatif, P5 — karena memang belum dibangun di modul
  aslinya (lihat memory `project_presensi_asesmen_akademik`).

## Testing / Verifikasi

Tidak ada automated test untuk dokumentasi ini. Verifikasi dilakukan manual:
- Tiap bab, setelah ditulis, dibaca ulang urut langkah demi langkah sambil membandingkan
  dengan UI aplikasi yang berjalan (bukan hanya dari kode) untuk memastikan tidak ada
  langkah yang hilang atau urutan yang salah.
- Script screenshot dijalankan sungguhan (bukan dry-run) — screenshot yang dihasilkan
  dicek visual sebelum disisipkan ke markdown.
