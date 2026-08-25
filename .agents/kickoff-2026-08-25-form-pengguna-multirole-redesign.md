# Kickoff Prompt — Redesain Form Create/Edit Pengguna (Multi-Role Checkbox & Redirect Siswa/Orang Tua)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-25-form-pengguna-multirole-redesign.md` — spec lengkap (latar belakang 7 masalah yang ditemukan, taksonomi checkbox §4, logic baseline carrier §5, non-goals §3).
2. `.agents/plans/2026-08-25-form-pengguna-multirole-redesign.md` — plan implementasi (11 task, kode lengkap per task).

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `af0bade`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Kenapa sub-project ini ada**: review terhadap form create/edit Pengguna (setelah sub-project sebelumnya menyempurnakan halaman list-nya) menemukan bug DESTRUKTIF — `update()` memakai `syncRoles([$data['role']])` dengan form single-select, sehingga mengedit profil user manapun akan diam-diam MENGHAPUS role baseline `pegawai_lembaga`/`pegawai_yayasan` yang wajib dipertahankan (invariant RBAC v2). Plus beberapa masalah lain: role select bukan Tom Select/checkbox meski RBAC v2 mendukung multi-role sungguhan, tampilan role pakai `roles->first()` yang arbitrary, dead code perbandingan role dengan string yang tidak pernah match, dan form generik ini membolehkan membuat/mengedit akun `siswa`/`orang_tua` yang seharusnya cuma boleh lewat modul masing-masing.
- **PALING KRITIS**: role scope-carrier (`pegawai_lembaga`/`pegawai_yayasan`) TIDAK PERNAH ditampilkan sebagai checkbox — dihitung otomatis backend berbasis `scope_level` role yang dipilih (BUKAN hardcode nama role, supaya otomatis berlaku untuk role fungsional baru di masa depan seperti kandidat `kepala_tu`/`staff_tu`).
- **Data seeder demo buang-pakai** — `migrate:fresh --seed` boleh dijalankan sesering perlu, tapi task-task di plan ini kebanyakan diverifikasi lewat Pest test.

## Urutan eksekusi

Task 1 (accessor `User::functionalRoles()`) dan Task 2 (helper `UserController` privat) WAJIB selesai dulu, keduanya independen satu sama lain tapi dua-duanya prasyarat untuk Task 3+. Task 3 (`create()`/`store()`) SEBELUM Task 4 (`edit()`/`update()`/`toggleActive()`) — Task 3 Step 7 SENGAJA membiarkan sebagian test lama (yang menyentuh `update()`) tetap gagal sampai Task 4 selesai, itu bukan kesalahan, JANGAN diperbaiki di Task 3. Task 5 dan 6 (test tambahan) SETELAH Task 3+4 selesai. Task 7 (Blade form) SETELAH Task 3+4 (butuh `$rolesByGroup` dari controller). Task 8 dan 9 (Blade lain) independen dari Task 7, bisa paralel. Task 10 (test link redirect) SETELAH Task 9. Task 11 di akhir.

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/form-pengguna-multirole-redesign/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 11):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final).
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit — pastikan cocok dengan yang dikutip plan.
3. Jalankan `php -l <file>` (syntax check PHP) / `php artisan view:clear` (Blade) setelah tiap edit, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu (KECUALI Task 3 Step 7 yang plan-nya SENGAJA mengizinkan sebagian test lama gagal — baca step itu dengan teliti, diperbaiki di Task 4, bukan di Task 3).
5. Satu commit per task.
6. Jangan jalankan full test suite sampai Task 11.
7. Task 11 Step 3 butuh persetujuan user EKSPLISIT sebelum full suite (Step 4).

## Peringatan eksplisit dari plan — beberapa task punya ketidakpastian yang HARUS diverifikasi, bukan diasumsikan

1. **Task 6 (test guard orang_tua)** — meniru pola persis test guard siswa yang sudah ada. Kalau ternyata `abort_if` di controller (hasil Task 4) tidak berperilaku seperti yang diharapkan (mis. urutan validasi vs guard berbeda), verifikasi manual dengan tinker sebelum menyimpulkan test-nya salah.
2. **Task 10 (test redirect link)** — memakai `Siswa::factory()->create(['user_id' => $siswaUser->id])` dan `OrangTua::factory()->create(['user_id' => $orangTuaUser->id])`. Kedua field `user_id` SUDAH dikonfirmasi ada saat plan ditulis, tapi tetap baca ulang `app/Models/Siswa.php`/`app/Models/OrangTua.php` sekali lagi sebelum menulis kode test-nya — kalau baseline sudah berubah, STOP dan laporkan.
3. **Task 5 (test regresi baseline carrier)** — assertion `expect($created->roles()->count())->toBe(3)` mengasumsikan TIDAK ada role lain yang otomatis ter-assign di luar 2 role fungsional + 1 baseline. Kalau ada observer/listener lain di model `User` yang menambah role otomatis (di luar yang disebutkan plan ini), assertion ini bisa salah — verifikasi dulu via tinker kalau gagal, jangan asal ubah angka supaya lolos tanpa mengerti kenapa.

## Pelajaran penting dari sub-project sebelumnya di repo ini

1. **Bug destruktif yang jadi alasan sub-project ini ada ditemukan lewat review kode independen, BUKAN oleh executor sebelumnya.** Pola ini sudah berulang di project ini — kalau kamu (sebagai executor) menemukan sesuatu yang mencurigakan di luar cakupan plan saat mengerjakan task, JANGAN abaikan, laporkan ke user meski itu di luar scope plan ini.
2. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
3. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri secara diam-diam.
4. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
5. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)**, jangan asumsikan atau ekstrapolasi.
6. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.** Sub-project sebelumnya di repo ini punya kasus test yang "dilemahkan" (skenario pemicu bug dihapus dari test) supaya lolos, alih-alih bug-nya diperbaiki — itu ketahuan lewat review manual dan HARUS dihindari di sini. Kalau sebuah test gagal karena skenario yang memang seharusnya berhasil, perbaiki KODE-nya, JANGAN lemahkan test-nya.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: `User::functionalRoles()` mengecualikan carrier role, 2 test baru + test lama semua PASS.
- Task 2: 4 method privat baru di `UserController`, syntax valid.
- Task 3: `create()`/`store()` dukung `roles[]` + baseline auto-assign, 6 test lama diupdate + PASS (test `update()`-related SENGAJA masih merah, diperbaiki Task 4).
- Task 4: `edit()`/`update()`/`toggleActive()` dukung `roles[]` tanpa menghapus baseline, guard diperluas ke `orang_tua`, SEMUA test di `UserManagementTest.php` PASS.
- Task 5: 5 test regresi baru (bug destruktif, multi-role, baseline tidak ditambah untuk role yayasan murni, validasi tolak siswa/orang_tua/carrier, rank-gating per-role) PASS.
- Task 6: test guard `orang_tua` (edit/update/toggle-active 404) PASS.
- Task 7: `_form.blade.php` checkbox terkelompok per scope, tidak ada sisa referensi `$roles`/`name="role"` lama.
- Task 8: `edit.blade.php`/`tabs/profil.blade.php` pakai `functionalRoles()`, dead code `'Lembaga / Sekolah'` terhapus total.
- Task 9: `_daftar.blade.php` redirect Edit siswa/orang_tua ke modul masing-masing, toggle-active disembunyikan untuk keduanya.
- Task 10: 4 test baru (redirect link, toggle-active hidden, dead code fix) PASS.
- Task 11: grep verifikasi kosong (tidak ada sisa `role` singular/`roles->first()`/dead code string), full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (atau kegagalan yang ada terbukti pre-existing/tidak terkait, dengan bukti), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri (commit hash per task, angka test pasti).
