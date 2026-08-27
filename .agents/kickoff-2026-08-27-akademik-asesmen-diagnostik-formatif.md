# Kickoff Prompt — Asesmen Diagnostik & Formatif (Priority 6)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah direview mendalam (brainstorming interaktif multi-putaran + review user yang menghasilkan penyempurnaan naming/test + spec self-review). Kamu tidak perlu brainstorming ulang, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-asesmen-diagnostik-formatif.md` — spec lengkap. §2 WAJIB dibaca — 5 keputusan bisnis kunci: `komponen_id` tetap wajib utk SEMUA jenis (termasuk Diagnostik/Formatif), `jenis` dan `assessment_type` ORTOGONAL (tidak ada constraint silang), v1-minimal (reuse halaman show existing, tidak ada analytics baru), `RaporCalculationService` WAJIB mengecualikan Diagnostik/Formatif (blocker keamanan data), `v1Didukung()` di-retire diganti `cases()`+`masukRapor()`.
2. `.agents/plans/2026-08-27-akademik-asesmen-diagnostik-formatif.md` — plan implementasi (5 task, kode lengkap, TDD step-by-step). Sudah lolos self-review termasuk verifikasi mandiri 2 fakta yang sempat ditandai perlu dicek (nama route `guru.asesmen.update-nilai` dikonfirmasi persis dari `routes/guru.php:19`, default `jenis` di `AsesmenFactory` dikonfirmasi `SumatifLingkupMateri`) — kamu TIDAK PERLU verifikasi ulang keduanya, sudah pasti benar.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

Project ini punya Laravel Boost MCP server terpasang. Ikuti aturan project (`CLAUDE.md` root):

- **Untuk verifikasi struktur data/route** (kalau kamu ragu soal sesuatu yang TIDAK sudah diverifikasi di plan) — pakai `database-schema`/`php artisan route:list`, bukan menebak.
- **Untuk debugging** — pakai `database-query` (read-only) atau `last-error`/`read-log-entries`, BUKAN `php artisan tinker` manual, dan BUKAN membuat script verifikasi terpisah.
- Plan ini sebagian besar transkripsi kode lengkap — `search-docs` HANYA perlu dipanggil kalau kamu ragu soal sintaks `Rule::enum()` version-sensitive, bukan di setiap step.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/domains-enums.md`, `.ai/rules/enums.md` (Task 1: modifikasi `JenisAsesmen`)
- `.ai/rules/controllers.md`, `.ai/rules/requests.md` (Task 2: modifikasi `AsesmenController`, `StoreAsesmenRequest`)
- `.ai/rules/services.md`, `.ai/rules/app-services.md` (Task 3: modifikasi `RaporCalculationService`)
- `.ai/rules/tests.md` (semua task)

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`), Pest v4, MySQL 8.0.30. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Task 3 adalah BLOCKER KEAMANAN DATA, bukan task biasa** — kalau kamu eksekusi Task 2 (buka form guru ke 6 jenis) TANPA Task 3 (filter rapor), Diagnostik/Formatif akan mencemari nilai rapor sungguhan begitu ada guru pakai fitur ini di produksi. Task 1→2→3 LINEAR, jangan pernah deploy/anggap selesai kalau baru sampai Task 2.
- **`v1Didukung()` DIHAPUS TOTAL** (bukan dipertahankan sbg alias/deprecated) — project ini tidak memakai backward-compatibility shim (lihat `CLAUDE.md`). Test Task 1 Step 1 eksplisit membuktikan method itu benar-benar hilang (`method_exists(...) === false`).
- **`komponen_id` TETAP WAJIB ≥1 utk semua jenis** — JANGAN tergoda membuat jalur "Diagnostik tanpa TP" meski secara pedagogis kadang masuk akal (assessment umum di awal semester) — itu eksplisit di luar scope (spec §2 poin 1, Non-Goal §7).
- **TIDAK ADA constraint silang `jenis`×`assessment_type`** — JANGAN menambahkan validasi "Diagnostik Non-Kognitif harus Narrative" atau semacamnya, meski terasa lebih "benar" secara pedagogis. Ini keputusan eksplisit dari brainstorming (spec §2 poin 2).
- **TIDAK ADA halaman rekap/analytics baru** — `AsesmenController::show()`/`updateNilai()` TIDAK BERUBAH SAMA SEKALI. Task 4 murni test, bukan fitur baru.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 → 5 murni LINEAR.**

**PERHATIAN KHUSUS Task 3**: `RaporCalculationService::hitungRekapKelas()` baris 28-31 adalah SATU-SATUNYA titik query `Asesmen` di seluruh method itu (dikonfirmasi ulang di plan) — pastikan filter `whereIn('jenis', JenisAsesmen::masukRapor())` ditambahkan PERSIS di query itu, bukan di tempat lain yang kelihatannya juga masuk akal (mis. difilter belakangan di PHP setelah `get()` — itu boleh secara fungsional tapi menyimpang dari kode yang ditulis plan tanpa alasan, JANGAN lakukan itu, ikuti persis).

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini sedang (5 task, ~8 file tersentuh, ada 1 task berstatus blocker keamanan data) — REKOMENDASI pakai `superpowers:subagent-driven-development` supaya Task 3 dapat review terpisah & lebih ketat dari task lain. Kalau tidak tersedia, pakai `superpowers:executing-plans`.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`JenisAsesmen.php`, `AsesmenController.php` baris 70, `StoreAsesmenRequest.php`, `RaporCalculationService.php` baris 5-31) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 5 commit total (satu per task, pesan commit sudah ditulis di tiap Step terakhir).
6. **JANGAN jalankan full test suite sampai Task 5 Step 2.**
7. Jalankan `vendor/bin/pint --dirty --format agent` di akhir Task 2 dan Task 4 (sudah tertulis eksplisit di step-nya).

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 3** — `masukRapor()` HARUS punya docblock yang menyatakan kontrak semantik eksplisit ("sumber perhitungan rapor", bukan sekadar "jenis yang didukung") — ini keputusan desain, bukan hiasan komentar, jangan dihapus/disingkat.
2. **Task 2 Step 1** — 3 test baru (Diagnostik Kognitif, Diagnostik Non-Kognitif, Formatif) HARUS terpisah, bukan digabung jadi 1 test dgn dataset — plan menulisnya terpisah supaya tiap jenis eksplisit terbukti, ikuti persis.
3. **Task 3 Step 1** — test regresi ke-4 (`'keeps rekap at the Sumatif value even when...'`) WAJIB assert dulu bahwa data Formatif/Diagnostik benar-benar tersimpan (baris `expect(Asesmen::where(...)->exists())->toBeTrue()` dkk) SEBELUM assert exclusion — JANGAN hapus assert "existence" itu meski terasa redundan, itu mencegah false-positive kalau data non-rapor ternyata gagal dibuat.
4. **Task 5 Step 1** — grep akhir `"v1Didukung"` HARUS 0 hasil di SEMUA folder (`app/`, `resources/`, `tests/`). Kalau ada sisa, STOP dan laporkan ke user (jangan diam-diam hapus sendiri tanpa laporan).

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test --compact` TANPA filter apa pun di Task 5 utk klaim "full suite hijau".
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 5 task ini** — termasuk godaan membuat halaman ringkasan Diagnostik/Formatif yang "kelihatannya berguna", atau constraint jenis×assessment_type. Semua itu eksplisit Non-Goal (spec §7).

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`route:list`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: `JenisAsesmen::masukRapor()` ada dgn docblock kontrak semantik, `v1Didukung()` benar-benar hilang, 4 test PASS.
- Task 2: form guru menampilkan 6 jenis, `StoreAsesmenRequest` validasi via `Rule::enum()`, 3 test sukses baru + seluruh test existing di file itu PASS.
- Task 3: `RaporCalculationService` filter `masukRapor()` ditambahkan, 4 test exclusion PASS (termasuk regresi kuat: Sumatif 88 + Formatif 100 + Diagnostik 100 → tetap 88), seluruh test `RaporCalculationService` existing lain tetap PASS.
- Task 4: 1 test siklus penuh (create→show→input nilai→show read-back→exclusion rapor) PASS tanpa perubahan kode produksi apa pun.
- Task 5: grep `v1Didukung` nol hasil, **full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, `PETA_PENGEMBANGAN.md` sudah ditandai Prioritas #6 SELESAI, laporan final ke user berisi angka pasti + 5 commit hash.
