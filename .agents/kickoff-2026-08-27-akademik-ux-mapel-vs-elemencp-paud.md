# Kickoff Prompt — UX Mata Pelajaran vs ElemenCp PAUD (Priority 7)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah direview mendalam (brainstorming interaktif + review user + verifikasi kode langsung). Kamu tidak perlu brainstorming ulang, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan. Ini plan KECIL (2 task, murni UX polish) — jangan perlakukan sebagai proyek besar.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-ux-mapel-vs-elemencp-paud.md` — spec lengkap. §2 WAJIB dibaca — wording banner sudah final persis kata-katanya, jangan diparafrase.
2. `.agents/plans/2026-08-27-akademik-ux-mapel-vs-elemencp-paud.md` — plan implementasi (2 task, kode lengkap, TDD step-by-step).

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

Project ini punya Laravel Boost MCP server terpasang. Ikuti aturan project (`CLAUDE.md` root):

- Kalau kamu ragu soal route apa pun (mis. nama route Komponen Penilaian) — pakai `php artisan route:list`, bukan menebak (sudah diverifikasi di spec, seharusnya tidak perlu cek ulang).
- **JANGAN buat script verifikasi terpisah/tinker** — plan ini murni UI+test, test yang ditulis plan sudah cukup membuktikan fungsionalitasnya.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/controllers.md` (Task 1: modifikasi `MataPelajaranController`)
- `.ai/rules/views.md` (Task 1: modifikasi `index.blade.php`)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`), Pest v4, MySQL 8.0.30. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Ini murni UX polish, TIDAK ADA logic baru** — jangan tergoda menambah fitur lain "selagi di sini" (mis. badge di sidebar menu, notifikasi, dsb). Scope-nya PERSIS: 1 banner kondisional di 1 halaman.
- **Wording banner FINAL, jangan diparafrase**: judul persis `"Catatan untuk PAUD"`, isi harus menyebut kata `"Elemen CP"` persis (bukan "ElemenCp"/"elemen capaian" saja), link persis `"Kelola Komponen Penilaian"`. Test di plan mengecek string ini persis — parafrase akan bikin test gagal.
- **`bentuk_pendidikan` di `Lembaga` TIDAK di-cast enum** (sudah diverifikasi eksplisit di spec §3, dikutip dari `Lembaga::casts()`) — kode di plan membandingkan `BentukPendidikan::Kb->value` dkk (string), BUKAN instance enum. JANGAN "perbaiki" ini jadi perbandingan enum instance — itu justru salah, sudah dicek user & diverifikasi kode aslinya.
- **JANGAN buat method baru di enum `BentukPendidikan`** (mis. `isPaud()`) — keputusan eksplisit user: 4 nilai literal inline di controller sudah cukup jelas tanpa abstraksi tambahan, membuat helper baru untuk 1 pemakaian adalah over-engineering.

## Urutan eksekusi

**Task 1 → 2 murni LINEAR.**

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini SANGAT kecil (2 task, 3 file tersentuh) — cukup eksekusi manual langsung (`superpowers:executing-plans`), TIDAK PERLU `subagent-driven-development` utk plan sekecil ini.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`MataPelajaranController.php` baris 1-68, `index.blade.php` baris 1-22) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 2 commit total (satu per task, pesan commit sudah ditulis di tiap Step terakhir).

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 4** — banner ditambahkan TEPAT SETELAH blok "Header & Breadcrumb", SEBELUM blok "KPI Compact Horizontal Statistic Cards" — bukan di posisi lain. Baca dulu baris 1-22 file itu penuh sebelum edit utk konfirmasi baris ini masih akurat.
2. **Task 1 Step 1** — test dataset `->with(['KB', 'TPA', 'SPS', 'TK'])` WAJIB ada (4 kasus, bukan cuma `TK`) — plan sengaja begini krn `in_array` di controller harus teruji utk semua 4 nilai.

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test --compact` TANPA filter apa pun di Task 2 utk klaim "full suite hijau".
3. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
4. **Jangan menambah scope di luar 2 task ini.**

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: banner "Catatan untuk PAUD" tampil di halaman Mata Pelajaran utk `KB`/`TPA`/`SPS`/`TK`, TIDAK tampil utk jenjang lain, link ke `admin.komponen-penilaian.index` berfungsi — 5 test PASS (4 dataset PAUD + 1 non-PAUD).
- Task 2: **full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, `PETA_PENGEMBANGAN.md` sudah ditandai Prioritas #7 SELESAI (dan mencatat seluruh 7 prioritas roadmap tuntas ditangani), laporan final ke user berisi angka pasti + 2 commit hash.
