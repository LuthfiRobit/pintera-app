# Kickoff Prompt — TD-AKADEMIK-002 (Retrofit ke `laravel-feature-standard`)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah direview mendalam. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-26-td-akademik-002-retrofit-skill-standard.md` — spec lengkap (§Latar Belakang WAJIB dibaca — menjelaskan kenapa task ini ADA dan prinsip "refactor internal murni, bukan kesempatan mengubah behavior").
2. `.agents/plans/2026-08-26-td-akademik-002-retrofit-skill-standard.md` — plan implementasi (4 task, kode lengkap, TDD step-by-step).
3. `.agents/skills/laravel-feature-standard/SKILL.md` — standar arsitektur yang jadi acuan (kalau ada keraguan soal konvensi FormRequest/DTO/Action, rujuk ke sini).

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`). Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Ini BUKAN fitur baru — ini PEMBERSIHAN TEKNIS (technical debt) dari Sprint 1-5 Fondasi Akademik Multi-Jenjang yang baru saja selesai.** Tugasmu memindahkan logic yang SUDAH ADA dan SUDAH BENAR ke layer arsitektur yang lebih rapi (FormRequest/DTO/Action), TANPA mengubah satu pun behavior/hasil/HTTP status code.
- **Prinsip paling penting di seluruh task ini**: kalau kamu menemukan sesuatu yang terlihat seperti bug/celah di kode lama saat membaca ulang — JANGAN diperbaiki sekalian. STOP dan laporkan ke user. Task ini murni "pindah kode ke tempat yang benar", bukan "sambil dirapikan sekalian diperbaiki".
- **Bukti utama task ini berhasil adalah: SEMUA test existing tetap hijau TANPA satu pun assertion diubah.** Test baru (utk Action) itu bonus, bukan bukti utama. Kalau kamu menemukan diri sendiri mengubah assertion di test existing (`FaseDefaultMappingControllerTest`, `KelasCrudTest`, `KelasPolaJamTest`, dll) supaya lolos — STOP, itu tandanya refactor-mu mengubah behavior, bukan test-nya yang salah.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 murni LINEAR.** Task 1 (pindah folder) independen dari 2-3, tapi ditaruh pertama krn paling kecil risikonya. Task 2 dan 3 independen satu sama lain (beda controller) tapi ditulis berurutan di plan.

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/td-akademik-002/progress.md`. Dispatch berurutan.

**Kalau tidak punya skill itu:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis.
2. **WAJIB baca file controller existing (`FaseDefaultMappingController.php` Task 2, `KelasController.php` Task 3) SEBELUM edit** dan bandingkan dgn kutipan di spec/plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. Satu commit per task (4 commit total).
6. **JANGAN jalankan full test suite sampai Task 4 Step 1.**

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **DILARANG KERAS menambah `exists:` rule baru** di `StoreKelasRequest`/`UpdateKelasRequest` untuk `tahun_ajaran_id`/`wali_kelas_guru_id`/`pola_jam_id` — ini akan mengubah response 404 (abort manual) jadi 422 (validation error) untuk ID yang tidak valid/lintas-lembaga. `fase_id` SATU-SATUNYA field dgn `exists:fase,id` (sudah ada sejak Sprint 3, dipertahankan apa adanya).
2. **Task 2 & 3 Step 6** — uniqueness check dan `authorizeMappingScope()` di `FaseDefaultMappingController` TETAP di controller, TIDAK dipindah ke Action (alasan: itu authorization/HTTP-response logic, bukan business mutation murni).
3. **Task 2 & 3, method lain di controller** (`index`, `create`, `edit`, `destroy`, `faseSuggestion`) — JANGAN disentuh sama sekali, HANYA `store()`/`update()`.
4. **Task 3 Step 8** — ini yang PALING KRITIS di seluruh plan: `tests/Feature/Admin/KelasCrudTest.php` (10 test, PRA-EXISTING sebelum Sprint 1) menguji ownership-check lintas-lembaga (guru/tahunAjaran/polaJam beda lembaga → 404). Kalau SATU SAJA dari 10 test ini gagal setelah retrofit, itu bug regresi nyata — STOP, jangan lanjut ke Task 4, laporkan ke user.
5. **Task 1 Step 6-7** — pastikan folder `Support/` benar-benar kosong sebelum dihapus, dan `grep` verifikasi nol referensi tersisa. Kalau grep menemukan referensi yang tidak disebut plan (mis. ada file lain yg belum diketahui memakai `Support\`), STOP dan laporkan.

## Pelajaran penting dari Sprint 1-5 (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test` TANPA filter apa pun untuk klaim "full suite hijau".
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 4 task ini** — termasuk godaan merapikan `PolaJamController` (yang levelnya sama tidak-patuh-nya) "karena sudah kelihatan pola dan gampang sekalian". Itu eksplisit di luar scope `TD-AKADEMIK-002` (lihat spec §Non-Goals).
6. **Jangan sentuh `TD-AKADEMIK-001` (`ElemenCp`)** — itu debt terpisah, belum dibahas arahnya, di luar scope task ini sama sekali.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: `Support/` sudah tidak ada, isinya di `Services/` dgn namespace benar, nol referensi lama tersisa, test pindahan tetap PASS jumlah sama persis.
- Task 2: `FaseDefaultMappingController::store()`/`update()` pakai `StoreFaseDefaultMappingRequest`/`UpdateFaseDefaultMappingRequest` + `FaseDefaultMappingData` + `CreateFaseDefaultMappingAction`/`UpdateFaseDefaultMappingAction`. 9 test `FaseDefaultMappingControllerTest` (Sprint 3) tetap PASS tanpa assertion diubah + 3 test Action baru PASS.
- Task 3: `KelasController::store()`/`update()` pakai `StoreKelasRequest`/`UpdateKelasRequest` + `KelasData` + `CreateKelasAction`/`UpdateKelasAction`. SEMUA test existing (`KelasCrudTest` 10 test, `KelasPolaJamTest`, `KelasFaseAssignmentTest`, `KelasFaseSuggestionTest`) tetap PASS tanpa assertion diubah + 4 test Action baru PASS.
- Task 4: **full test suite (`php artisan test` tanpa filter) 0 failed**, angka pasti dicatat, nol referensi `Domains\Akademik\Support` tersisa di file `.php` aktif, laporan final ke user berisi angka pasti + 4 commit hash + konfirmasi eksplisit "tidak ada behavior/HTTP-status yang berubah".
