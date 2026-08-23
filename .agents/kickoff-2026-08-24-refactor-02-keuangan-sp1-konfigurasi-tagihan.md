# Kickoff Prompt — Migrasi Domain Keuangan Sub-project 1: Konfigurasi & Generasi Tagihan

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/skills/laravel-feature-standard/SKILL.md` — standar arsitektur mengikat proyek ini (struktur `Domains/`, Action/DTO/Model, thin controller, dst).
2. `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` — roadmap induk. §3.1 (kriteria blast-radius per-model), §3.2 (kenapa model "Data Induk" umumnya TIDAK jadi domain sendiri, tapi model sempit di dalamnya BISA), §3.3 (konvensi controller & view, BAHAYA sed/cari-ganti blanket yang pernah merusak 5 file Blade di migrasi sebelumnya) WAJIB dipahami persis.
3. `.agents/specs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` — spec item ini (kenapa & apa: latar belakang skala nyata modul Keuangan 13 controller/1892 baris/17 model, keputusan pemecahan jadi 4 sub-project, cakupan 8 model + 1 controller + 2 service sub-project ini, semua keputusan brainstorming didokumentasikan).
4. `.agents/plans/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` — plan implementasi (13 task, kode lengkap per task, daftar file consumer hasil grep nyata per model, tabel gotcha referensi implisit, langkah verifikasi eksplisit).

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `refactor-v1` (SUDAH ADA, sudah dipakai untuk sub-project "Data Induk Sempit" sebelumnya di branch yang sama — jangan buat branch baru, jangan buat worktree).
- Modul Keuangan menyangkut UANG SUNGGUHAN (billing, pembayaran, cicilan) — tapi sub-project INI HANYA menyentuh model KONFIGURASI (jenis tagihan, nominal per jalur/siswa, aturan diskon/sasaran, skema cicilan header) — BELUM menyentuh alur pembayaran/transaksi uang bergerak. Risiko rendah, tapi tetap ikuti disiplin zero-behavior-change persis.
- **Ini adalah Sub-project 1 dari 4 sub-project migrasi domain Keuangan.** Sub-project 2 (Alur Tagihan Inti + Portal Tampilan), 3 (Pembayaran & Gateway, TERMASUK webhook BRI SNAP), 4 (Wallet & Cicilan + Rekonsiliasi) BELUM ditulis plan-nya — JANGAN sentuh model/controller/service yang jelas-jelas wilayah sub-project itu (`Tagihan`, `JenisTagihanMonitoringController`, `TagihanBillingGenerator`, `TagihanGenerator`, `Pembayaran`, `Wallet`, `Cicilan`, webhook BRI) — lihat §4 dan §9 spec untuk daftar lengkap yang DITUNDA.
- **Preseden yang WAJIB dicontek persis:** Sub-project "Data Induk Sempit" (`.agents/plans/2026-08-23-refactor-01-data-induk-sempit.md`) — pola teknisnya (git mv, namespace, update consumer via grep, FQCN inline untuk relasi lintas-domain/lintas-file-tetap, `newFactory()` selektif per model) SAMA PERSIS dipakai di plan ini, hanya skalanya lebih besar (8 model + 1 controller besar + 2 service, bukan 3 model kecil).
- **4 gotcha referensi implisit same-namespace SUDAH ditemukan dan didokumentasikan eksplisit di plan** (Task 1 Step 5-6, Task 4 Step 4, Task 8 Step 5): `BillingJobLog.php`→`JenisTagihan`, `Tagihan.php`→`JenisTagihan`+`TagihanItem`+`SkemaCicilan` (3 referensi dalam 1 file, DIPERBAIKI SEKALIGUS di Task 1 Step 6 meski `TagihanItem`/`SkemaCicilan` baru benar-benar pindah di Task 7/8 — ini SENGAJA, baca catatan "PENTING" di Task 1 Step 6 kenapa ini aman), `KategoriKeringanan.php`→`JenisTagihanKeringanan`, `Cicilan.php`→`SkemaCicilan`. Keempat file ini TETAP di `app/Models/` (tidak dimigrasi), tapi referensi implisitnya ke model yang pindah WAJIB jadi FQCN inline.
- **Referensi implisit ANTAR model yang SAMA-SAMA pindah ke `Domains\Keuangan\Models` di sub-project ini (mis. relasi `JenisTagihan::keringananRules()` → `JenisTagihanKeringanan`) TIDAK PERLU diubah jadi FQCN** — begitu kedua file sama-sama pindah namespace, referensi implisit tetap resolve benar karena berbagi namespace baru yang sama. Plan sudah membedakan ini eksplisit — jangan blanket-convert semua ke FQCN, itu kerja tak perlu.
- **`JenisTagihan::booted()` yang men-dispatch event `BillTypeActivated` DIHAPUS dari model di Task 1**, logic yang sama persis DIPINDAH ke `UpdateJenisTagihanAction` di Task 10/12 — sudah diverifikasi hanya 1 call site nyata (`JenisTagihanController::update()`), `store()`/`create()` tidak pernah memicu event `updated`. Kalau kamu menjalankan Task 1 tanpa segera lanjut ke Task 10/12, event dispatch akan HILANG dari aplikasi untuk sementara (window antar-task) — INI TIDAK APA-APA selama kamu mengeksekusi task berurutan tanpa deploy parsial di antaranya, TAPI jangan lewati Task 10/12 atau anggap Task 1 "sudah final" tanpa Task 10/12.
- **`newFactory()` HANYA untuk 4 model**: `JenisTagihan`, `NominalTagihanJalur`, `TagihanItem`, `SkemaCicilan` (pakai `HasFactory`). **4 model lain TIDAK ditambahkan** `HasFactory`/`newFactory()`: `NominalTagihanSiswa`, `JenisTagihanKeringanan`, `JenisTagihanSasaranGrup`, `JenisTagihanSasaranKriteria` — plan sudah eksplisit menandai ini per-task, jangan "koreksi" jadi seragam.
- **Namespace controller pindah ke `App\Http\Controllers\Lembaga\Keuangan\JenisTagihanController`** (Task 12) — controller lama di `Admin\JenisTagihanController` DIHAPUS TOTAL, bukan dibiarkan sebagai alias/wrapper.
- **Route NAME dan PATH tidak berubah sama sekali** di seluruh plan ini — cuma `use` statement controller di `routes/admin/keuangan.php` yang diganti (Task 12 Step 6). Kalau kamu menemukan diri ingin mengubah nama route, STOP, itu di luar plan.
- **`TagihanController.php` (Admin, milik Sub-project 2) BUKAN bagian migrasi ini, TAPI `use` statement-nya untuk `SkemaCicilan` WAJIB diupdate di Task 8** karena model itu pindah namespace — ini murni ganti 1 baris import, JANGAN sentuh method/logic apapun di file itu.
- **2 service (`JenisTagihanSasaranMatcher`, `TagihanNominalResolver`) pindah ke `Domains\Keuangan\Services\` di Task 9** meski satu-satunya pemanggil nyata (`TagihanBillingGenerator`) adalah wilayah Sub-project 2 — kepemilikan domain ditentukan SUBJEK DATA (model yang diquery), bukan siapa pemanggilnya. Ini keputusan sadar user, bukan salah alamat.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/refactor-keuangan-sp1/progress.md`.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 13, urutannya penting):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Untuk task yang MEMODIFIKASI file existing (terutama Task 1, 4, 8, 9, 12) — WAJIB baca isi file itu dulu sebelum mengedit, pastikan potongan kode yang dikutip plan cocok dengan isi file saat ini. Task 12 secara eksplisit mengutip seluruh baseline controller (445 baris) untuk kamu bandingkan — kalau isi berbeda signifikan, STOP dan laporkan, jangan menebak.
3. Untuk daftar "update file consumer" (Task 1-9, tiap model/service) — WAJIB grep ulang untuk konfirmasi daftar masih akurat SEBELUM mulai edit massal (`grep -rln "use App\\Models\\<Model>;" --include="*.php" app database tests`) — kalau ada file baru di luar yang disebut plan (karena waktu berlalu sejak spec/plan ditulis), tambahkan ke proses edit, JANGAN lewati.
4. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu. Task 1-7 test scoped-nya minimal (class-load check saja, karena model saling bergantung sampai Task 8 selesai) — test fungsional penuh baru masuk akal di Task 8 Step 7 (setelah ke-8 model pindah semua).
5. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task step terakhir.
6. Jangan jalankan full test suite sampai Task 13.
7. Task 13 Step 3 butuh persetujuan user secara EKSPLISIT sebelum menjalankan full suite (Step 4) — TANYA dulu, jangan otomatis jalan.
8. Task 11 murni gate verifikasi (tidak ada commit) — kalau ada temuan yang tidak sesuai, STOP dan perbaiki sebelum lanjut ke Task 12, jangan lanjut sambil "nanti diperbaiki belakangan".

## Pelajaran penting dari review pekerjaan sebelumnya di repo ini (WAJIB diperhatikan)

1. **Jangan tandai step/task selesai kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti, atau grep dengan hasil kosong) — bukan asumsi dari membaca kode.
2. **Verifikasi grep WAJIB menyisir `app database tests`, bukan cuma `app/Models`.** Di migrasi Data Induk Sempit sebelumnya, grep verifikasi sempat dibatasi ke `app/Models` saja — ini membuat 5 file test lolos tak terdeteksi (masih memakai `use App\Models\{Model}` lama) dan baru ketahuan ~38 menit / 6 commit kemudian lewat sweep test luas. Plan ini SUDAH memperbaiki semua perintah grep-nya dengan scope yang benar (`app database tests`) — JANGAN persempit sendiri scope grep-nya demi "lebih cepat".
3. **Bahaya cari-ganti blanket (§3.3 roadmap induk) PERNAH terjadi nyata**: migrasi 9 view Akademik sebelumnya rusak karena `sed` tidak dibatasi hanya ke baris `view(`/`@include(`/`route(` — merusak `route()` di 5 file. Task 12 Step 5 plan ini SUDAH memperingatkan ini eksplisit — ikuti instruksinya persis (cuma ganti 2 baris `@include` yang sudah disebutkan, JANGAN sentuh `route()` di file manapun).
4. **Kalau kamu terpaksa memperbaiki sesuatu di luar daftar file yang disebut plan (mis. menemukan file consumer baru lewat grep ulang, atau bug kecil yang kepergok saat baca kode) — WAJIB laporkan eksplisit, JANGAN diam-diam dimasukkan ke commit tanpa disebut.** Insiden sebelumnya di project ini: 2 perbaikan tak terduga (test FQCN yang salah) masuk lewat commit yang tidak disebut terpisah di handoff log — meski perbaikannya sendiri benar, ketiadaan disclosure jadi temuan review. Kalau kamu menemukan hal serupa, tulis sebagai baris terpisah eksplisit di handoff log (Task 13 Step 5), bukan disembunyikan di 1 baris netral tabel commit.
5. **Kalau full suite/test lain menunjukkan kegagalan yang TIDAK terkait sama sekali** (mis. flaky hari-Minggu terkait hari libur mingguan SDM), jangan langsung anggap itu masalah dari pekerjaanmu — jalankan ulang test itu SENDIRIAN dulu untuk konfirmasi, dan sebutkan angka gagalnya secara eksplisit di handoff log kalau memang terjadi (jangan disembunyikan di headline "semua passed").

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `ed25f74` di branch `refactor-v1`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda baris kecil), atau daftar file consumer hasil grep di plan ternyata sudah tidak akurat (grep ulang menunjukkan hasil berbeda) — STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan, lalu sesuaikan berdasarkan grep BARU (bukan asal tambah tanpa verifikasi).

## Setelah kamu selesai

Task 13 Step 5-7 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` (ringkasan per task 1-12, commit hash, hasil test dengan angka pasti dari 2 command berbeda — scoped gabungan Task 8/13 Step 1 dan full suite Task 13 Step 4 — jangan disatukan/dicampur), dan update tabel Sub-Task di roadmap induk (§6 `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md`). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan cross-check klaim di handoff log terhadap kode sungguhan) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history), dan jangan menulis klaim di handoff log yang tidak sesuai kode yang sebenarnya kamu tulis.

## Definisi selesai

Task 1-9 selesai: seluruh 8 model + 2 service sudah di `Domains\Keuangan\Models`/`Services`, `grep -rln "use App\\Models\\JenisTagihan;\|use App\\Models\\NominalTagihanJalur;\|use App\\Models\\NominalTagihanSiswa;\|use App\\Models\\JenisTagihanKeringanan;\|use App\\Models\\JenisTagihanSasaranGrup;\|use App\\Models\\JenisTagihanSasaranKriteria;\|use App\\Models\\TagihanItem;\|use App\\Models\\SkemaCicilan;\|use App\\Services\\JenisTagihanSasaranMatcher;\|use App\\Services\\TagihanNominalResolver;" --include="*.php" app database tests` KOSONG total, ke-8 file model lama sudah tidak ada di `app/Models/`, test scoped gabungan Task 8 Step 7 hijau.

Task 10-12 selesai: DTO + 6 Action baru ada di `app/Domains/Keuangan/Actions/JenisTagihan/` dan `app/Domains/Keuangan/DataTransferObjects/`, controller baru ada di `Lembaga\Keuangan\JenisTagihanController`, controller lama sudah dihapus, 5 view sudah pindah ke `resources/views/portals/lembaga/keuangan/jenis-tagihan/`, `php artisan route:list --name=jenis-tagihan` menunjukkan route name sama persis seperti sebelum migrasi, test scoped Task 12 Step 9 hijau.

Task 13 selesai: full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (kecuali flaky yang sudah dikenal dan dikonfirmasi ulang sendirian), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri (bukan klaim tanpa command), roadmap induk sudah diupdate.
