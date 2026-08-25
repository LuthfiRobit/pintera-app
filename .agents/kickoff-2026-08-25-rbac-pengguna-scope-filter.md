# Kickoff Prompt — Halaman Pengguna: Filter Scope Chip & Visibilitas Lintas-Tenant Platform Admin

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-25-rbac-pengguna-scope-filter.md` — spec lengkap (latar belakang masalah, taksonomi chip §4, keputusan scope bypass §5, non-goals §3).
2. `.agents/plans/2026-08-25-rbac-pengguna-scope-filter.md` — plan implementasi (10 task, kode lengkap per task).

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `38029d5`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Kenapa sub-project ini ada**: RBAC v2 (sub-project sebelumnya, sudah SELESAI) menambahkan role `platform_super_admin` dengan `scope_level='platform'` di database, tapi tidak ada satu pun jalur kode yang mengenali nilai ini — `User::widestScopeLevel()` tidak punya cabang untuk `platform` (jatuh ke cabang paling restriktif), dan halaman "Pengguna" tidak punya cara memfilter/menampilkan user berdasarkan kategori scope. Sub-project ini memperbaiki fondasi scope-awareness itu SEKALIGUS menambah UI filter chip yang diminta user.
- **PALING KRITIS**: bypass `TenantScope` untuk `platform_super_admin` HANYA berlaku untuk model `User`. Model lain (`Karyawan`, `Kelas`, `Guru`, `Siswa`, dll) yang memakai trait `BelongsToTenant` TETAP terbatasi tenant seperti sekarang, termasuk untuk scope `platform`. Ini keputusan sadar (lihat spec §3 Non-Goals) — JANGAN generalisasi perubahan ke `TenantScope::apply()` supaya berlaku untuk semua model.
- **Data seeder demo buang-pakai** — `migrate:fresh --seed` boleh dijalankan sesering perlu untuk verifikasi, tapi task-task di plan ini kebanyakan diverifikasi lewat Pest test, bukan seeding manual.

## Urutan eksekusi

Task 1 → 3 (perbaikan fondasi: `widestScopeLevel()`, `TenantScope`, `scopeRank()`) WAJIB berurutan dan WAJIB selesai sebelum Task 4 (semua bergantung pada `widestScopeLevel()` mengenali `platform`). Task 4 dan Task 5 SENGAJA digabung jadi satu commit (baca catatan di Task 4 Step 5 — Task 4 sengaja membuat satu test lama gagal sampai Task 5 memperbaikinya, JANGAN commit di antara keduanya). Task 6, 7, 8 (frontend) bisa dikerjakan paralel secara konsep tapi harus SETELAH Task 4/5 selesai (butuh variabel baru dari controller). Task 9 (test tambahan) setelah semua Task 1-8. Task 10 di akhir.

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/rbac-pengguna-scope-filter/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 10):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final, kecuali step yang eksplisit bilang "baca dulu, verifikasi/konfirmasi" — itu WAJIB kamu jalankan verifikasinya).
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit — pastikan cocok dengan yang dikutip plan.
3. Jalankan `php -l <file>` (syntax check PHP) / `npm run build` (untuk JS) setelah tiap edit, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu (KECUALI Task 4 Step 4 yang plan-nya SENGAJA mengizinkan satu test lama gagal — baca step itu dengan teliti, itu diperbaiki di Task 5, bukan di Task 4).
5. Satu commit per task, KECUALI Task 4+5 yang digabung jadi satu commit (lihat instruksi eksplisit di Task 4 Step 5 dan Task 5 Step 4).
6. Jangan jalankan full test suite sampai Task 10.
7. Task 10 Step 3 butuh persetujuan user EKSPLISIT sebelum full suite (Step 4).

## Peringatan eksplisit dari plan — beberapa task punya ketidakpastian yang HARUS diverifikasi, bukan diasumsikan

1. **Task 2 (`TenantScopePlatformBypassTest.php`)** — test kedua ("does not extend the platform bypass to... Karyawan") memakai `Karyawan::factory()->create(['yayasan_id' => ..., 'lembaga_id' => ..., 'nik' => '...'])`. Kalau struktur `KaryawanFactory` ternyata beda (field wajib lain, nama field beda), sesuaikan test — tapi JANGAN ubah esensi assertion-nya (membuktikan bypass TIDAK menyebar ke model lain).
2. **Task 9 (test baru)** — beberapa test memakai `Yayasan::factory()->create(['nama' => '...'])`. Kalau field `nama` di `YayasanFactory` ternyata tidak ada / namanya beda, verifikasi dulu via `grep -n "nama" database/factories/YayasanFactory.php` sebelum menyesuaikan test — JANGAN paksakan assertion yang salah demi lolos.
3. **Task 8 (JS shared component)** — `dataTableFilter()` di `resources/js/data-table-filter.js` dipakai halaman LAIN juga (Peran, Siswa, Guru, Kelas, Lembaga). Step 4 di task ini meminta kamu mencari dan menjalankan test halaman lain yang memakai komponen sama untuk memastikan tidak ada regresi — WAJIB dijalankan, jangan dilewati karena "sepertinya aman".
4. **Task 4 & 5 (perubahan perilaku siswa)** — ini BUKAN bug, ini perubahan perilaku yang DISENGAJA (siswa sekarang muncul di daftar Pengguna secara default, sebelumnya sengaja dikecualikan). Kalau kamu menemukan test lain (di luar yang disebut plan) yang diam-diam mengasumsikan siswa selalu absen dari halaman ini, itu kemungkinan gap yang perlu dilaporkan ke user, BUKAN diperbaiki sendiri secara diam-diam.

## Pelajaran penting dari sub-project sebelumnya di repo ini

1. **Audit blind spot berulang** — di sub-project RBAC v2 sebelumnya, grep audit yang scope-nya diasumsikan sudah lengkap ternyata melewatkan beberapa consumer. Kalau kamu menduga ada file lain yang terdampak perubahan `TenantScope`/`widestScopeLevel()` di luar yang disebut plan ini, grep dulu (`grep -rln "widestScopeLevel" app database tests`) sebelum menganggap cakupan sudah lengkap.
2. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
3. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri secara diam-diam.
4. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
5. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)**, jangan asumsikan atau ekstrapolasi. Sub-project sebelumnya sempat ada klaim "339 test gagal" yang ternyata anomali environmental sesaat — kalau kamu melihat lonjakan kegagalan test yang tidak masuk akal dari perubahan yang kamu buat, laporkan sebagai temuan yang perlu verifikasi, bukan fakta final.
6. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: `widestScopeLevel()` mengenali `platform`, 2 test baru + 4 test lama semua PASS.
- Task 2: `TenantScope` bypass HANYA untuk model `User` + scope `platform`, 3 test baru PASS (termasuk bukti bypass TIDAK menyebar ke `Karyawan`).
- Task 3: `scopeRank('platform') === 4`, test baru + seluruh `UserManagementTest.php` PASS.
- Task 4+5: `UserController::index()` menerima `scope_group`, siswa tidak lagi selalu dikecualikan, search mencakup username, `UserManagementTest.php` (termasuk 3 test baru pengganti test siswa lama) semua PASS.
- Task 6: 7 chip scope tampil di halaman, placeholder search terupdate.
- Task 7: kolom Yayasan tampil kondisional untuk viewer `platform_super_admin`.
- Task 8: `setScopeGroup()`/`refreshRoleOptions()` bekerja, `npm run build` sukses, test halaman lain yang pakai `dataTableFilter` tetap PASS.
- Task 9: test chip filtering, count badge, dan visibilitas lintas-tenant platform admin semua PASS.
- Task 10: grep verifikasi kosong, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (atau kegagalan yang ada terbukti pre-existing/tidak terkait, dengan bukti), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri (commit hash per task, angka test pasti).
