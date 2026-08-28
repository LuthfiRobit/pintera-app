# Kickoff: Fix PolaJam lembaga_id NULL & Catatan Wali Kelas Semester Mismatch

**Untuk**: Antigravity (execution agent)
**Branch**: `akademik-v2` (lanjutkan di branch ini, JANGAN buat branch baru)
**Spec**: `.agents/specs/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md`
**Plan**: `.agents/plans/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md`

---

## Peringatan Kritis — Baca Dulu Sebelum Mulai

1. **Baca `.agents/plans/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md` SELURUHNYA sebelum menulis kode apa pun.** Plan ini punya 2 task independen, masing-masing dengan kode lengkap di setiap step.
2. **Baca dulu `.ai/rules/index.md`**, lalu buka rule file yang cocok dengan glob untuk `app/Http/Controllers/**` dan `tests/**`.
3. **Temuan #3 dari audit yang sama (`ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction`, waka kurikulum yayasan tidak bisa approve rapor) SENGAJA TIDAK ada di plan ini** — user memisahkannya sebagai item terpisah. JANGAN mengerjakannya meski file-nya berdekatan secara tema.
4. **Verifikasi baseline sebelum edit** — Step 1 di masing-masing task meminta kamu membaca ulang file sumber dan membandingkan dengan baseline yang tertulis di plan. Kalau berbeda, STOP dan laporkan.

## Konteks Masalah (ringkas)

**Task 1**: `PolaJamController::store()` cuma mengisi `$lembagaId` kalau aktor level yayasan (`if ($request->user()->widestScopeLevel() === 'yayasan') { $lembagaId = session('active_lembaga_id'); ... }`). Untuk aktor lembaga-scoped biasa — mayoritas pengguna fitur ini — `$lembagaId` tidak pernah diisi, tetap `null`, dan tersimpan sebagai `lembaga_id = NULL` di database. Akibatnya aktor yang baru saja membuat PolaJam itu **tidak bisa melihatnya lagi** di halaman index sendiri (TenantScope memfilter `lembaga_id = milik aktor`, bukan `null`). Fix: mirror pola `GuruController::resolveLembagaId()` yang sudah benar.

**Task 2**: `Guru\RaporController` punya 5 method yang menerima `semester_id` dari request. Fix sebelumnya menambahkan cross-check "semester harus satu tahun ajaran dengan kelas" ke 4 method (`edit`, `generateNarasi`, `ajukan`, `cetak`) — tapi `update()` (method yang justru MENYIMPAN data `CatatanWaliKelas`) luput. Fix: tambah guard yang sama.

Kedua temuan ini murni bug integritas data dalam lembaga yang sama (Task 1) atau cross-periode dalam lembaga yang sama (Task 2) — BUKAN celah lintas-tenant baru seperti 5 dari 6 bug sebelumnya di sesi audit ini.

## Urutan Eksekusi

2 task independen. Kerjakan berurutan sesuai plan (Task 1 dulu, lalu Task 2). Masing-masing: baca baseline → tulis test gagal → jalankan & pastikan gagal → implementasi fix → jalankan & pastikan semua lolos → (Task 2 juga: pint) → commit.

## Titik Verifikasi Wajib

**Task 1:**
- Setelah Step 3 (sebelum fix): test `creates a pola jam` HARUS gagal di assertion `lembaga_id`, dan test `lets the lembaga-scoped manager see the pola jam they just created in the index` HARUS gagal (pola jam tidak muncul). Kalau sudah PASS di titik ini, STOP.
- Setelah Step 5 (setelah fix): `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --compact` HARUS hijau semua.

**Task 2:**
- Setelah Step 3 (sebelum fix): test reproduksi HARUS gagal (`assertNotFound()` gagal karena request justru sukses tersimpan). Kalau sudah PASS, STOP.
- Setelah Step 5 (setelah fix): `php artisan test tests/Feature/Guru/RaporControllerTest.php --compact` HARUS hijau semua — TERMASUK 2 test existing (`saves catatan wali kelas via update...`, `redirects to the next siswa...`) yang harus tetap PASS sebagai bukti tidak ada regresi jalur normal.

**TIDAK PERLU full suite** — cukup test scoped per task seperti di atas.

Jalankan `vendor/bin/pint --dirty --format agent` sebelum commit Task 2 (Step 6).

## Pelajaran Penting dari Sprint-Sprint Akademik Sebelumnya

- **Temuan Task 1 ironis: bug ini adalah kebalikan dari pola biasa** — kalau 6 bug sebelumnya di sesi ini semuanya soal data yang BOCOR ke lembaga lain, Task 1 justru soal data yang jadi ORPHAN (`lembaga_id = NULL`) dan tidak terlihat siapa pun kecuali platform-level. Jangan asumsikan semua bug di modul ini berbentuk "kebocoran" — kadang bentuknya "kehilangan akses ke data sendiri".
- **Temuan Task 2 adalah bukti bahwa fix yang sudah dikerjakan sebelumnya (commit `dd757eb2`) tidak lengkap** — hanya menyentuh 4 dari 5 method yang seharusnya dapat guard yang sama. Kalau menemukan pola serupa (guard yang diterapkan tidak konsisten ke SEMUA method yang seharusnya), laporkan sebagai temuan terpisah di handoff log, jangan diam-diam memperbaikinya di luar scope task yang sedang dikerjakan.
- **Selalu perkuat test lama kalau menemukan assertion yang terlalu lemah** — test `creates a pola jam` yang lama cuma cek `exists()` tanpa cek `lembaga_id`, itulah kenapa bug ini lolos dari radar test selama ini. Task 1 Step 2 sengaja memperkuat test itu, bukan cuma menambah test baru terpisah.

## Kalau Menemukan Sesuatu Tidak Sesuai Plan

- Kalau baseline kode di repo berbeda dari yang tertulis di plan Step 1 (masing-masing task), STOP dan laporkan detail perbedaannya di handoff log.
- Kalau ada test lain (di luar yang disebut di plan) yang tiba-tiba FAIL setelah fix diterapkan, JANGAN diam-diam mengubah assertion test itu. Laporkan sebagai temuan di handoff log.

## Definisi Selesai

- [ ] `PolaJamController.php::store()` mengisi `$lembagaId` sesuai plan Task 1 Step 4.
- [ ] Test `creates a pola jam` diperkuat + 3 test baru ditambahkan di `PolaJamCrudTest.php`, semua PASS, seluruh test existing lain di file itu tetap PASS.
- [ ] `Guru\RaporController.php::update()` mendapat 1 `abort_if` baru sesuai plan Task 2 Step 4.
- [ ] 1 test baru ditambahkan di `RaporControllerTest.php` dan PASS, seluruh test existing di file itu (termasuk 2 test yang disebut eksplisit) tetap PASS.
- [ ] `vendor/bin/pint --dirty --format agent` sudah dijalankan.
- [ ] 2 commit dibuat sesuai pesan di plan Task 1 Step 6 dan Task 2 Step 7.
- [ ] Handoff log ditulis ke `.agents/logs/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md` mengikuti format handoff log sebelumnya (lihat `.agents/logs/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md` sebagai contoh format): ringkasan apa yang dikerjakan (kedua task), keputusan penting yang diambil, hal yang perlu direview manusia.
