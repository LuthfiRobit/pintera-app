# Kickoff Prompt — Penyempurnaan Data 7 Dashboard Multi-Role

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-25-redesain-dashboard-multi-role.md` — spec lengkap (§2 koreksi penting dari draft awal yang ditolak, §3 struktur data terverifikasi, §4 cakupan final per dashboard termasuk non-goals eksplisit).
2. `.agents/plans/2026-08-25-redesain-dashboard-multi-role.md` — plan implementasi (10 task, kode lengkap per task).

## Konteks singkat

- Repo Laravel 12 multi-tenant (`pintera-app`). Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Baseline kode plan ini: commit `afbed1f`.** Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **Kenapa sub-project ini ada**: 7 dashboard role sudah ada (Platform & Karyawan baru ditambah/diperkaya sub-project sebelumnya), tapi Guru/Orang Tua/Siswa hanya menampilkan data modul Kasus — padahal data akademik (nilai, presensi, jadwal), keuangan (tagihan), dan SDM (kuota cuti, shift) sudah ada sebagai modul terpisah, cuma belum disambungkan ke dashboard. Siswa masih stub kosong total.
- **PENTING — draft spec/plan AWAL (versi pertama, sebelum revisi ini) DITOLAK** karena: mengusulkan komponen Blade baru yang DUPLIKAT dengan yang sudah ada (`<x-hero-banner>`, `<x-stat-tile>`, `<x-panel>`, `<x-badge>` — SEMUA SUDAH ADA di `resources/views/components/`), mengusulkan sistem token visual baru tanpa mengecek yang sudah dipakai, dan sebagian besar task-nya (6 dari 9) tidak punya kode sama sekali, cuma deskripsi seperti "Apply smooth layout". Plan REVISI ini (yang kamu baca sekarang) sudah memperbaiki semua itu — WAJIB reuse komponen existing, JANGAN buat versi baru.
- **Data seeder demo buang-pakai** — `migrate:fresh --seed` boleh dijalankan sesering perlu, tapi kebanyakan task diverifikasi lewat Pest test.

## Urutan eksekusi

Task 1 (`DashboardStatsService`) dan Task 2 (chart Alpine baru) WAJIB selesai dulu — keduanya prasyarat semua task dashboard (3-9). Task 3-9 (satu per dashboard) SALING INDEPENDEN satu sama lain (masing-masing menyentuh cabang berbeda di `DashboardController::index()` dan file Blade berbeda) — bisa dikerjakan paralel/urutan bebas SETELAH Task 1-2 selesai. Task 10 di akhir, setelah semua task dashboard selesai.

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/redesain-dashboard-multi-role/progress.md`.

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 10):
1. Baca task, kerjakan tiap step persis seperti ditulis (kode di plan itu final).
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit — pastikan cocok dengan yang dikutip plan (semua field/relasi model di plan ini SUDAH diverifikasi langsung dari kode, tapi tetap baca ulang file existing yang akan diedit, bukan model referensinya).
3. Jalankan `php -l <file>` (syntax check PHP) / `npm run build` (JS) setelah tiap edit, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. Satu commit per task.
6. Jangan jalankan full test suite sampai Task 10.
7. Task 10 Step 3 butuh persetujuan user EKSPLISIT sebelum full suite (Step 4).

## Peringatan eksplisit dari plan — beberapa task punya ketidakpastian yang HARUS diverifikasi, bukan diasumsikan

Beberapa bagian plan ini SENGAJA ditandai dengan **"PENTING"** karena butuh verifikasi tambahan saat eksekusi (nama field factory, helper test existing yang belum sempat dibaca penuh saat plan ditulis, dll) — SEMUA penanda "PENTING" WAJIB diikuti instruksinya, JANGAN dilewati:

1. **Task 1** — nilai valid untuk field `jenis_ptk` di `KuotaCutiConfig` belum diverifikasi (kemungkinan enum/string tertentu) — cek migration-nya dulu sebelum test dijalankan.
2. **Task 4** — nama helper setup di `tests/Feature/Admin/DashboardYayasanTest.php` adalah TEBAKAN (`actingAsYayasanManagerForDashboardTest()`) — baca file itu dulu untuk nama helper SEBENARNYA.
3. **Task 5** — test-nya SENGAJA berupa kerangka `->todo()` karena pola helper `DashboardLembagaTest.php` belum dibaca penuh — WAJIB dilengkapi jadi test sungguhan mengikuti pola file itu, bukan dibiarkan `->todo()` selamanya.
4. **Task 7** — `JamPelajaran::factory()` mungkin butuh field wajib tambahan (`pola_jam_id`) — cek factory-nya dulu.
5. **Task 8** — field wajib `NilaiSiswa`/`KomponenPenilaian` (apakah `asesmen_id` wajib diisi meski pakai jalur `komponen_penilaian_id`) belum diverifikasi — cek migration `nilai_siswa` dulu.

## Pelajaran penting dari sub-project sebelumnya di repo ini

1. **Draft awal plan ini sendiri adalah contoh nyata kenapa "tulis kode konkret, jangan deskripsi" itu wajib** — draft pertama sepenuhnya tidak bisa dieksekusi tanpa subagent mengarang semuanya sendiri. Kalau kamu (executor) menemukan bagian plan REVISI ini yang ternyata masih kurang detail/kode, JANGAN mengarang sendiri — STOP dan laporkan ke user bagian mana yang kurang.
2. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
3. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri.
4. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan.**
5. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)**, jangan asumsikan atau ekstrapolasi.
6. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.** Sub-project sebelumnya di repo ini punya kasus test yang "dilemahkan" (skenario pemicu bug dihapus) supaya lolos alih-alih bug-nya diperbaiki — itu HARUS dihindari. Kalau sebuah test gagal karena skenario yang memang seharusnya berhasil, perbaiki KODE-nya, JANGAN lemahkan test-nya.
7. **Reuse komponen/pola yang sudah ada adalah prioritas #1 di sub-project ini** — kalau kamu tergoda membuat komponen Blade/helper JS baru untuk "kerapian", STOP dulu dan cek apakah sudah ada yang setara di `resources/views/components/` atau `resources/js/dashboard-charts.js`. Ini bukan preferensi gaya, ini requirement eksplisit di Global Constraints plan.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: 4 method baru di `DashboardStatsService`, 5 test baru PASS.
- Task 2: 2 chart Alpine baru terdaftar, `npm run build` sukses.
- Task 3: Dashboard Platform ada chart tren tenant + 2 kolom health check baru, test PASS.
- Task 4: Dashboard Yayasan ada ringkasan presensi SDM lintas lembaga + kasus eskalasi unassigned, test PASS.
- Task 5: Dashboard Lembaga ada presensi SDM, progress rapor per kelas (kondisional permission), izin cuti pending, test PASS (bukan `->todo()` lagi).
- Task 6: Dashboard Karyawan ada chart presensi 30 hari, sisa kuota cuti, shift mendatang, test PASS.
- Task 7: Dashboard Guru ada jadwal hari ini, status wali kelas+progress rapor, presensi diri, RPP, rekap presensi siswa, test PASS.
- Task 8: Dashboard Orang Tua ada tagihan, nilai terbaru, jadwal anak, riwayat izin/sakit — lintas SEMUA anak yang terhubung, test PASS.
- Task 9: Dashboard Siswa dibangun dari nol (bukan stub lagi) — jadwal, presensi rekap bulan ini, nilai, tagihan, test PASS.
- Task 10: grep verifikasi kosong (tidak ada partial/token baru), full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (atau kegagalan yang ada terbukti pre-existing/tidak terkait, dengan bukti), handoff log tertulis dengan bukti verifikasi yang bisa ditelusuri (commit hash per task, angka test pasti, catatan hasil verifikasi tiap penanda "PENTING").
