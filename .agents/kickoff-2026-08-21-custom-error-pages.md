# Kickoff Prompt — Halaman Error Custom Bergaya Pintera

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-21-custom-error-pages.md` — spec (kenapa dan apa: 7 halaman error custom, mapping ikon, copy, layout)
2. `.agents/plans/2026-08-21-custom-error-pages.md` — plan implementasi (4 task, lengkap dengan kode Blade dan langkah)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode Blade/PHP lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- Tugasnya murni kosmetik/frontend: repo ini TIDAK punya `resources/views/errors/` sama sekali, semua kode error (403/404/419/422/429/500/503) jatuh ke halaman default Laravel yang polos tanpa brand. Plan ini membuat 7 halaman custom yang reuse identitas visual `resources/views/layouts/guest.blade.php` (gradient dark ink→brass, brand mark, kartu putih rounded) plus sistem ikon SVG inline yang sudah ada di `resources/views/components/icon.blade.php`.
- **TIDAK ADA perubahan logic/controller/middleware/route apapun** — kalau kamu merasa perlu mengubah sesuatu di luar Blade view/component, STOP dan laporkan ke user, itu di luar cakupan.
- Task 1 menambah 3 `@case` baru ke `icon.blade.php` (ikon yang sudah dipakai luas di seluruh aplikasi, JANGAN diubah/dihapus case yang sudah ada). Task 2 buat 1 component reusable. Task 3 buat 7 view tipis + test render per kode. Task 4 test tombol auth-aware + full-suite gate.
- **Cara test render halaman error yang WAJIB dipakai**: `Route::get('/uji-error/{code}', fn () => abort({code}));` di dalam test itu sendiri, lalu `$this->get(...)`. JANGAN coba simulasikan CSRF/rate-limit/maintenance-mode asli — plan sudah menjelaskan kenapa itu tidak reliable di test suite ini (CSRF di-skip otomatis saat `runningUnitTests()`).
- **Catatan soal 422**: halaman ini nyaris tidak pernah muncul lewat form submit biasa (Laravel redirect 302 untuk validation error HTML, bukan render 422), itu perilaku bawaan Laravel bukan bug plan ini — sudah dijelaskan detail di plan, jangan dianggap sebagai sesuatu yang perlu "diperbaiki".

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdd/progress.md`. Ini mode yang sama yang dipakai untuk Spec 1, Spec 2, dan migrasi domain sebelumnya di branch yang sama.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 4, urutannya penting — Task 2 butuh Task 1 selesai untuk ikon `book_search`/`server`/`build`, Task 3 butuh Task 2 selesai untuk component `<x-error-page>`):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Jalankan verifikasi manual (tinker, grep) DAN test scoped yang disebut di tiap task SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
3. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task.
4. Jangan jalankan full test suite sampai Task 4.
5. Task 4 Step 5 butuh persetujuan user sebelum menjalankan full suite — TANYA dulu, jangan otomatis jalan.

## Pelajaran penting dari review pekerjaan sebelumnya di branch ini (WAJIB diperhatikan)

Review independen terhadap eksekusi Spec 1 dan Spec 2 sebelumnya di branch ini menemukan pola kegagalan yang harus dihindari:
1. **Jangan tandai step/task selesai di commit message atau handoff log kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti, grep yang hasilnya dicek, dst) — bukan asumsi dari membaca kode.
2. **Kalau full suite menunjukkan kegagalan yang TIDAK terkait sama sekali dengan halaman error, jangan langsung anggap itu masalah dari pekerjaanmu.** Ada pola flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) yang gagal sesekali karena randomness, tidak terkait perubahan apapun — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi sebelum melaporkan sebagai regresi.
3. **Kalau plan mengasumsikan sesuatu tentang file yang ternyata sudah berbeda saat kamu baca (misal `icon.blade.php` sudah punya `@case('book_search')` dari commit lain) — JANGAN duplikasi atau menimpa diam-diam.** Cek dulu, laporkan penyimpangannya ke user.

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `1f70bb9` di branch `rbac-v2`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak atau "memperbaiki sendiri". Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Tulis handoff log di `.agents/logs/2026-08-21-custom-error-pages.md` (format mengikuti handoff log Spec 1/Spec 2 sebelumnya di folder yang sama: ringkasan per task, commit hash, hasil verifikasi akhir dengan angka pasti). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan menjalankan full test suite sendiri) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history).

## Definisi selesai

Task 4 selesai: `resources/views/errors/` berisi persis 7 file (403/404/419/422/429/500/503), `ErrorPagesTest.php` punya 9 test yang semuanya hijau, full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (0 failed, 0 error, total test ≥ 1904), handoff log tertulis di `.agents/logs/2026-08-21-custom-error-pages.md` dengan bukti verifikasi yang bisa ditelusuri (bukan klaim tanpa command).
