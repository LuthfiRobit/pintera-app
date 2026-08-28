# Kickoff Prompt — Fix Susulan Kelompok A: Widget Jadwal Siswa & Orang Tua

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP. Ini adalah **fix susulan** dari Kelompok A (audit sistematis Akademik tahap 2, yang sudah dinyatakan SELESAI sebelumnya) — user menantang klaim "audit sudah mencakup semua layer" dengan pertanyaan eksplisit soal layer siswa/orang tua, dan ditemukan 2 consumer yang terlewat waktu fix widget jadwal guru di Kelompok A. Kamu tidak perlu audit ulang, tidak perlu menulis spec baru.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-audit-2-kelompok-a-lanjutan.md` — spec lengkap. §1 WAJIB dibaca, terutama nuansa TenantScope untuk orang tua lintas-lembaga.
2. `.agents/plans/2026-08-27-akademik-audit-2-kelompok-a-lanjutan.md` — plan implementasi (3 task, kode lengkap, TDD step-by-step).

## Konteks penting — kenapa fix ini ada

Kelompok A memperbaiki widget "Jadwal Hari Ini" untuk GURU (`DashboardController.php`) dengan menambah `JadwalPelajaran::scopeSemesterAktif()`. Tapi ada 2 widget lain di file yang sama — untuk SISWA dan ORANG TUA — dengan bug identik (query jadwal tanpa filter semester aktif) yang tidak pernah disentuh. Ditemukan saat user meminta audit ulang khusus layer siswa/orang tua.

## Peringatan PALING KRITIS — nuansa TenantScope untuk orang tua

`Semester` model memakai `BelongsToTenant` (auto-filter `lembaga_id` milik user yang login). `scopeSemesterAktif()` versi Kelompok A **TIDAK membypass** TenantScope pada subquery semester — ini kebetulan benar untuk guru (satu lembaga), tapi **SALAH untuk orang tua** yang anaknya bisa di lembaga berbeda-beda (akun orang tua sering `lembaga_id = null`). Task 1 di plan ini **WAJIB** mengubah `scopeSemesterAktif()` supaya subquery semester-nya pakai `withoutGlobalScope(TenantScope::class)` — SALIN PERSIS kode di plan, jangan diubah/disederhanakan. Kalau langkah ini dilewati atau salah, Task 3 (test lintas-lembaga) akan gagal dengan cara yang membingungkan (jadwal anak di lembaga lain hilang, bukan cuma yang basi).

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu struktur tabel (`jadwal_pelajaran`, `semester`, `siswa`, `orang_tua`) — pakai `database-schema`, jangan buka migration manual.
- **JANGAN buat script verifikasi terpisah/tinker** — test yang ditulis plan sudah cukup.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/models.md` (Task 1: `JadwalPelajaran`)
- `.ai/rules/controllers.md` (Task 2, 3: `DashboardController`)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.68.0 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **JANGAN hapus data `jadwal_pelajaran` apa pun** — konsisten dengan keputusan Kelompok A, jadwal lama tetap riwayat sah.
- **Task 1 Step 2 & 4**: WAJIB jalankan test regresi guru DUA KALI (sebelum dan sesudah perubahan `scopeSemesterAktif()`) untuk membuktikan hasilnya identik — bukan cuma "masih pass", tapi bukti bahwa perubahan implementasi tidak mengubah perilaku guru sama sekali.
- **Task 3**: 2 dari 3 test baru adalah skenario lintas-lembaga (orang tua dengan anak di 2 lembaga berbeda) — ini bagian TERPENTING dari plan ini, jangan dilewati atau disederhanakan jadi cuma 1 lembaga.
- **Full test suite TIDAK PERLU dijalankan** — plan ini sempit (1 method + 2 query di 1 controller), cukup `tests/Feature/DashboardTest.php` penuh sebagai checkpoint (Task 3 Step 6).

## Urutan eksekusi

**Task 1 → 2 → 3 murni LINEAR** (Task 1 adalah prasyarat teknis untuk Task 2 & 3).

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini kecil (3 task, 2 file produksi) — eksekusi manual langsung (`superpowers:executing-plans`) sudah cukup, tidak perlu `subagent-driven-development`.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`JadwalPelajaran.php`, `DashboardController.php` baris 113-170 & 172-255) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 4 commit total (3 task + 1 docs), pesan commit sudah ditulis di tiap Step terakhir.

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1** — `scopeSemesterAktif()` HARUS tetap sama signature (`public function scopeSemesterAktif(Builder $query): Builder`), hanya isi implementasinya yang berubah.
2. **Task 2 & 3** — `->semesterAktif()` ditambahkan SEBAGAI TAMBAHAN di query yang sudah ada, JANGAN hapus/ubah pola `withoutGlobalScope(TenantScope::class)` yang sudah ada di kedua branch.
3. **Task 3** — cek dulu apakah `use App\Models\OrangTua;` sudah ada di import `tests/Feature/DashboardTest.php` sebelum menambahkannya (kemungkinan sudah ada dari test lama).

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti).**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 3 task ini.**

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: `scopeSemesterAktif()` membypass TenantScope pada subquery semester, 2 test regresi guru tetap identik hasilnya sebelum/sesudah.
- Task 2: widget jadwal siswa filter semester aktif, 2 test baru PASS, test siswa lama tetap hijau.
- Task 3: widget jadwal orang tua filter semester aktif PER ANAK (termasuk lintas-lembaga), 3 test baru PASS termasuk 2 skenario lintas-lembaga.
- `tests/Feature/DashboardTest.php` penuh 0 failed, angka pasti dicatat, `PETA_PENGEMBANGAN.md` dicatat tindak lanjutnya, laporan final ke user berisi angka pasti + commit hash.
