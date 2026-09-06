# Kickoff: Kartu Digital Siswa & Presensi via Scan (Opsi A3)

**Base commit**: `3f7a837c` (`docs(akademik): implementation plan kartu digital siswa & presensi via scan -- opsi A3`)
**Branch**: `akademik-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Roadmap platform sebelumnya menyebut "Notifikasi presensi & penjemputan (tap-in/tap-out)" sebagai 1 item. Setelah digali lewat diskusi panjang, item itu dipecah jadi 3 proyek terpisah:

- **Opsi A2** — Notifikasi Presensi Akademik berbasis Jurnal KBM. **SUDAH SELESAI**, sudah di-review penuh (Subagent-Driven Development, semua task Approved, final whole-branch review Ready-to-merge=Yes), commit `a9cdc631`..`403960d3` di branch ini juga. BELUM di-merge ke `main`.
- **Opsi A3** (plan ini) — evolusi A2: siswa scan kode QR pribadi sendiri untuk presensi, guru cuma validasi/isi manual siswa yang tidak sempat scan. SEKALIGUS membangun fondasi identitas "Kartu Digital Siswa" (kode QR permanen per siswa) yang dirancang generik untuk konsumen lain di masa depan (belum dibangun sekarang).
- **Opsi B** — presensi fisik tap-in/tap-out di gerbang sekolah, kartu RFID/QR di titik absen. Proyek terpisah total, TIDAK disentuh di sini.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-06-kartu-digital-siswa-presensi-scan.md` — keputusan desain lengkap (15 poin), alasan tiap keputusan, hasil investigasi kode existing yang jadi dasar desain.
2. `.agents/plans/2026-09-06-kartu-digital-siswa-presensi-scan.md` — 8 task, kode lengkap tiap task, sudah self-review.
3. `.agents/specs/2026-09-06-notifikasi-presensi-akademik.md` dan `.agents/plans/2026-09-06-notifikasi-presensi-akademik.md` — konteks A2 (sudah selesai), supaya paham kenapa `RecordJurnalDanPresensiAction`/`PresensiNotificationService` TIDAK BOLEH disentuh sama sekali oleh plan ini.
4. `.ai/rules/index.md` lalu SETIAP file rule yang glob-nya cocok dengan path yang akan disentuh (migrations, domains, domains-models, actions, controllers, routes, views, tests) — plan sudah ditulis mengikuti rule-rule ini, tapi verifikasi ulang kalau ada perubahan sejak plan ditulis.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **`RecordJurnalDanPresensiAction`, `PresensiNotificationService`, `PresensiPengecualianNotification` (semua dari A2) TIDAK BOLEH disentuh sama sekali.** Scan cuma mengisi form presensi manual yang sudah ada secara otomatis di browser (lewat event Alpine), bukan jalur simpan baru. Kalau menemukan alasan kuat untuk mengubah salah satu file itu, STOP dan laporkan ke user dulu — jangan jalan sendiri.
- **Kode QR permanen per siswa, bukan rotating.** "Generate ulang" mengganti nilai `kode` pada BARIS YANG SAMA (`UPDATE`), BUKAN membuat baris baru — ini WAJIB supaya tidak melanggar unique constraint `(siswa_id, tipe)` di tabel `kartu_siswa`. Ini koreksi desain yang ditemukan SAAT plan ditulis (desain awal di percakapan brainstorming sempat menyebut "nonaktifkan lama + buat baru", TERNYATA salah dan sudah diperbaiki di plan — ikuti kode di plan, bukan mengira-ngira dari histori percakapan manapun).
- **Kolom `tipe` di `kartu_siswa` adalah native DB ENUM** (`$table->enum('tipe', ['qr'])`), BUKAN string + PHP enum cast — sesuai `.ai/rules/migrations.md`. RFID HANYA disiapkan strukturnya (kolom bisa di-`ALTER` tambah nilai `'rfid'` nanti), TIDAK ADA implementasi RFID fungsional apa pun di plan ini.
- **Render QR pakai `SimpleSoftwareIO\QrCode\Facades\QrCode::size(...)->generate($kode)`** (SVG server-side, package Composer sudah terinstall) — BUKAN pola `<img src="https://api.qrserver.com/...">` yang dipakai `resources/views/sdm/qr-saya.blade.php` (pola itu sengaja TIDAK ditiru, mengirim data ke pihak ketiga). TIDAK ADA dependency npm baru — `html5-qrcode` (untuk scan) sudah terinstall & reusable apa adanya lewat `resources/js/qr-camera-scanner.js`.
- **Admin kelola kartu siswa lewat 1 TAB baru di halaman Data Siswa yang sudah ada** (`admin/siswa/{siswa}/edit`) — BUKAN halaman/menu sidebar baru, BUKAN generate massal. Kebutuhan operasional sengaja dibatasi (YAGNI) sampai ada kebutuhan nyata (mis. proyek kartu fisik).
- **Validasi scan 3 lapis WAJIB PERSIS urutan**: kartu ditemukan & aktif → siswa satu kelas dengan sesi → siswa satu lembaga dengan guru. Berhenti di lapis pertama yang gagal — JANGAN cek lapis berikutnya kalau lapis sebelumnya sudah gagal (mis. jangan sampai pesan error "beda lembaga" muncul padahal sebenarnya kartunya sendiri tidak valid).
- **TIDAK ADA toggle Lembaga untuk memilih mode A2 vs A3** — guru selalu bisa pakai scan DAN isi manual berdampingan dalam satu sesi yang sama.
- **Struktur Alpine di tabel presensi manual (`show.blade.php`) SUDAH per-baris (`<tr x-data="{status: ...}">`), BUKAN state global satu form** — Task 6 plan sudah menghitung ini (pola event bus `$dispatch`/`@presensi-scanned.window`), JANGAN direfactor jadi state form besar cuma untuk memudahkan scan — itu perubahan lebih invasif dari yang dibutuhkan dan berisiko regresi ke fitur keterangan izin/sakit yang sudah ada.
- **Hindari istilah "check-in"/"tap"/gerbang** di kode, nama class, pesan, komentar — tetap terpisah konsepnya dari Opsi B (fisik).
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Migrations di project ini sudah di-squash** — cuma 7 file migrasi tersisa (base schema ada di `database/schema/mysql-schema.sql`, di-load otomatis Laravel sebelum migrasi lain jalan). Kalau lihat cuma sedikit file migrasi, itu MEMANG BEGITU, bukan berarti riwayat migrasi hilang — jangan panik, jangan coba rekonstruksi ulang.
- **Dev server**: kalau perlu verifikasi manual browser (Task 6, WAJIB — lihat §5), jalankan `php artisan serve` atau `composer run dev`, dan `npm run build`/`npm run dev` untuk asset JS (Task 6 menambah JS inline di Blade + mungkin perlu registrasi Alpine component, cek Step 3 Task 6).
- **MySQL deadlock risk**: project ini pernah kena deadlock kalau 2 proses test jalan bersamaan — SELALU cek dulu (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` di PowerShell) sebelum jalankan full suite di Task 8.
- **Akun demo untuk verifikasi manual Task 6**: cari akun guru dan siswa yang SATU kelas & SATU lembaga yang sama (supaya scan valid), dan cari juga 1 siswa dari kelas/lembaga BEDA untuk uji skenario gagal — cek seeder demo yang ada (`DatabaseSeeder.php`, `LembagaPaudDemoSeeder.php`, atau seeder rebranding Yayasan Permata) untuk akun yang sudah tersedia, JANGAN menebak email/password.

## 5. Instruksi Stop-and-Report

- **Task 6 (tombol & modal scan) WAJIB diverifikasi manual di browser sungguhan** — kamera browser tidak bisa diuji penuh lewat Pest. Plan sudah menuliskan checklist verifikasi eksplisit di Step 5 Task 6. JANGAN tandai Task 6 selesai kalau verifikasi manual ini belum dilakukan — laporkan hasilnya (lolos/tidak, apa yang ditemukan) secara eksplisit di laporan task, jangan diam-diam diasumsikan lolos karena kode "terlihat benar".
- **Kalau ada langkah "PERIKSA LANGSUNG dulu" di plan** (ada beberapa — nama icon component, isi helper test `siapkanGuruDenganJadwalHariIni()`, cara registrasi Alpine component `qrCameraScanner` untuk portal guru, relasi `User<->Siswa` yang benar) — WAJIB benar-benar dibaca kodenya dulu sebelum menulis, JANGAN menebak berdasarkan asumsi dari plan/spec semata. Kalau ternyata beda dari asumsi plan, sesuaikan dan catat kenapa di laporan task.
- **Kalau menemukan bug/perilaku aneh saat verifikasi manual Task 6**: STOP task itu, laporkan detail (langkah reproduksi, ekspektasi vs kenyataan) sebagai status BLOCKED — jangan lanjut ke Task 7-8 seolah tidak terjadi apa-apa.
- **Kalau ragu soal cakupan** (mis. apakah suatu detail masuk Task ini atau di luar cakupan spec §5): tanyakan, jangan menebak sepihak.

## 6. Catatan Serah Terima

- Spec lahir dari brainstorming panjang dengan banyak keputusan yang sudah dipertimbangkan matang (termasuk penolakan eksplisit terhadap ide awal user untuk menggabungkan VA+QR+RFID jadi satu sistem — keputusan akhirnya tetap terpisah per domain, cuma titik resolusi `KartuSiswa::resolveSiswa()` yang generik). JANGAN direvisi ulang dari nol tanpa alasan kuat.
- User secara eksplisit meminta kickoff kali ini (berbeda dari plan A2 sebelumnya yang dieksekusi inline di sesi yang sama) — artinya TIDAK ADA sesi controller yang menunggu, kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 8 selesai, JANGAN merge branch `akademik-v2` ke `main` — itu keputusan terpisah yang akan diambil user sendiri (branch ini juga masih membawa hasil A2 dan audit-audit sebelumnya yang juga belum di-merge).

## 7. Mulai dari mana

Mulai dari **Task 1** (migrasi + model `KartuSiswa`) di `.agents/plans/2026-09-06-kartu-digital-siswa-presensi-scan.md`, kerjakan berurutan Task 1 → 8. Task 6 (verifikasi manual browser) adalah satu-satunya task yang butuh perhatian ekstra di luar siklus TDD biasa — jangan diburu-buru.
