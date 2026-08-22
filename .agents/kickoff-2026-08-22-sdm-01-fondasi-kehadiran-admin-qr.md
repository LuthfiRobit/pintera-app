# Kickoff Prompt — Kehadiran SDM Sub-project 1 (Fondasi + Admin Manual + QR Statis)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan siap jalan. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu, dalam urutan ini

1. `.agents/specs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` — spec (kenapa dan apa: fondasi domain Kehadiran SDM, model data, tenant isolation, RBAC, alur admin manual + QR)
2. `.agents/plans/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` — plan implementasi (10 task, lengkap dengan kode PHP/Blade/JS dan langkah verifikasi)

Plan itu ditulis dengan asumsi kamu (executor) tidak punya konteks apa pun tentang codebase ini di luar isi plan dan isi repo — setiap task berisi kode lengkap, bukan deskripsi/pseudocode.

## Konteks singkat

- Repo Laravel 11 multi-tenant (`pintera-app`). Branch kerja: `sdm-v1` (SUDAH ADA, jangan buat branch baru, jangan buat worktree).
- Fitur baru: modul **Kehadiran SDM** (absensi pegawai — guru & karyawan, BUKAN presensi murid yang sudah ada di modul Akademik). Sub-project 1 ini fondasi: 5 tabel (metode absensi per lembaga, titik absen, event immutable, record harian agregat, QR pegawai), 5 Action, RBAC baru (`admin_sdm`), 4 controller + view (konfigurasi, input manual kehadiran, scan QR oleh petugas, self-view QR pegawai).
- **Tenant isolation 100% reuse mekanisme existing** (`App\Models\Concerns\BelongsToTenant` + `App\Models\Scopes\TenantScope`) — JANGAN menulis kode isolasi baru, JANGAN mengubah `TenantScope.php`. Task 7 punya 1 pengecualian sengaja: query konfigurasi metode default yayasan (`lembaga_id IS NULL`) HARUS bypass TenantScope secara eksplisit (`withoutGlobalScope`) karena scope itu akan menyembunyikan baris default dari aktor `scope_level: lembaga` — plan sudah menjelaskan kenapa dan bagaimana filter manual penggantinya (`where('yayasan_id', ...)` + kondisi lembaga aktif OR null), JANGAN dianggap sebagai bug yang perlu "diperbaiki" dengan cara lain.
- Relasi ke pegawai (Guru/Karyawan) pakai **polymorphic morphTo/morphMany** (`pegawai_type`/`pegawai_id`), TANPA morph map terdaftar — FQCN mentah disimpan, mengikuti pola `App\Domains\Workflow\Models\ApprovalRequest::approvable()` yang sudah ada di codebase ini. JANGAN daftarkan `Relation::morphMap()` di provider manapun, itu bukan konvensi codebase ini.
- **TIDAK ADA hardcode nama role apapun** (`hasRole('admin_sdm')` dst) — semua authorization pakai `$this->authorize('kehadiran-sdm.xxx')` via Spatie Permission. Ini bukan preferensi gaya, tapi hasil temuan audit RBAC sebelumnya di repo ini yang menemukan bug nyata dari hardcode role — kalau kamu menulis kode dengan `hasRole()` untuk cek permission modul ini, STOP, itu salah.
- Task 1-6 murni backend (migrasi, model, RBAC seeder, DTO, Service, Action) — tidak ada UI. Task 7-9 controller + routes + view. Task 10 verifikasi akhir + full-suite gate.

## Cara eksekusi

**Kalau kamu punya akses ke skill `superpowers` (Claude Code dengan plugin Superpowers terpasang):**
Gunakan skill `superpowers:subagent-driven-development` untuk mengeksekusi plan ini task-by-task — fresh subagent per task, task review, ledger progress di `.superpowers/sdm-sdd/progress.md`. Ini mode yang sama yang dipakai untuk migrasi domain Akademik dan Kasus sebelumnya di repo ini.

**Kalau kamu tidak punya skill itu (agent lain / tanpa Superpowers):**
Eksekusi manual task-by-task sesuai urutan di plan (Task 1 → 10, urutannya penting — tiap task punya blok "Interfaces: Consumes/Produces" eksplisit yang menandai dependency ke task sebelumnya):
1. Baca task, kerjakan tiap step-nya persis seperti ditulis (kode di plan itu final, jangan diparafrase).
2. Jalankan verifikasi (tinker, migrate:status, php artisan test) SEBELUM commit — kalau ada yang gagal, JANGAN commit, cari tahu kenapa dulu.
3. Satu commit per task, pakai pesan commit yang sudah ditentukan di tiap task Step terakhir.
4. Jangan jalankan full test suite sampai Task 10.
5. Task 10 Step 3 butuh persetujuan user secara EKSPLISIT sebelum menjalankan full suite (Step 4) — TANYA dulu, jangan otomatis jalan.

## Pelajaran penting dari review pekerjaan sebelumnya di branch/repo ini (WAJIB diperhatikan)

Review independen terhadap eksekusi-eksekusi sebelumnya di repo ini menemukan pola kegagalan yang harus dihindari:
1. **Jangan tandai step/task selesai di commit message atau handoff log kalau isinya belum benar-benar diverifikasi lewat command nyata.** Setiap klaim "sudah diverifikasi" harus bisa ditelusuri ke output command yang benar-benar dijalankan (test PASS dengan jumlah pasti, migrate:status yang hasilnya dicek, dst) — bukan asumsi dari membaca kode.
2. **Kalau full suite menunjukkan kegagalan yang TIDAK terkait sama sekali dengan Kehadiran SDM, jangan langsung anggap itu masalah dari pekerjaanmu.** Ada pola flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) yang gagal sesekali karena randomness, tidak terkait perubahan apapun — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi sebelum melaporkan sebagai regresi.
3. **Kalau plan mengasumsikan sesuatu tentang file yang ternyata sudah berbeda saat kamu baca** (misal `database/seeders/RoleSeeder.php` sudah punya baris berbeda dari yang dikutip plan karena ada commit baru masuk) — JANGAN menebak atau "memperbaiki sendiri" secara diam-diam. Cek dulu isi file sebenarnya, laporkan penyimpangannya ke user sebelum melanjutkan.
4. **Test tenant-isolation WAJIB benar-benar dijalankan dan hijau, jangan dilewati.** Modul ini rawan pola cross-tenant IDOR yang sudah berulang kali ditemukan di modul lain repo ini — Task 6, 8, 9 masing-masing punya test eksplisit untuk itu (`QrTokenLembagaMismatchException`, penolakan 404 admin lintas lembaga), jangan anggap ini test "opsional" atau "boleh dilewati kalau waktu sempit".

## Kalau menemukan sesuatu yang tidak sesuai plan

Kode yang dikutip plan ini berbasis commit `7a13c83` di branch `sdm-v1`. Kalau kamu menemukan isi file yang BEDA signifikan dari yang dikutip plan (bukan cuma beda nomor baris), atau blok kode yang dicari plan ternyata TIDAK ADA sama sekali — STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Setelah kamu selesai

Task 10 Step 5 di plan sudah meminta kamu menulis handoff log ke `.agents/logs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` (ringkasan per task, commit hash, hasil verifikasi akhir dengan angka pasti). Sesi yang menulis plan ini kemungkinan akan melakukan review independen terhadap hasil kerjamu (termasuk `git diff` terhadap baseline dan menjalankan full test suite sendiri) — pastikan setiap commit bersih dan bisa ditelusuri (jangan squash / rewrite history).

## Definisi selesai

Task 10 selesai: seluruh test scoped Task 2-9 hijau bersama-sama (≥ 25 test baru), full suite dijalankan SETELAH izin eksplisit user dan hasilnya hijau (0 failed, 0 error), grep `hasRole('admin_sdm')` kosong, handoff log tertulis di `.agents/logs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md` dengan bukti verifikasi yang bisa ditelusuri (bukan klaim tanpa command).
