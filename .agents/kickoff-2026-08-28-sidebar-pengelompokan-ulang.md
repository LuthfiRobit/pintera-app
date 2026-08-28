# Kickoff Prompt — Pengelompokan Ulang Sidebar (Personal Context Precedence)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini — dikerjakan agent eksternal seperti Antigravity, bukan sesi Claude Code yang sama).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP untuk **restrukturisasi menu sidebar** app Laravel 12 multi-tenant "Pintera" (`pintera-app`). Ini BUKAN fitur baru dalam arti fungsional — ini perbaikan STRUKTUR NAVIGASI: item yang sebelumnya nyasar ke grup admin dipindah ke grup identitas personal pemiliknya, plus 2 grup baru dan satu perbaikan gate keamanan-navigasi yang tertinggal dari sesi kerja sebelumnya. Kamu tidak perlu brainstorming ulang, tidak perlu menulis spec baru — semua keputusan desain sudah final dan disetujui lewat proses review berlapis.

## Yang harus kamu baca dulu, URUTAN INI

1. `.agents/specs/2026-08-28-sidebar-pengelompokan-ulang.md` — spec lengkap. §1 (audit) dan §2.1 (prinsip presedensi) WAJIB dibaca sebelum menyentuh kode apa pun — tanpa memahami §2.1, kamu berisiko salah menerapkan aturan urutan identitas sebagai hierarki RBAC (BUKAN itu maksudnya, baca peringatan di bawah).
2. `.agents/plans/2026-08-28-sidebar-pengelompokan-ulang.md` — plan implementasi (7 task, kode lengkap, TDD step-by-step, semua ketidakpastian layout/namespace SUDAH diverifikasi ke kode sungguhan sebelum plan ditulis).

## Konteks penting — kenapa restrukturisasi ini ada

`resources/views/layouts/sidebar.blade.php` adalah SATU file dipakai SEMUA role (admin/yayasan/guru/siswa/orang_tua) lewat array `$navGroups` yang difilter `Auth::user()->can(...)` per item. Audit menemukan dua prinsip pengelompokan tercampur: sebagian grup berbasis AUDIENS ("Ruang Guru" = milik sendiri), sebagian berbasis DOMAIN ("Akademik" = kelola institusi). Akibatnya, item personal guru (RPP, QR Kehadiran, Izin/Cuti, Kasus Pendampingan) nyasar ke grup domain yang terasa seperti menu admin. Siswa dan Orang Tua bahkan tidak punya grup identitas sama sekali.

## PERINGATAN PALING KRITIS — "Personal Context Precedence" adalah aturan navigasi UI, BUKAN hierarki RBAC

Urutan cek identitas (`hasRole('guru')` → `hasRole('siswa')` → `orangTua !== null` → fallback `Kehadiran Saya`) HANYA menentukan **DI GRUP MANA** sebuah item personal ditampilkan. Urutan ini **TIDAK BOLEH** kamu tafsirkan sebagai:
- "guru lebih berwenang dari siswa/orang_tua" — SALAH, ini bukan otorisasi sama sekali.
- Alasan untuk menyentuh `scopeRank()`, `baselineCarrierRole()`, atau kode RBAC apa pun di `UserController.php` — **TIDAK ADA satu pun file RBAC yang disentuh plan ini.**
- Alasan untuk mengubah GUARD `can(...)` item mana pun — guard TIDAK BERUBAH untuk semua item yang dipindah, KECUALI satu (lihat peringatan kedua).

Kalau kamu menemukan dirimu ingin mengubah permission/role/gate APA PUN di luar satu pengecualian di bawah — STOP, itu tanda kamu salah paham scope. Plan ini murni pindah-lokasi item + 2 grup baru + 1 perbaikan guard spesifik.

## PERINGATAN KEDUA — SATU-SATUNYA perubahan guard yang diizinkan: Kasus Pendampingan

Item "Kasus Pendampingan" berubah dari `Auth::user()->can('kasus.view')` menjadi `Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class)` — DI SETIAP tempat item ini muncul (Ruang Guru, Ruang Siswa, Ruang Orang Tua, Kehadiran Saya/fallback). Ini menutup gap yang ditemukan sesi RBAC v2 sebelumnya: konselor pool karyawan yang cuma dapat akses lewat fakta domain (`konselor_karyawan_id`, TANPA `kasus.view` eksplisit) sebelumnya tidak melihat menu ini sama sekali walau halamannya bisa diakses langsung lewat URL.

**Kamu TIDAK PERLU dan TIDAK BOLEH mengubah `app/Domains/Kasus/Policies/KasusPolicy.php`** — method `viewAny()` SUDAH BENAR dari sesi sebelumnya (dibuktikan di spec §1.3: cabang `can('kasus.view')` di dalam `viewAny()` sudah `true` untuk baseline guru/siswa/orang_tua, jadi mengganti guard sidebar TIDAK MENGUBAH PERILAKU mereka sama sekali — HANYA menambah visibility untuk konselor pool). Kalau test kamu gagal dan solusinya "kelihatannya" perlu mengubah `KasusPolicy`, itu tanda ada yang salah di implementasimu, BUKAN alasan untuk mengubah policy.

## PERINGATAN KETIGA — Role assignment TIDAK SAMA dengan identitas profil

`guru_bk`/`wali_kelas` adalah role assignment-only (terbukti di sesi RBAC v2 sebelumnya: `grep hasRole('guru_bk')`/`grep hasRole('wali_kelas')` di seluruh `app/` = 0 hasil, keduanya TIDAK butuh profil `Guru` apa pun). Seorang karyawan yang diberi role `guru_bk` TANPA role `guru` **TETAP** jatuh ke fallback `Kehadiran Saya`, BUKAN `Ruang Guru`. Precedence poin pertama HARUS `Auth::user()->hasRole('guru')` — role literal, BUKAN permission `kasus.triase`/`kasus.view` yang kebetulan sama-sama dipegang `guru_bk`. Task 7 Step 2 di plan sudah menulis test eksplisit untuk ini — JANGAN dilewati atau dianggap kasus tepi yang tidak penting.

## PERINGATAN KEEMPAT — Siswa dan Orang Tua diarahkan ke halaman sama, TAPI alasannya BEDA, dan itu HARUS ditulis beda

3 item Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya): siswa NOL backend sama sekali — dikonfirmasi `route:list` penuh untuk kata kunci nilai/jadwal/rapor/presensi = nol route `siswa.*`.

3 item Orang Tua (Nilai Anak, Jadwal Anak, Riwayat Izin/Sakit Anak): `DashboardController::index()` baris 173-256 SUDAH punya query lengkap untuk ketiganya (`$nilaiTerbaru`, `$jadwalAnakHariIni`, `$riwayatIzinSakit`), cuma ditampilkan sebagai widget dashboard, belum ada halaman dedicated.

**Plan ini TIDAK membangun controller/halaman sungguhan untuk keduanya** — SEMUA 6 item mengarah ke SATU route generik `dalam-pengembangan` (Task 1). Jangan sampai kamu "membantu" dengan membangun halaman nyata untuk Orang Tua karena "kan datanya sudah ada" — itu SENGAJA ditunda ke siklus terpisah (dicatat di `PETA_PENGEMBANGAN.md`), BUKAN scope plan ini.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu nama route (`kasus.index`, `keuangan.dashboard`, `sdm.qr-saya`, dll) — pakai `php artisan route:list`, jangan menebak.
- Kalau ragu icon Lucide (`backpack`, `award`, `clipboard-check`, `calendar-clock`, `hammer`) tersedia — SEMUA sudah diverifikasi render lewat `Blade::render()` sebelum plan ditulis (tinker), tapi kalau ada error saat implementasi, cek ulang dengan cara yang sama sebelum mengganti nama icon.
- **JANGAN buat script verifikasi terpisah/tinker untuk hal yang test-nya sudah cukup** — test yang ditulis plan sudah cukup untuk membuktikan behavior.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/views.md` (Task 1, semua task sidebar: `resources/views/**`)
- `.ai/rules/routes.md` (Task 1: `routes/web.php`)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dengan instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru/worktree, kecuali user secara eksplisit minta branch terpisah untuk pekerjaan sidebar ini).
- **Radius perubahan produksi**: `resources/views/layouts/sidebar.blade.php` (Task 2-6), `routes/web.php` (+1 route, Task 1), `resources/views/shared/dalam-pengembangan.blade.php` (BARU, Task 1). TIDAK ADA controller/model/migration yang disentuh.
- **Urutan task LINEAR WAJIB**: Task 1 → 2 → 3 → 4 → 5 → 6 → 7. Task 3 dan 4 bergantung route dari Task 1. Task 5 dan 6 bergantung item yang sudah dipindah Task 2 (supaya guard anti-dobel yang ditambahkan match dengan apa yang sudah ada). Task 7 adalah checkpoint akhir + full suite — JANGAN dikerjakan sebelum Task 1-6 selesai semua.
- **Setiap task WAJIB dimulai dengan membaca ulang baris yang akan diedit** (Step 1 tiap task selalu "baca ulang baseline") — kalau baseline yang kamu baca TIDAK COCOK persis dengan kutipan di plan, STOP dan laporkan ke user, JANGAN melanjutkan edit di atas asumsi bahwa "mungkin sudah sedikit berbeda tapi maksudnya sama".
- **Task 3 memperkenalkan key `params` opsional** di struktur array `$item` (untuk item yang butuh query string, seperti `dalam-pengembangan?fitur=...`) — ini mengharuskan SATU baris render link di `sidebar.blade.php` diubah dari `route($item['route'])` jadi `route($item['route'], $item['params'] ?? [])`. JANGAN lewatkan perubahan baris render ini — kalau terlewat, 6 item baru (Task 3+4) akan mengarah ke URL tanpa query param `fitur`, halaman tetap render tapi judulnya selalu fallback "Fitur Ini" untuk semuanya (bug senyap, tidak akan terlihat lewat error).
- **Full suite HANYA di Task 7** — jangan jalankan di task lain, cukup test scoped (`--filter`) sesuai instruksi tiap task.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 → 5 → 6 → 7 murni LINEAR**, TIDAK BOLEH diacak atau dikerjakan paralel — setiap task mengedit bagian `$navGroups` yang berbeda di FILE YANG SAMA (`sidebar.blade.php`), mengerjakan di luar urutan berisiko konflik baseline (Step 1 tiap task membaca kutipan baris yang mengasumsikan task sebelumnya sudah selesai).

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini menengah (7 task, 3 file produksi disentuh berulang) — `subagent-driven-development` direkomendasikan karena tiap task independen diverifikasi test sebelum lanjut, dan Task 7 butuh review whole-branch sebelum full suite.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca ulang baseline SEBELUM setiap edit** (Step 1 tiap task) dan bandingkan dengan kutipan "baris X saat ini" — kalau berbeda, STOP.
3. Jalankan `php -l` pada file PHP/Blade yang diedit setelah tiap edit, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 7-8 commit total (1 per task, +1 opsional kalau Pint mereformat sesuatu di Task 7).
6. **Full suite HANYA di Task 7 Step 6** — jangan jalankan di task lain.

## Pelajaran penting dari sprint-sprint sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti).**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan overreach**: `guru_bk`/`wali_kelas`/8 role administratif lain TIDAK BOLEH ikut mendapat perlakuan khusus apa pun di luar yang eksplisit ditulis plan — sudah 2x sesi sebelumnya menemukan bug dari asumsi "role ini kedengarannya terkait, pasti perlu diperlakukan sama" yang ternyata salah.
6. **Kalau test Task 3/4 gagal karena escaping HTML (`&` jadi `&amp;`) tidak sesuai ekspektasi** — cek `$response->getContent()` dulu untuk lihat HTML sungguhan, JANGAN mengubah label di kode sidebar supaya "cocok" dengan assertion tebakan; assertion yang harus disesuaikan ke hasil render sungguhan, bukan sebaliknya.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`route:list`, `database-schema`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu. Task 7 Step 5 secara eksplisit meminta kamu grep test lain yang mungkin bersinggungan dengan label item yang dipindah — kalau ketemu file DI LUAR yang disebut plan, JANGAN diam-diam mengubahnya, laporkan dulu ke user.

## Definisi selesai

- Task 1: route `dalam-pengembangan` render halaman dengan judul dinamis dari query param `fitur`, guest diarahkan ke login.
- Task 2: RPP, QR Kehadiran, Izin/Cuti, Kasus Pendampingan muncul di `Ruang Guru` untuk akun guru.
- Task 3: grup `Ruang Siswa` muncul dengan 3 link "Dalam Pengembangan" + Kasus Pendampingan (kalau relevan) untuk akun siswa; render link mendukung key `params` opsional.
- Task 4: grup `Ruang Orang Tua` muncul dengan 3 link "Dalam Pengembangan" + 3 item keuangan (dipindah) + Kasus Pendampingan (kalau relevan); grup `Keuangan` (domain) tidak lagi mendefinisikan item self-service orang tua.
- Task 5: RPP TIDAK dobel untuk guru (muncul 1x, di `Ruang Guru`), TETAP muncul di `Akademik` untuk kepala_sekolah/wakasek_kurikulum/operator_akademik.
- Task 6: Kasus Pendampingan self-service pindah dari `Pendampingan` (domain) ke `Kehadiran Saya` (fallback) dengan guard `viewAny()`; QR/Izin tidak dobel untuk guru.
- Task 7: kombinasi guru+wakasek_kurikulum tidak kehilangan/menduplikasi item; `guru_bk` tanpa role `guru` TIDAK masuk `Ruang Guru`; **full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, laporan final ke user berisi angka pasti + commit hash (7-8 commit) + konfirmasi eksplisit semua poin di "Definisi selesai" Task 2-6.
