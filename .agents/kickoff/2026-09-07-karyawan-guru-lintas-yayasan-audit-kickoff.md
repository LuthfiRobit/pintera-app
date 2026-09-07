# Kickoff: Perbaikan Kebocoran Lintas-Yayasan Modul Karyawan & Guru

**Base commit**: `890e5c10` (`docs(sdm): implementation plan perbaikan lintas-yayasan Karyawan & Guru`)
**Branch**: `rbac-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menyeluruh modul Karyawan & Guru (2 subagent paralel + investigasi lanjutan + verifikasi empiris tinker berulang kali, transaksi rollback) menemukan 6 bug, 2 di antaranya kebocoran data **LINTAS YAYASAN** nyata (bukan cuma lintas lembaga) — dikonfirmasi lewat reproduksi langsung, bukan cuma pembacaan kode:

1. **4 titik validasi `exists:` Laravel** yang tidak lewat `YayasanScope` — admin bisa menautkan `jenis_karyawan_id`/`jabatan_tambahan_master_id` milik yayasan LAIN ke data mereka sendiri (IDOR).
2. **2 resolver SDM** (`AttendancePolicyResolver`, `KuotaCutiResolver`) yang bisa menerapkan kebijakan presensi/kuota cuti milik yayasan lain ke karyawan pool.
3. Karyawan pool terlewat dari Alpa otomatis & 2 dropdown pemilih karyawan.
4. Exception tak tertangkap di `GuruController::store()`.
5. Index Karyawan tidak bisa dicari by NIK.

**Catatan penting soal proses audit ini sendiri**: draf pertama spec sempat py 3 kesalahan (ditemukan lewat re-review baris-demi-baris terpisah, sebelum plan ini ditulis) — termasuk subagent audit awal yang SALAH menyimpulkan `KuotaCutiResolver` "sudah benar" padahal py bug identik di tier 1/2-nya. **Pelajaran untuk implementer**: JANGAN percaya "kelihatannya sudah benar" tanpa membaca SELURUH method & memverifikasi empiris kalau memungkinkan — kode di plan ini SUDAH melalui proses itu, tapi tetap verifikasi ulang asumsi kalau kode di lapangan ternyata beda dari yang dikutip.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md` — spec lengkap (versi SUDAH DIKOREKSI, commit `9e25b9c0` — baca versi TERBARU, bukan draf pertama).
2. `.agents/plans/2026-09-07-karyawan-guru-lintas-yayasan-audit.md` — 6 task, kode lengkap tiap step, sudah self-review.
3. `.agents/specs/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md` — konteks `YayasanScope` yang baru dipasang ke `JenisKaryawanMaster`/`JabatanTambahanMaster` (SELESAI di branch yang sama sebelumnya) — Kelompok A spec ini adalah dampak LANGSUNG dari perubahan itu (validasi `exists:` yang dulu aman/tidak relevan, sekarang jadi celah karena tabelnya baru saja jadi per-yayasan).
4. `.ai/rules/index.md` lalu setiap file rule yang glob-nya cocok (`controllers.md`, `services.md`, `migrations.md`, `tests.md`).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Task 3 (Kelompok C) HANYA aman dikerjakan SETELAH Task 2 (Kelompok B) selesai & hijau total** — `resolveLibur()` untuk karyawan pool butuh fix B.1 dulu, kalau tidak akan `TypeError` fatal (`KalenderKerjaSdmResolver::resolve()` mensyaratkan `Lembaga` non-nullable). JANGAN mengerjakan Task 3 lebih dulu meski secara sepintas terlihat independen.
- **Karyawan pool default SELALU dianggap hari kerja** kecuali ada entri `KalenderKerjaSdm` eksplisit — keputusan bisnis dikonfirmasi user, BUKAN diasumsikan sendiri. `Yayasan` model TIDAK punya kolom `hari_libur_mingguan_sdm` (SENGAJA tidak ditambah, di luar scope).
- **`attendance_events.lembaga_id` diubah NULLABLE lewat migrasi** — keputusan bisnis dikonfirmasi user setelah 2 opsi lain dipertimbangkan (pakai lembaga representasi — ditolak karena menyesatkan; skip total — ditolak karena tidak menyelesaikan bug). JANGAN pilih pendekatan lain tanpa lapor balik.
- **Kelompok A**: `$yayasanId` scope HARUS sesuai konteks tiap titik — aktor yang login untuk `store()`/create, PEMILIK row untuk `update()`/operasi pada resource yang sudah ada. JANGAN disamakan semua pakai 1 pola tanpa cek konteks (lihat detail per task, terutama A.3 yang paling rumit — `$yayasanId` harus dihitung dari input MENTAH sebelum `validate()`, karena `resolveYayasanId()` versi resmi baru tersedia SETELAH validasi jalan).
- **Kelompok C.2 (`AttendanceConfigurationController.php` vs `AttendanceController.php`) TIDAK IDENTIK** — file pertama SUDAH punya `$yayasanId` terhitung, file kedua SAMA SEKALI TIDAK PUNYA helper yayasan (harus ditambah baru). JANGAN asumsikan kedua fix sama persis — plan sudah menulis terpisah untuk masing-masing, IKUTI PERSIS.
- **Task 4 (Kelompok D)**: test yang ditulis JUJUR diakui TIDAK sepenuhnya membuktikan skenario race condition konkuren (keterbatasan test HTTP biasa) — fix TETAP diterapkan sebagai defense-in-depth meski test-nya cuma membuktikan kasus sequential (yang sebenarnya sudah ditangani pre-check existing). JANGAN menganggap ini kekurangan yang perlu "disempurnakan" di luar scope — sudah didokumentasikan sengaja di plan.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 6.
- **DB dev sesungguhnya bernama `pintera_sdm_app`**, BUKAN `pintera_app`.
- **`Karyawan` punya `yayasan_id` LANGSUNG di kolom/fillable, `Guru` TIDAK** (cuma `lembaga_id`, SELALU terisi — tidak ada konsep pool guru). Ini kenapa resolver Kelompok B py cabang berbeda utk `$pegawai instanceof Guru` vs `Karyawan`.
- **File test target SEMUANYA SUDAH ADA di codebase** (dikonfirmasi lewat pencarian saat spec ditulis) — jangan buat file test baru kecuali disebutkan eksplisit di plan (Kelompok D punya keterbatasan pengujian yang sudah didiskusikan, bukan alasan bikin file terpisah).
- **`AttendancePolicyTenantIsolationTest.php` SUDAH ADA tapi TIDAK menguji skenario pool lintas-yayasan** (cuma menguji bypass TenantScope aktor login, hal berbeda) — tambahkan test BARU ke file ini di Task 2, JANGAN bikin file terpisah dgn nama serupa.
- **Task 5 butuh verifikasi manual di browser** (Step 5) — WAJIB benar-benar dilakukan dan dilaporkan jujur, bukan diasumsikan lolos karena kode "terlihat benar".

## 5. Instruksi Stop-and-Report

- **Kalau Task 2 Step 8 (grep ulang pola resolver) menemukan file LAIN** dengan pola bug yang sama di luar 2 file yang sudah diperbaiki — STOP, laporkan sebagai temuan tambahan, JANGAN diperbaiki diam-diam di luar scope task tanpa mencatatnya eksplisit di laporan.
- **Kalau kode aktual di lapangan BERBEDA dari yang dikutip plan** (kemungkinan berubah lagi sejak plan ditulis, terutama nomor baris) — STOP, baca versi terkini, sesuaikan, catat perbedaannya di laporan task.
- **Kalau full suite Task 6 menunjukkan kegagalan DI LUAR 4 yang sudah dikenal** (seeder demo PPDB/presensi) — investigasi dulu, jangan asumsikan tidak terkait, terutama untuk test yang menyentuh `AttendancePolicy`/`KuotaCutiConfig`/`Karyawan`/`Guru`/`attendance_events`.
- **Kalau migrasi Task 3 Step 1 gagal atau `down()` dites dan gagal** (karena sudah ada data `lembaga_id IS NULL`) — ini WAJAR/diketahui, JANGAN panik, cukup catat di laporan bahwa itu perilaku yang diharapkan, bukan bug.

## 6. Catatan Serah Terima

- Spec sudah melalui 1 putaran koreksi setelah re-review baris-demi-baris (3 kesalahan nyata ditemukan & diperbaiki sebelum plan ditulis) — kalau menemukan bagian plan yang ternyata TIDAK cocok dengan kode terkini, perbaiki dan catat kenapa, JANGAN diam-diam ikuti draft yang salah.
- User secara eksplisit meminta kickoff kali ini (bukan eksekusi inline) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 6 selesai, JANGAN merge branch `rbac-v2` ke `main` — keputusan terpisah milik user (branch ini membawa banyak hasil kerja lain yang belum di-merge dari sesi-sesi sebelumnya).

## 7. Mulai dari mana

Mulai dari **Task 1** (Kelompok A, 4 titik validasi `exists:`) di `.agents/plans/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`, kerjakan berurutan Task 1 → 6. **Task 2 → 3 urutannya WAJIB, tidak boleh dibalik atau dikerjakan paralel** (lihat Keputusan Kritis di atas). Task 4 dan 5 independen, boleh dikerjakan kapan saja setelah Task 1-3 selesai kalau ternyata ada alasan praktis untuk itu — tapi ikuti urutan plan (1→6) kecuali ada alasan kuat, dan catat kalau menyimpang.
