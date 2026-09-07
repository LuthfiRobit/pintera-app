# Kickoff: Jenis Karyawan & Jabatan Tambahan Master — Per-Yayasan

**Base commit**: `1c488646` (`docs(sdm): implementation plan jenis karyawan & jabatan tambahan per-yayasan`)
**Branch**: `rbac-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Item backlog 🟡 dari audit sidebar sebelumnya (`.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md`, bagian "Rekap"): `jenis_karyawan_master` dan `jabatan_tambahan_master` TIDAK punya kolom tenant sama sekali — datanya dibagi lintas SEMUA yayasan di seluruh sistem (bukan cuma lintas lembaga dalam 1 yayasan, kelas masalah yang lebih besar).

Melalui diskusi dengan user, sempat dipertimbangkan pola "katalog nasional + custom per-yayasan" (`yayasan_id` nullable), TAPI **diputuskan disederhanakan jadi per-yayasan PENUH** — `yayasan_id` NOT NULL, TIDAK ADA baris global/nasional sama sekali. Setiap yayasan kelola daftarnya sendiri sepenuhnya. Konsekuensinya (starter catalog untuk yayasan baru lewat seeder saat onboarding) SENGAJA di luar scope kerja ini — user eksplisit bilang "jangan pikirkan yayasan lain dulu, aman saja".

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md` — spec lengkap: keputusan bisnis, verifikasi data sebelum migrasi (hasil tinker empiris terhadap DB nyata), arsitektur per bagian.
2. `.agents/plans/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md` — 3 task, kode lengkap tiap step, sudah self-review. Task 1 dan 2 STRUKTURNYA IDENTIK (2 vertical slice per tabel) — tapi Task 2 py 1 perbedaan besar: `JabatanTambahanMasterCrudTest.php` fixture-nya SAMA SEKALI TIDAK COMPATIBLE dengan scope baru dan harus ditulis ULANG TOTAL (bukan cuma ditambal 2 baris seperti Task 1) — baca "Step 9: Rewrite fixture test menyeluruh" di Task 2 dengan sangat teliti.
3. `.agents/specs/2026-09-07-orang-tua-siswa-person-tautan.md` (khususnya bagian Konteks Arsitektur) dan `app/Models/Scopes/YayasanScope.php` langsung — WAJIB paham cara kerja `YayasanScope` (dipakai `Person`, sekarang dipakai ulang di 2 model SDM ini) sebelum menyentuh model manapun di plan ini. Resolusi `yayasan_id` aktor lewat fallback chain (`$actingUser->yayasan_id ?? $actingUser->lembaga?->yayasan_id ?? ...`) dan fail-closed (`whereRaw('1 = 0')` kalau tidak terselesaikan) — PENTING dipahami supaya tidak salah asumsi soal kenapa suatu skenario test gagal/lolos.
4. `.ai/rules/index.md` lalu setiap file rule yang glob-nya cocok (`actions.md`, `domains-models.md`, `migrations.md`, `controllers.md`, `tests.md`).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Tanpa baris nasional/global** — `yayasan_id` NOT NULL di kedua tabel, tanpa pengecualian. Kalau menemukan alasan kuat untuk mengubah ini (mis. backfill data produksi riil ternyata jauh lebih rumit dari asumsi), STOP dan laporkan ke user dulu — jangan diam-diam kembali ke pola nullable.
- **Backfill WAJIB defensif** (assign ke pemakai existing kalau 1 pemakai, clone+repoint kalau >1 pemakai) — JANGAN disederhanakan jadi "assign semua ke yayasan_id=1" meski itu SATU-SATUNYA kasus yang benar-benar terjadi di data sekarang (dikonfirmasi via tinker, dicatat di spec). Logic percabangan di migrasi tetap harus ditulis lengkap.
- **`yayasan_id` dihitung di Action/Controller, BUKAN masuk DTO** — user TIDAK memilihnya lewat form, dan payload request TIDAK BOLEH bisa override-nya (lihat test baru "assigns yayasan_id from the acting manager automatically, ignoring any yayasan_id in the request payload" di Task 1 Step 10 — WAJIB lulus).
- **Guard delete di-scope ke yayasan PEMILIK ROW, bukan aktor yang login** — guru/karyawan di yayasan LAIN tidak boleh memblokir delete. Ini beda arah dari "scope biasa" (yang biasanya berdasarkan aktor) — jangan ditukar tanpa sadar.
- **`index()` di kedua controller SENGAJA TIDAK diseragamkan cakupannya** — `JenisKaryawanMasterController::index()` menghitung `withCount('karyawan')` sesuai scope aktor SAAT INI (lembaga vs yayasan), sedangkan `JabatanTambahanMasterController::index()` menghitung `withCount(['guru' => withoutGlobalScopes()])` SELALU lintas-lembaga-dalam-yayasan (perilaku yang SUDAH ADA sebelum fix ini, tidak diubah). Ini BUKAN inkonsistensi yang perlu diperbaiki — baca penjelasan lengkap di Task 1 Step 8 dan Task 2 Step 8 sebelum menganggap ini bug.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **MySQL deadlock risk**: SELALU cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 3.
- **DB dev sesungguhnya bernama `pintera_sdm_app`**, BUKAN `pintera_app`.
- **Data existing di DB dev**: 4 yayasan total. `jenis_karyawan_master` 4 baris (3 dipakai, semua di yayasan_id=1). `jabatan_tambahan_master` 16 baris (4 dipakai, semua di yayasan_id=1). Setelah migrasi Task 1/2 jalan, verifikasi hasil backfill masuk akal (`WHERE yayasan_id != 1` seharusnya kosong ATAU cuma baris yang memang terverifikasi dipakai yayasan lain — TIDAK ADA yang begitu di data sekarang, jadi HARUSNYA semua baris jadi `yayasan_id=1` persis).
- **`Karyawan` model punya `yayasan_id` LANGSUNG di kolom/fillable** (pola "pool"), TAPI **`Guru` model TIDAK** (cuma `lembaga_id`) — ini KENAPA logic backfill & guard delete kedua tabel BEDA BENTUK query (Task 1 langsung `where('yayasan_id', ...)`, Task 2 harus join lewat `Lembaga::where('yayasan_id', ...)->pluck('id')` dulu). Jangan disamakan strukturnya, itu memang beda karena model dasarnya beda.
- **`KaryawanFactory` (`database/factories/KaryawanFactory.php:54`) punya default `'yayasan_id' => Yayasan::factory()`** — SELALU membuat Yayasan BARU secara acak kalau tidak di-override eksplisit. WAJIB selalu pass `'yayasan_id' => $manager->yayasan_id`) eksplisit di SETIAP pemakaian `Karyawan::factory()->create([...])` di test manapun yang terkait plan ini — kalau lupa, test delete-guard tidak akan pernah mendeteksi "masih dipakai" (false negative diam-diam, bukan error).

## 5. Instruksi Stop-and-Report

- **Kalau `php artisan test` (full suite Task 3) menunjukkan kegagalan DI LUAR 4 kegagalan pre-existing yang sudah dikenal** (seeder demo PPDB/presensi) — JANGAN asumsikan "pasti tidak terkait". Grep dulu `JenisKaryawanMaster::factory\(\)|JabatanTambahanMaster::create\(|JabatanTambahanMaster::factory\(\)` di SELURUH `tests/` (bukan cuma 2 file yang sudah diperbaiki plan ini) — kemungkinan ada test LAIN (mis. test seeder, test Guru/Karyawan modul lain) yang juga bergantung pada baris `JenisKaryawanMaster`/`JabatanTambahanMaster` tanpa `yayasan_id` eksplisit dan sekarang gagal karena `abort_if`/unique-scope baru. Kalau ketemu, perbaiki fixture-nya (pola sama seperti Task 1/2), CATAT di laporan task sebagai temuan tambahan.
- **Kalau backfill migrasi menghasilkan hasil yang TIDAK sesuai ekspektasi** (mis. ada baris yang ternyata `yayasan_id`-nya BUKAN 1 padahal seharusnya, atau ada error saat migrasi jalan) — STOP, jangan force-fix dengan `UPDATE` manual di luar migrasi. Investigasi dulu kenapa logic backfill tidak sesuai asumsi data yang sudah diverifikasi di spec, laporkan sebagai BLOCKED kalau tidak bisa diselesaikan sendiri.
- **Kalau ragu soal cakupan** (mis. apakah suatu fixture rewrite tambahan termasuk "di luar 2 file yang disebut plan" boleh dikerjakan) — kerjakan kalau memang langsung diperlukan supaya full suite hijau (itu bagian dari "regresi wajib 0" di Global Constraints), tapi CATAT di laporan sebagai deviasi dari brief, jangan diam-diam.

## 6. Catatan Serah Terima

- Plan ini py 1 keputusan desain yang SENGAJA didokumentasikan sebagai "boleh beda, bukan bug" (lihat poin `index()` di section 3) — supaya reviewer/implementer berikutnya TIDAK mencoba "memperbaiki" itu jadi seragam tanpa sadar itu keputusan sengaja, bukan kelalaian.
- User secara eksplisit meminta kickoff kali ini (bukan eksekusi inline) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 3 selesai, JANGAN merge branch `rbac-v2` ke `main` — itu keputusan terpisah milik user (branch ini membawa banyak hasil kerja lain yang belum di-merge: 7-task scope yayasan/lembaga, perbaikan tautan orang tua-siswa-person, perbaikan UI index siswa).

## 7. Mulai dari mana

Mulai dari **Task 1** (`JenisKaryawanMaster`) di `.agents/plans/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md`, kerjakan berurutan Task 1 → 3. Task 1 selesai duluan supaya Task 2 punya pola yang sudah terbukti jalan (migrasi+backfill+scope+action+controller) sebagai rujukan konkret, sebelum menghadapi bagian tersulit Task 2 (rewrite fixture test menyeluruh di Step 9).
