# Kickoff Prompt — Susutkan Seeder Demo ke 1 Lembaga (SD)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/skills/seeder-standard/SKILL.md` — standar konvensi seeder proyek ini (§5 baru: skala data demo minimal 1 lembaga).
2. `.agents/specs/2026-08-24-seeder-susutkan-1-lembaga-sd.md` — spec sub-project ini (§3 kategorisasi 27 file per tingkat risiko, §4 target volume).
3. `.agents/plans/2026-08-24-seeder-susutkan-1-lembaga-sd.md` — plan implementasi (20 task, kode lengkap per task).

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree). Branch ini sebelumnya dipakai untuk spec RBAC v2 (belum dieksekusi, TIDAK terkait sub-project ini — JANGAN sentuh `RoleSeeder.php`/`RolePermissionAssignmentSeeder.php`, itu di luar scope).
- **Baseline kode plan ini: commit `06f7d8b`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Kenapa sub-project ini ada**: user ingin menyusutkan `DatabaseSeeder.php` supaya hanya membuat 1 lembaga (SD, bukan 4 lembaga KB/TK/SD/SMP seperti sekarang), dengan syarat eksplisit "data harus seperti data real" — bukan sekadar hapus 3 lembaga lalu biarkan data SD tetap token/minim. Audit menemukan 5 file akan CRASH TOTAL kalau lembaga lain dihapus tanpa perbaikan, 3 file lain diam-diam menghasilkan NOL baris untuk SD.
- **Data buang-pakai** — `migrate:fresh --seed` adalah cara verifikasi NORMAL di proyek ini, BUKAN operasi berisiko yang perlu dihindari. Jalankan sesering perlu untuk verifikasi tiap task, TAPI JANGAN jalankan proses test (`php artisan test`) bersamaan dengan proses lain yang mengakses database yang sama.

## Urutan eksekusi — SANGAT PENTING, jangan diacak

Task 1-9 punya dependency ketat (LembagaSeeder dulu → 5 file crash-risk → checkpoint). Task 10-14 dan 15-19 lebih independen tapi tetap ikuti urutan nomor di plan (beberapa saling mereferensikan hasil task sebelumnya, misal Task 15-18 semua butuh Task 4/5/6/16 selesai duluan).

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/seeder-susutkan-sd/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 20):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final).
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit — pastikan cocok dengan yang dikutip plan (banyak task pakai `wc -l`/`cat` untuk konfirmasi baseline sebelum edit).
3. Jalankan `php -l <file>` (syntax check) setelah tiap edit, SEBELUM commit.
4. Satu commit per task (kecuali Task 2+3 yang sengaja digabung 1 commit karena saling bergantung — sudah dicatat eksplisit di plan).
5. **Task 9 adalah gate WAJIB** — `migrate:fresh --seed` harus 0 exception SEBELUM lanjut Task 10. Kalau ada exception, STOP, jangan lanjut, perbaiki dulu.
6. Jangan jalankan full test suite sampai Task 20.
7. Task 20 Step 5 butuh persetujuan user EKSPLISIT sebelum full suite (Step 6).

## Detail yang sering jadi jebakan (baca dua kali)

1. **Task 15-18 (Asesmen/Jadwal/Sesi/Nilai) butuh KONFIRMASI nama mapel IPAS SD yang PERSIS** dari `MataPelajaranSeeder.php` sebelum menulis kode — plan menyebut tebakan nama `'Ilmu Pengetahuan Alam dan Sosial (IPAS)'` tapi itu HARUS diverifikasi dulu (Task 15 Step 1 sudah eksplisit minta ini), JANGAN asumsikan tebakan itu benar tanpa cek.
2. **Kode `KomponenPenilaian` (TP.1.1, TP.1.2, dst) untuk mapel SD kemungkinan BEDA dari kode SMP** — plan sudah memperingatkan ini di Task 15 Step 3, WAJIB dicek ulang, bukan disalin mentah dari kode SMP.
3. **Task 12 (`SarprasPengadaanDemoSeeder.php`)** — ada BANYAK string "VII-A"/"VII-B"/"kelas VII" tersebar di deskripsi/spesifikasi aset (bukan cuma di 2 tempat yang dikutip plan) — Step 5 minta grep ulang, JANGAN cuma ganti yang dikutip literal di plan, cari SEMUA kemunculan string itu di file tersebut.
4. **Task 5 (`SiswaSeeder.php`)** pakai kombinasi nama depan×belakang programatik (30×16 kombinasi) — BUKAN 336 baris nama literal. Ini SENGAJA, jangan diubah jadi array literal manual (itu justru kerja sia-sia dan rentan salah ketik).
5. **Task 19 (`PresensiSeeder.php`)** pakai formula modulo deterministik untuk variasi status, BUKAN `rand()`/`fake()` — WAJIB tetap deterministik supaya `migrate:fresh --seed` berulang menghasilkan data identik (idempotent by design, meski technically bukan idempotent di level row karena `firstOrCreate` — poinnya konsistensi ANTAR-RUN, bukan literal idempotency check).

## Pelajaran penting dari sub-project sebelumnya di repo ini

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata** (`php -l`, `migrate:fresh --seed`, `tinker`).
2. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri.
3. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan** — bisa tabrakan akses database.
4. **Bahaya cari-ganti otomatis (`sed`) untuk file dengan banyak variasi string** (terutama Task 12 Sarpras) — edit manual per-kemunculan, verifikasi dengan grep SEBELUM dan SESUDAH.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

Task 1-9 selesai: `migrate:fresh --seed` sukses 0 exception, `Lembaga::count() === 1`. Task 10-14 selesai: tidak ada lagi silent-skip (grep warning log kosong dari indikasi skip), tidak ada label email salah (`*.kb@demo.test` dkk). Task 15-19 selesai: Kelas 1-A/1-B dapat detail treatment (asesmen/jadwal/sesi/nilai bervariasi, bukan fallback generik flat), Presensi punya variasi status. Task 20 selesai: grep gabungan NPSN lama KOSONG total, volume data sesuai target §4 spec (dicatat angka pasti), full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (test yang gagal karena bergantung ke seeder lama — kalau ada — dilaporkan eksplisit, bukan diperbaiki diam-diam di luar scope), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri.
