# Kickoff Prompt — Fondasi Akademik Multi-Jenjang, Sprint 5 (Konsolidasi Derivasi Kategori Jenjang Rapor)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah direview mendalam. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint5.md` — spec lengkap (§Latar Belakang WAJIB dibaca — menjelaskan kenapa outline "Report Engine" asli DIBATALKAN TOTAL, diganti scope jauh lebih kecil).
2. `.agents/plans/2026-08-26-akademik-multi-jenjang-sprint5.md` — plan implementasi (2 task, kode lengkap, TDD step-by-step).

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`). Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Nama "Sprint 5" di roadmap awal adalah "Report Engine Abstraction"** — TAPI setelah audit kode nyata, ternyata premis outline itu SALAH: outline mengira "cuma Dikdas yang diimplementasikan, PAUD dkk belum ada builder-nya" — padahal 4 template rapor (`paud`/`sd`/`smp-sma`/`smk`) **sudah production semua**, lewat SATU builder data generik (`RaporPdfDataBuilder::build()`) yang datanya dikonsumsi seragam oleh ke-4 Blade view. Kalau kamu (atau siapa pun) tergoda membangun `ReportBuilder` interface/`ReportEngine`/builder-per-jenjang krn "kan namanya Report Engine sprint" — **JANGAN**. Itu sudah dibahas panjang dan DITOLAK eksplisit oleh user krn tidak sesuai kenyataan dan berisiko merusak fitur yang sudah jalan.
- **Scope Sprint 5 SEBENARNYA jauh lebih kecil**: cuma refactor 1 method (`RaporPdfDataBuilder::templateUntukJenjang()`) supaya delegasi ke `AcademicProfile::fromBentukPendidikan()->reportTemplate` (Sprint 4, sudah ada) — bukan punya logic `if`/`in_array` sendiri lagi. Tujuannya menghilangkan duplikasi 2 sumber kebenaran "kategori jenjang" yang sebelumnya ada.
- **Tidak ada file baru dibuat** — cuma 2 file di-modify (`RaporPdfDataBuilder.php` + test-nya).

## Urutan eksekusi

**Task 1 → 2 murni LINEAR.** Task 1 sangat kecil (1 method diubah + 1 test file diupdate, TDD 5 step). Task 2 murni verifikasi.

**PERHATIAN KHUSUS Task 1 Step 1-2**: ada test EXISTING (`tests/Feature/Akademik/RaporPdfDataBuilderTest.php`, bukan file baru) yang berisi assertion `expect($builder->templateUntukJenjang('NILAI_TAK_DIKENAL'))->toBe('pdf.rapor.sd')` — ini menguji PERSIS behavior lama (silent fallback) yang Sprint 5 SENGAJA ubah jadi throw. Plan sudah mengutip isi lengkap file lama & versi barunya — **ganti test ini sbg bagian dari Task 1, ini bukan "test lain yang kebetulan gagal" yang perlu dilaporkan sbg temuan aneh, ini konsekuensi yang sudah diantisipasi spec.**

**Kalau kamu punya akses ke skill `superpowers`:**
Boleh eksekusi manual langsung (`superpowers:executing-plans` atau inline) — scope terlalu kecil untuk butuh `subagent-driven-development`.

**Kalau tidak punya skill itu:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis.
2. **WAJIB baca file existing (`RaporPdfDataBuilder.php`, `RaporPdfDataBuilderTest.php`) dulu dan bandingkan dgn yang dikutip plan** — kalau baseline-nya beda (mis. sudah ada perubahan lain yang tidak diketahui plan), STOP dan laporkan ke user, jangan menebak-nebak menyesuaikan sendiri.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. Satu commit untuk Task 1 (Task 2 tidak menghasilkan commit, murni verifikasi + laporan).
6. **JANGAN jalankan full test suite sampai Task 2 Step 2.**

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 1** — baca ulang `RaporPdfDataBuilderTest.php` baris 1-18 SEBELUM edit, pastikan isinya persis sama dgn yang dikutip plan.
2. **Task 1 Step 3** — baca ulang `RaporPdfDataBuilder::templateUntukJenjang()` (skitar baris 152-168) SEBELUM edit, termasuk komentar docblock `/** Whitelist sama seperti field kondisional 04c... */` yang HARUS DIHAPUS (bukan dipertahankan) krn whitelist-nya sekarang pindah ke `AcademicProfile`.
3. **Branch `LogicException` (defense-in-depth) TIDAK PERLU test coverage terpisah** — jangan tergoda menambah test aneh (mocking/reflection) demi 100% coverage. Ini keputusan eksplisit di spec §2, sudah final.
4. **Task 2 Step 1** — verifikasi baca (bukan edit) 2 consumer controller (`Guru\RaporController::cetak()`, `Lembaga\Rapor\PersetujuanController::cetak()`) untuk konfirmasi signature `templateUntukJenjang()` yang tidak berubah memang membuat keduanya aman tanpa modifikasi. Kalau ternyata ada kebutuhan menangkap exception di sana yang tidak diantisipasi plan, STOP dan laporkan — jangan diam-diam menambah try/catch di luar Task 1.
5. **Task 2 Step 2** — baseline sebelum Sprint 5 adalah **2221 passed, 4 skipped** (state akhir Sprint 4). Laporkan angka NYATA dari `php artisan test` tanpa filter, jangan asumsikan angka.

## Pelajaran penting dari Sprint 1-4 (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test` TANPA filter apa pun untuk klaim "full suite hijau".
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah fitur di luar 2 task ini** — termasuk godaan membangun `ReportBuilder`/`ReportEngine` "karena namanya kedengaran seperti Report Engine sprint". Itu sudah eksplisit ditolak, lihat §Konteks singkat di atas.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: `RaporPdfDataBuilder::templateUntukJenjang()` delegasi ke `AcademicProfile::fromBentukPendidikan()->reportTemplate`, docblock whitelist lama dihapus, fail-fast 2 lapis (`InvalidArgumentException` dari `AcademicProfile` + `LogicException` defense-in-depth baru). Test existing diupdate (bukan dihapus diam-diam) — 9 test table-driven kategori (termasuk SLB dgn catatan compatibility) + 1 test throw, semua PASS. `build()` dan Blade template tidak disentuh. `php -l` bersih, 1 commit.
- Task 2: 2 consumer controller diverifikasi tidak perlu diubah, **full test suite (`php artisan test` tanpa filter) 0 failed**, angka pasti dicatat, laporan final ke user berisi angka pasti + commit hash Task 1 + konfirmasi hanya 2 file yang tersentuh.
