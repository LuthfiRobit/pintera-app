# Kickoff Prompt — Migrasi Domain Keuangan Sub-project 5 (Mini, Penutup Celah): Kategori & Siswa Keringanan

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

**Ini sub-project KECIL** — 3 file (2 model + 1 controller), penutup celah kecil yang lolos dari audit final Sub-project 4 (yang menutup migrasi domain Keuangan yang lebih besar). Meski kecil, ikuti disiplin yang sama seperti sub-project sebelumnya (grep ulang, verifikasi sebelum commit, test sebelum commit).

## Yang harus kamu baca dulu

1. `.agents/skills/laravel-feature-standard/SKILL.md` — standar arsitektur mengikat proyek ini.
2. `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` — roadmap induk.
3. `.agents/specs/2026-08-24-refactor-02-keuangan-sp5-kategori-keringanan.md` — spec sub-project ini (§3 bukti keterikatan domain, §4 keputusan desain).
4. `.agents/plans/2026-08-24-refactor-02-keuangan-sp5-kategori-keringanan.md` — plan implementasi (3 task, kode lengkap per task).

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `refactor-v1` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `032200b`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Kenapa sub-project ini ada**: migrasi domain Keuangan (4 sub-project, SP1-4) sudah dinyatakan selesai dan direview independen bersih. Tapi audit final Task 12 SP4 punya blind spot — command grep-nya hanya mencari pola `wallet|cicilan|tagihan|pembayaran|bri`, tidak menangkap kata "keringanan" (diskon/potongan biaya). 2 model (`KategoriKeringanan`, `SiswaKeringanan`) dan 1 controller (`KategoriKeringananController`) yang murni milik Keuangan lolos tanpa termigrasi. SP5 ini menutup celah itu.
- **Zero-behavior-change TOTAL** — tidak ada bug fix disengaja di sub-project ini (beda dari SP4 yang punya 1 pengecualian `WalletMutasi`). Murni pemindahan namespace.
- **Route TIDAK berubah** — nama route `admin.kategori-keringanan.store` dan path tetap sama persis, cuma `use` statement di `routes/admin/keuangan.php` yang diupdate menunjuk controller baru.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/refactor-keuangan-sp5/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 3, Task 2 butuh Task 1 selesai karena controller baru mengonsumsi model yang dipindah Task 1):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final).
2. WAJIB baca isi file existing dulu sebelum mengedit apapun, pastikan cocok dengan yang dikutip plan.
3. Grep ulang untuk konfirmasi daftar consumer masih akurat, scope `app database tests`.
4. Jalankan verifikasi (test scoped) SEBELUM commit — kalau ada yang gagal, JANGAN commit.
5. Satu commit per task.
6. Jangan jalankan full test suite sampai Task 3.
7. Task 3 Step 4 butuh persetujuan user EKSPLISIT sebelum full suite (Step 5).

## Pelajaran penting dari sub-project sebelumnya di repo ini

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **Kalau kamu memutuskan menyimpang dari keputusan arsitektur eksplisit di plan — STOP dan laporkan ke user, JANGAN diam-diam menulis ulang keputusan itu di handoff log seolah bersih dari awal.** Ini pola kesalahan yang berulang di SP1/SP2, selalu baru ketahuan lewat review manual — jangan sampai terulang.
3. **Verifikasi grep WAJIB scope `app database tests`, bukan cuma `app/Models`.** Ini KHUSUSNYA relevan di sub-project ini — SP5 ada karena audit sebelumnya pakai pola grep yang tidak cukup luas (cuma cari kata kunci tertentu, bukan menyisir SEMUA nama model). Kalau kamu menemukan file lain yang juga lolos dari audit (pola serupa: model Keuangan-spesifik yang masih di `App\Models`), LAPORKAN ke user, jangan langsung migrasi sendiri di luar scope plan ini.
4. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan** — merusak MySQL test DB bersama.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

Task 1-2 selesai: `KategoriKeringanan`, `SiswaKeringanan` di `Domains\Keuangan\Models`; `KategoriKeringananController` di `Lembaga\Keuangan`; grep gabungan Task 3 Step 1 KOSONG total; route `admin.kategori-keringanan.store` tetap ada dengan path sama. Task 3 selesai: full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (kecuali flaky yang sudah dikenal dan dicatat eksplisit, bukan diam-diam diabaikan), handoff log tertulis, roadmap induk diupdate.
