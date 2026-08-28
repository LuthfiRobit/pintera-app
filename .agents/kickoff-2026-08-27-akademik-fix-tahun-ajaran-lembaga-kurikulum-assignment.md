# Kickoff Prompt — Fix Konsistensi Ownership Tahun Ajaran vs Lembaga (Kurikulum Assignment)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP untuk fix defense-in-depth kecil pada `KurikulumAssignmentController::store()`. Spec ini sudah melalui 2 putaran revisi berdasarkan review manusia yang ketat (soal wording "otorisasi" vs "ownership", gaya query, cakupan matrix test, dan wording klaim test) — versi final sudah presisi. Kamu tidak perlu audit ulang, tidak perlu menulis spec baru.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-fix-tahun-ajaran-lembaga-kurikulum-assignment.md` — spec lengkap (versi final, sudah direvisi 2x).
2. `.agents/plans/2026-08-27-akademik-fix-tahun-ajaran-lembaga-kurikulum-assignment.md` — plan implementasi (1 task, kode lengkap, TDD step-by-step).

## Konteks penting — kenapa fix ini ada

`StoreKurikulumAssignmentRequest` hanya validasi `tahun_ajaran_id` dengan `exists:tahun_ajaran,id` — tidak cek kepemilikan lembaga. Bukan celah lewat UI normal (dropdown form sudah difilter benar ke lembaga aktor), murni celah defense-in-depth via POST manual: bisa menghasilkan baris `kurikulum_assignment` dengan `lembaga_id` dan `tahun_ajaran_id` yang saling tidak konsisten.

## Peringatan PALING KRITIS — invariant berbasis NILAI, bukan JENIS AKTOR

Jangan menulis kode yang bercabang berdasarkan "apakah aktor platform/yayasan atau admin lembaga". Invariant yang benar HANYA berdasarkan **nilai `$lembagaId` efektif** yang sudah dihitung di baris existing (`$lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;`):
- `$lembagaId !== null` → validasi ownership (SIAPA PUN aktornya, platform atau bukan).
- `$lembagaId === null` → SAMA SEKALI tidak divalidasi (kasus "default nasional", disengaja).

Plan sudah menulis kode yang benar — SALIN PERSIS, jangan tambah percabangan role-check lain.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu struktur tabel (`kurikulum_assignment`, `tahun_ajaran`) — pakai `database-schema`, jangan buka migration manual.
- **JANGAN buat script verifikasi terpisah/tinker** — test yang ditulis plan sudah cukup.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/controllers.md` (`KurikulumAssignmentController`)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.68.0 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Gunakan `TahunAjaran::whereKey($id)->where('lembaga_id', $lembagaId)->exists()`** — satu query gabungan. JANGAN `TahunAjaran::find($id)` lalu cek null terpisah (sudah dikoreksi dari draft awal, jangan dibalikin).
- **Test kasus "lembaga_id null"** (matrix baris #5) HARUS pakai assertion sempit `assertSessionDoesntHaveErrors(['tahun_ajaran_id'])` — JANGAN `assertRedirect()`/klaim "sukses total", itu overclaim yang sudah dikoreksi di spec. Salin persis assertion di plan.
- **Tidak perlu full test suite** — cukup `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` penuh sbg checkpoint (Task 1 Step 6).

## Urutan eksekusi

Plan ini cuma 1 task, 8 step, linear. Boleh eksekusi manual langsung (`superpowers:executing-plans`) tanpa perlu `subagent-driven-development` — terlalu kecil untuk butuh itu.

**Kalau tidak punya skill superpowers sama sekali:**
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`KurikulumAssignmentController.php` baris 1-84, `KurikulumAssignmentControllerTest.php` baris 1-111) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 2 commit total (1 fix + 1 docs), pesan commit sudah ditulis di Step terakhir masing-masing.

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Step 3** — 2 dari 4 test baru (kasus "matches that lembaga" dan "left null") SUDAH PASS bahkan SEBELUM fix diterapkan — itu memang benar (regresi-negatif baseline), BUKAN tanda kamu salah tulis test. Jangan bingung kalau di Step 3 sebagian test "gagal sesuai ekspektasi" dan sebagian lagi "sudah hijau dari awal" — keduanya benar sesuai plan.
2. **Step 6** — test existing baris 28-41 (`'creates a kurikulum assignment'`) adalah bukti hidup matrix baris #1 (admin lembaga + tahun ajaran sendiri → sukses) — kalau ini sampai gagal setelah fix, itu tanda regresi nyata, STOP dan investigasi.

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti).**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 1 task ini** — termasuk godaan memperbaiki race condition pada cek duplikat `exists()` yang disebut spec sbg "diketahui tapi di luar scope" — itu bukan bagian plan ini.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- `store()` menolak kombinasi `lembaga_id` terisi + `tahun_ajaran_id` milik lembaga lain, untuk admin lembaga MAUPUN platform/yayasan yang pilih lembaga eksplisit.
- Kasus `lembaga_id` null (default nasional) TIDAK terkena validasi baru sama sekali.
- Seluruh `KurikulumAssignmentControllerTest.php` (7 existing + 4 baru = 11 test) PASS, angka pasti dicatat.
- `PETA_PENGEMBANGAN.md` dicatat, laporan final ke user berisi angka pasti + commit hash.
