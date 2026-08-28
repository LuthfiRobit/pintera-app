# Kickoff Prompt — Guard Role `guru` di Form Pengguna Generik (RBAC v2)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP untuk **FIX RBAC** — role `guru` bisa diassign lewat `Admin → Pengguna` (`UserController`), satu-satunya jalur yang bisa menghasilkan `User(role=guru)` TANPA profil `Guru` yang menaut (`guru.user_id`). Kamu tidak perlu brainstorming ulang, tidak perlu menulis spec baru — semua keputusan desain sudah final dan disetujui lewat proses review berlapis (termasuk satu fix kritis yang ditemukan lewat code-check eksplisit sebelum plan ditulis).

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-28-rbac-v2-guard-guru-generic-user-form.md` — spec lengkap. §1.2 (matriks role vs profil) dan §2.2(c) (fix kritis `update()`) WAJIB dibaca.
2. `.agents/plans/2026-08-28-rbac-v2-guard-guru-generic-user-form.md` — plan implementasi (5 task, kode lengkap, TDD step-by-step).

## Konteks penting — kenapa fix ini ada

`GuruController::store()` (jalur `Admin → Guru`) dan `AkunKaryawanGenerator::buat()` (jalur `Admin → Karyawan`) sudah benar dan transactional — keduanya selalu membuat `User` + profil (`Guru`/`Karyawan`) + role baseline dalam satu transaksi. Tapi `UserController::store()`/`update()` (jalur `Admin → Pengguna`, form generik) bisa meng-assign role `guru` ke `User` mana pun TANPA pernah membuat `Guru`. Hasilnya: `User(role=guru)` tanpa `Guru` — banyak fitur (RppController, dashboard guru, kehadiran-sdm self-service) diam-diam gagal karena semuanya bergantung ke `$user->guru?->id`.

**PENTING — batasan scope yang SUDAH diverifikasi lewat audit kode langsung (bukan tebakan)**: HANYA role `guru` yang butuh guard ini. `guru_bk` dan `wali_kelas` — walau namanya kedengaran berhubungan dengan "guru" — TERBUKTI (lewat `grep -rn "hasRole('guru_bk')"` dan `grep -rn "hasRole('wali_kelas')"` di seluruh `app/`, keduanya 0 hasil) tidak pernah dicek dependency profil apa pun di kode manapun. **JANGAN pernah memperluas guard ini ke role tersebut** — itu akan melanggar acceptance criterion eksplisit di spec (§4.3, §4.4).

## Peringatan PALING KRITIS — fix `update()` BUKAN sekadar guard penolakan

Task 3 di plan punya DUA bagian yang harus SAMA-SAMA ada, bukan cuma satu:
1. **Guard penolakan**: tolak kalau `guru` ADA di `$data['roles']` yang dikirim admin (mencegah penambahan).
2. **Preservasi paksa** (`$rolesToPersist`): kalau `$user` SUDAH punya role `guru`, paksa tetap disertakan ke `syncRoles()` dan ke perhitungan `baselineCarrierRole()` — TERLEPAS dari apa yang dikirim di request.

**Kenapa #2 wajib, bukan opsional**: setelah `guru` dikeluarkan dari checkbox form (Task 1), `User::functionalRoles()` (dipakai untuk pre-check checkbox di `_form.blade.php`) TETAP mengandung `guru` — artinya tidak ada checkbox untuk merepresentasikan "guru sudah tercentang". Kalau admin membuka akun seorang guru cuma untuk menambah role lain (mis. `wakasek_kurikulum`) dan submit, request TIDAK mengandung `guru` sama sekali. TANPA fix #2, `syncRoles()` akan **mencabut role `guru` DAN carrier `pegawai_lembaga` sekaligus** dari akun guru asli — padahal admin sama sekali tidak bermaksud itu. Ini BUKAN skenario hipotetis, ini bug nyata yang ditemukan dengan membaca `app/Models/User.php:115-118` dan `resources/views/admin/users/_form.blade.php:2` sebelum plan ditulis.

**Perhatikan juga**: guard penolakan (poin #1) memakai `$data['roles']` (array ASLI hasil validasi), sedangkan `syncRoles()`/perhitungan carrier (poin #2) memakai `$rolesToPersist` (array SETELAH preservasi). Kalau keduanya tertukar, update untuk guru existing akan SELALU ditolak (karena `guru` akan selalu ada di `$rolesToPersist` setelah preservasi). Plan Task 3 Step 5 sudah menulis kode yang benar — SALIN PERSIS.

## Peringatan KEDUA — 2 test lama HARUS diganti, bukan dihapus atau dibiarkan gagal

`tests/Feature/Admin/UserManagementTest.php` baris 361-405 (SEBELUM plan dieksekusi) punya 2 test yang secara eksplisit submit `roles` mengandung `guru` dan mengharapkan SUKSES — perilaku itu sekarang DILARANG. Task 4 Step 1-2 di plan sudah menulis versi pengganti PERSIS yang membuktikan invariant ASLI test tersebut (self-healing baseline, multi-role shared carrier) tapi lewat cara submit yang valid (tanpa `guru`). User (pemilik proyek) SUDAH menyetujui pendekatan "rewrite, bukan hapus" ini secara eksplisit — jangan tanya ulang, ikuti plan.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu struktur tabel (`guru`, `users`, `model_has_roles`) — pakai `database-schema`, jangan buka migration manual.
- **JANGAN buat script verifikasi terpisah/tinker** — test yang ditulis plan sudah cukup.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/controllers.md` (semua task: `UserController`)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dengan instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Radius perubahan produksi HANYA `app/Http/Controllers/Admin/UserController.php`** — Task 1 (`assignableRoles()`), Task 2 (`store()`), Task 3 (`update()`). TIDAK ADA file lain yang disentuh di luar test.
- **`GuruController`, `AkunKaryawanGenerator`, `RoleSeeder`, model `Guru`/`Karyawan`/`User`, `formRoleGroups()`, `User::functionalRoles()` TIDAK DIUBAH SAMA SEKALI** — kalau kamu merasa perlu mengubah salah satunya untuk membuat test lolos, STOP, itu tanda kamu menyimpang dari plan.
- Pesan error HARUS PERSIS: `'Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.'` (perhatikan tanda panah `→`, bukan `->` atau `=>`) — disalin verbatim di Task 2 DAN Task 3, jangan diketik ulang manual di kedua tempat (risiko typo membuat pesan beda).
- **Task 5 (terakhir)**: jalankan `UserManagementTest.php` + `RoleSeederTest.php` saja (bukan full suite) — plan ini kecil dan terisolasi ke satu controller, sesuai instruksi eksplisit user untuk plan ini. Full suite TIDAK diminta untuk plan ini.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 → 5 murni LINEAR.** Task 4 Step 1-2 (rewrite 2 test lama) sengaja ditunda sampai Task 3 selesai karena test tersebut butuh perilaku LENGKAP `update()` (termasuk preservasi) untuk lolos — jangan pindahkan rewrite itu ke task lebih awal.

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini kecil (5 task, 1 file produksi) — `subagent-driven-development` direkomendasikan tapi eksekusi manual juga cukup mudah untuk plan sekecil ini kalau skill tidak tersedia.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca `UserController.php` SEBELUM edit** (baris yang disebut plan) dan bandingkan dengan kutipan "baris X saat ini" — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l app/Http/Controllers/Admin/UserController.php` setelah tiap edit, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal DAN bukan bagian yang memang diharapkan gagal sementara (lihat Task 2 Step 6, Task 3 test lama SEMENTARA gagal sampai Task 4), diagnosis dulu.
5. 5 commit total (1 per task, Task 5 cuma commit kalau Pint mereformat sesuatu).
6. **Full suite TIDAK diminta** — cukup `UserManagementTest.php` + `RoleSeederTest.php` di Task 5.

## Pelajaran penting dari sprint-sprint RBAC sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti).**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Task 2 Step 6 mengharapkan 2 test LAMA gagal sementara — itu BUKAN bug, itu diharapkan.** Jangan panik dan mencoba memperbaikinya di Task 2 atau Task 3; itu tugas Task 4 Step 1-2. Kalau kamu perbaiki lebih awal, ikuti kode PERSIS yang ditulis Task 4 (jangan improvisasi versi lain).
6. **Jangan pernah menambahkan `guru_bk`/`wali_kelas`/8 role administratif lain ke guard manapun** — audit kode sudah membuktikan tidak perlu, dan itu akan langsung melanggar test acceptance yang eksplisit (§4.3/§4.4 di spec, Task 4 Step 4-5 di plan).

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu. Task 4 Step 8 secara eksplisit meminta kamu grep file test lain yang mungkin bersinggungan dengan `admin.users.store`/`admin.users.update` + `guru` — kalau ketemu file DI LUAR yang disebut plan, JANGAN diam-diam mengubahnya, laporkan dulu ke user.

## Definisi selesai

- Task 1: `guru` tidak lagi muncul di `rolesByGroup` form create/edit; `guru_bk`/`wali_kelas` tetap muncul.
- Task 2: `store()` menolak `guru` (sendirian maupun kombinasi) dengan pesan yang benar, `User` tidak dibuat.
- Task 3: `update()` menolak PENAMBAHAN `guru`, DAN tidak pernah mencabut `guru`+`pegawai_lembaga` dari user yang sudah punya, bahkan saat request tidak menyertakan `guru` sama sekali.
- Task 4: 2 test lama sudah diganti (bukan dihapus) dan PASS dengan cara submit yang valid; `guru_bk`/`wali_kelas` sendirian terbukti tetap bisa dibuat lewat form generik.
- Task 5: `UserManagementTest.php` + `RoleSeederTest.php` **0 failed**, angka pasti dicatat, laporan final ke user berisi angka pasti + commit hash (4-5 commit) + konfirmasi eksplisit: `guru` terkunci di `Admin → Guru`, `guru_bk`/`wali_kelas` tidak terpengaruh, akun guru existing aman dari pencabutan role tidak sengaja.
