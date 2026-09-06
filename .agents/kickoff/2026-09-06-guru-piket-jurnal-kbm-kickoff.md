# Kickoff: Guru Piket — Pengisian Jurnal KBM Real-Time Pengganti (Fase 1)

**Base commit**: `f782cb36` (`docs(akademik): implementation plan guru piket -- pengisian jurnal kbm real-time pengganti`)
**Branch**: `akademik-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

"Proyek C" dari rangkaian diskusi presensi/jurnal KBM sesi ini (setelah Proyek A — batas edit presensi, SELESAI; Proyek B — akses Waka Kurikulum lintas-guru, backlog terpisah, TIDAK dikerjakan). Masalah: `JurnalKbmController` digerbangi `authorizeMilikGuru()` — HANYA guru pemilik sesi yang bisa isi presensi/jurnal. Kalau guru itu sakit dadakan/izin mendadak, tidak ada jalan resmi bagi orang lain mengisi HARI ITU JUGA.

Solusi: konsep "Guru Piket" (jadwal bergilir per hari, lazim di sekolah Indonesia — dokumen resmi "Program Piket Mingguan" yang biasanya ditandatangani Kepsek tiap semester). Fase 1 (kickoff ini) membangun mekanisme akses & data dasarnya. Fase 2 (LaporanPiket, verifikasi Kepsek, cetak dokumen) SENGAJA ditunda, backlog terpisah.

**Ini spec/plan PALING BESAR dari rangkaian fitur presensi/jurnal sesi ini** — 11 task (dibanding 7-8 task Proyek A & Kartu Digital Siswa sebelumnya). Cakupannya genuinely lebih besar: 2 tabel baru, 1 kolom baru, 3 Action, 1 Service, 2 titik guard yang wajib konsisten, 1 halaman admin CRUD baru, 1 permission baru.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md` — keputusan desain lengkap (11 poin di §2), arsitektur detail (§3.1-3.4 dengan kode konkret `PiketAccessChecker` dan `RegenerateJadwalPiketHarianAction`), 13 skenario test (§4).
2. `.agents/plans/2026-09-06-guru-piket-jurnal-kbm.md` — 11 task, kode lengkap tiap task, sudah self-review (termasuk 1 koreksi yang ditemukan & diperbaiki SAAT plan ditulis — baca bagian "Koreksi yang ditemukan" di Self-Review plan).
3. `.agents/specs/2026-09-06-batas-edit-presensi-jurnal.md` dan `.agents/specs/2026-09-06-kartu-digital-siswa-presensi-scan.md` — konteks fitur-fitur yang BARU SAJA ditambahkan ke file yang sama (`JurnalKbmController.php`, `UpdateJurnalPresensiRequest.php`, `RecordJurnalDanPresensiAction.php`, `show.blade.php`, `index.blade.php`) — WAJIB dipahami supaya tidak merusaknya.
4. `.ai/rules/index.md` lalu setiap file rule yang glob-nya cocok path yang akan disentuh (`actions.md`, `controllers.md`, `data-transfer-objects.md`, `domains-models.md`, `migrations.md`, `routes.md`, `services.md`, `views.md`, `tests.md`).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Wewenang piket HANYA hari ini** (`tanggal` sesi = hari ini) — BUKAN model N-hari-mundur seperti Proyek A. Ini prinsip inti, jangan dilonggarkan.
- **2 lapis data**: `JadwalPiketMingguan` (pola berulang) generate `PiketHarian` (per-tanggal). Override manual pada `PiketHarian` TIDAK PERNAH tertimpa otomatis.
- **3 aturan baris "beku"** (TIDAK PERNAH disentuh regenerate): `sumber = 'override_manual'`, ATAU `tanggal < hari ini`, ATAU sudah ada `SesiPembelajaran.diisi_oleh_guru_id` yang cocok. Ketiganya WAJIB ada, bukan sebagian.
- **`RegenerateJadwalPiketHarianAction`: 3 langkah bernomor WAJIB urutan itu** (ambil kandidat → filter yang sudah dipakai → baru delete+generate ulang), dalam 1 `DB::transaction()`. JANGAN delete duluan baru cek belakangan.
- **`GenerateJadwalPiketHarianAction` WAJIB idempotent** (`firstOrCreate`) — jaring pengaman kedua.
- **Generate mulai dari HARI INI, bukan `semester->tanggal_mulai`** — ini koreksi yang ditemukan saat plan ditulis (Task 2), WAJIB diikuti persis kodenya di plan, jangan revert ke versi naif yang generate dari awal semester.
- **Kriteria pemilihan Generate vs Regenerate berbasis KONDISI DATA** (`PiketHarian::exists()` untuk lembaga+semester), BUKAN jenis form action (tambah/ubah/hapus). `store()` cek kondisi dulu; `update()`/`destroy()` SELALU Regenerate (baris yang diedit/dihapus pasti sudah ada data sebelumnya).
- **`PiketAccessChecker::bisaAkses()` WAJIB cek `$sesi->lembaga_id`** (bukan lembaga_id guru yang login) — cross-tenant safety, project ini punya riwayat bug lintas-tenant berulang kali.
- **1 helper (`PiketAccessChecker`) dipanggil dari `authorizeMilikGuru()` DAN `UpdateJurnalPresensiRequest::authorize()`** — TIDAK BOLEH logic ditulis ulang beda di 2 tempat, ini jebakan yang mudah kelewat.
- **`diisi_oleh_guru_id` cuma terisi kalau guru yang submit BEDA dari pemilik asli** — guru pemilik sendiri isi sesinya sendiri → tetap `null`.
- **Permission `piket.kelola` HANYA untuk atur jadwal**, BUKAN untuk mengisi presensi (itu tetap `presensi.isi` yang sudah ada — TIDAK ADA permission baru untuk aksi mengisi).
- **Fase 2 TIDAK dikerjakan** — LaporanPiket, verifikasi Kepsek, cetak dokumen, mekanisme otomatis pembersihan `PiketHarian` saat `KalenderAkademik` berubah setelah batch generate awal. Semua ini backlog terpisah.
- **JANGAN merusak fitur A2 (notifikasi WA), A3 (Scan Presensi Kartu Digital Siswa), atau Proyek A (batas edit presensi)** yang sudah ada di file-file yang sama.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **MySQL deadlock risk**: cek dulu (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` di PowerShell) sebelum full suite di Task 11.
- **Migrations di project ini sudah di-squash** — base schema di `database/schema/mysql-schema.sql`, migrasi baru cukup ditambah normal.
- **`KalenderAkademikResolver`** (`app/Domains/Akademik/Services/KalenderAkademikResolver.php`) SUDAH ADA dan SUDAH TERBUKTI dipakai `SesiPembelajaranGenerator` untuk kebutuhan sama (cek hari libur) — Task 2 WAJIB reuse ini, JANGAN menulis ulang query `KalenderAkademik` dari nol.
- **`PolaJamController`** (`app/Http/Controllers/Admin/PolaJamController.php`) adalah referensi pola CRUD admin lembaga yang sudah ada — `ResolveLembagaScopeTrait`, `resolveActiveLembagaId()`. Task 9 & 10 WAJIB ikuti pola ini.
- **Akun demo untuk verifikasi**: cari akun dengan role `wakasek_kesiswaan` atau `operator_akademik` untuk uji halaman admin Task 9/10, dan akun guru untuk uji Task 5/6/7 — cek seeder demo dulu, password konvensi project ini `password` untuk semua akun demo.

## 5. Instruksi Stop-and-Report

- **Kalau menemukan `JurnalKbmController.php`, `UpdateJurnalPresensiRequest.php`, atau `RecordJurnalDanPresensiAction.php` BERBEDA dari yang diasumsikan plan** — file-file ini sudah berubah berkali-kali sesi-sesi sebelumnya, STOP, baca versi terkini, sesuaikan, catat di laporan task apa yang berbeda dan kenapa.
- **Kalau test regresi (Task 5 Step 6, Task 6 Step 6) gagal untuk kasus NON-PIKET** (guru biasa akses sesinya sendiri, guru lain tetap ditolak, notifikasi WA A2) — ini WAJIB tetap identik seperti sebelumnya. Kalau gagal, STOP dan laporkan BLOCKED, jangan lanjut dengan asumsi "nanti terperbaiki di task berikutnya".
- **Kalau ragu soal cakupan Fase 1 vs Fase 2** — tanyakan, jangan menebak sepihak. Terutama godaan untuk "sekalian aja bangun LaporanPiket karena sudah di sini" — TAHAN, itu di luar cakupan eksplisit.

## 6. Catatan Serah Terima

- Spec ini lahir dari brainstorming SANGAT panjang dan mendalam — banyak putaran koreksi soal race condition, idempotency, kriteria generate-vs-regenerate yang eksplisit berbasis kondisi data (bukan tebakan implementer). Semua keputusan sudah final dan dipertimbangkan matang — JANGAN didesain ulang tanpa alasan kuat, itu akan mengulang kerja yang sudah selesai.
- User secara eksplisit meminta kickoff kali ini (bukan eksekusi inline) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 11 selesai, JANGAN merge branch `akademik-v2` ke `main` — itu keputusan terpisah milik user (branch ini membawa A2, A3, Proyek A, dan Proyek C Fase 1 sekaligus, semua belum di-merge).

## 7. Mulai dari mana

Mulai dari **Task 1** (migrasi & model dasar) di `.agents/plans/2026-09-06-guru-piket-jurnal-kbm.md`, kerjakan berurutan Task 1 → 11. Task 5 (wiring guard) adalah titik paling kritis untuk keamanan lintas-tenant — jangan diburu-buru, verifikasi tenant-safety-nya benar-benar teruji sebelum lanjut ke task berikutnya.
