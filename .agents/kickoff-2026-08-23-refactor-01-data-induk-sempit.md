# Kickoff Prompt — Serap Model Data Induk Sempit ke Domain Pemiliknya

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/skills/laravel-feature-standard/SKILL.md` — standar arsitektur mengikat proyek ini (struktur `Domains/`, Action/DTO/Model, thin controller, Blade component, dst).
2. `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` — roadmap induk. §3.1 (kriteria blast-radius per-model) dan §3.3 (konvensi controller & view, BAHAYA sed/cari-ganti blanket yang pernah merusak 5 file Blade di migrasi sebelumnya) WAJIB dipahami persis.
3. `.agents/specs/2026-08-23-refactor-01-data-induk-sempit.md` — spec item ini (kenapa & apa: 3 model dipindah, semua keputusan brainstorming didokumentasikan).
4. `.agents/plans/2026-08-23-refactor-01-data-induk-sempit.md` — plan implementasi (8 task, kode lengkap per task, daftar file consumer hasil grep nyata, langkah verifikasi eksplisit).

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `refactor-v1` (SUDAH ADA, dibuat sejajar `sdm-v1`, jangan buat branch baru, jangan buat worktree).
- Ini murni kerapian arsitektur (prioritas Rendah di roadmap produk) — BUKAN prasyarat fitur bisnis apapun. Jangan campur dengan pekerjaan lain.
- **Preseden yang WAJIB dicontek persis:** migrasi `PolaJam`/`JamPelajaran` ke `Domains\Akademik` (Sub-Task 05, `.agents/plans/2026-08-20-akademik-05-migrasi-domain-tersisa.md` Task 4-5) — pola teknisnya (git mv, namespace, update consumer, FQCN inline untuk relasi lintas-domain, `newFactory()`) SAMA PERSIS dipakai di plan ini.
- **3 gotcha referensi implisit same-namespace SUDAH ditemukan dan didokumentasikan eksplisit di plan** (Task 1 Step 5, Task 2 Step 4, Task 3 Step 5): `Karyawan.php`→`JenisKaryawanMaster`, `Guru.php`→`JabatanTambahanMaster`, `JadwalPelajaran.php`→`MataPelajaran`. Ini relasi PALING PENTING di ketiga model (parent-child utama) dan TIDAK muncul di grep `use` biasa — kalau kamu skip step ini, aplikasi akan error "Class not found" begitu model pindah, TAPI test yang menjalankan lewat instansiasi `Karyawan`/`Guru`/`JadwalPelajaran` baru akan menunjukkannya, bukan langsung ketahuan dari grep.
- **`JabatanTambahanMaster` TIDAK pakai `HasFactory`** — jangan ditambahkan (§3.6 spec, zero-behavior-change). Dua model lain (`JenisKaryawanMaster`, `MataPelajaran`) WAJIB dapat `newFactory()`.
- **Namespace controller SENGAJA menyimpang dari preseden PolaJam** — PolaJam mempertahankan controller di `Admin\`, tapi plan ini memindahkan ke `Lembaga\Sdm\`/`Lembaga\Akademik\` atas keputusan sadar user (demi konsistensi pola jangka panjang). Jangan "koreksi" ini supaya sama dengan preseden — itu bukan kesalahan, itu keputusan.
- **Route NAME dan PATH tidak berubah sama sekali** di seluruh plan ini — cuma `use` statement controller di file route yang diganti. Kalau kamu menemukan diri ingin mengubah nama route, STOP, itu di luar plan.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/refactor-data-induk/progress.md`.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 8, urutannya penting — Task 4-6 masing-masing bergantung pada model yang sudah dipindah di Task 1-3):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Untuk task yang MEMODIFIKASI file existing (Task 1-6 semua) — WAJIB baca isi file itu dulu sebelum mengedit, pastikan potongan kode yang dikutip plan cocok dengan isi file saat ini. Task 4-6 secara eksplisit mengutip seluruh baseline controller untuk kamu bandingkan.
3. Untuk daftar "update N file consumer" (Task 1/2/3) — WAJIB grep ulang untuk konfirmasi daftar di plan masih akurat SEBELUM mulai edit massal (`grep -rln "use App\\Models\\<Model>;" --include="*.php" app database tests`) — kalau ada file baru di luar daftar plan (karena waktu berlalu sejak spec ditulis), tambahkan ke proses edit, JANGAN lewati.
4. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
5. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task Step terakhir.
6. Jangan jalankan full test suite sampai Task 8.
7. Task 8 Step 5 butuh persetujuan user secara EKSPLISIT sebelum menjalankan full suite (Step 6) — TANYA dulu, jangan otomatis jalan.

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan)

1. **Jangan tandai step/task selesai kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti) — bukan asumsi dari membaca kode.
2. **Bahaya cari-ganti blanket (§3.3 roadmap induk) PERNAH terjadi nyata**: migrasi 9 view Akademik sebelumnya rusak karena `sed` tidak dibatasi hanya ke baris `view(`/`@include(`/`route(` — merusak `route()` di 5 file. Task 4/5/6 Step "Pindahkan view" plan ini SUDAH memperingatkan ini eksplisit — ikuti instruksinya persis (cuma ganti `@include` path, JANGAN sentuh `route()`).
3. **Handoff log sebelumnya di project ini pernah menyentuh file di luar scope tanpa disebut di ringkasan, dan pernah melaporkan angka test yang tidak rekonsiliasi dengan tabel di baris di atasnya sendiri** — ketahuan lewat review manual terpisah. Untuk item ini scope-nya jelas (8 task eksplisit) — kalau kamu merasa PERLU menyentuh file di luar yang disebut, STOP dan laporkan, jangan diam-diam. Saat menulis handoff log, pastikan angka agregat benar-benar dijumlah dari angka per-task yang kamu laporkan sendiri, jangan tempel angka dari run lain.
4. **Kalau full suite/test lain menunjukkan kegagalan yang TIDAK terkait sama sekali** (mis. flaky hari-Minggu terkait hari libur mingguan SDM), jangan langsung anggap itu masalah dari pekerjaanmu — jalankan ulang test itu SENDIRIAN dulu untuk konfirmasi, dan sebutkan angka gagalnya secara eksplisit di handoff log kalau memang terjadi (jangan disembunyikan di headline "semua passed").

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `31a03ab` di branch `refactor-v1`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda baris), atau daftar file consumer di §4 spec ternyata sudah tidak akurat (grep ulang menunjukkan hasil berbeda) — STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan, lalu sesuaikan daftar berdasarkan grep BARU (bukan asal tambah tanpa verifikasi).

## Setelah kamu selesai

Task 8 Step 7-8 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-23-refactor-01-data-induk-sempit.md` (ringkasan per task, commit hash, hasil test dengan angka pasti dari 2 command berbeda — scoped gabungan dan full suite — jangan disatukan/dicampur). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan cross-check klaim di handoff log terhadap kode sungguhan) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history), dan jangan menulis klaim di handoff log yang tidak sesuai kode yang sebenarnya kamu tulis.

## Definisi selesai

Task 1-7 selesai: seluruh test scoped tiap task hijau, `grep -rln "use App\\Models\\JenisKaryawanMaster;\|use App\\Models\\JabatanTambahanMaster;\|use App\\Models\\MataPelajaran;" --include="*.php" app database tests` KOSONG total, `grep -rn "JenisKaryawanMaster::class\|JabatanTambahanMaster::class\|MataPelajaran::class" --include="*.php" app/Models` KOSONG total, ketiga file model lama sudah tidak ada di `app/Models/`, `php artisan route:list` menunjukkan route name sama persis seperti sebelum migrasi untuk ketiga fitur. Task 8 selesai: full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (kecuali flaky yang sudah dikenal dan dikonfirmasi ulang sendirian), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri (bukan klaim tanpa command).
