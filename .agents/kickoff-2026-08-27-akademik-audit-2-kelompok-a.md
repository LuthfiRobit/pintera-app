# Kickoff Prompt — Audit Sistematis Akademik Tahap 2, Kelompok A (Kritis)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP, hasil audit sistematis PENUH terhadap area Akademik yang belum pernah diaudit sebelumnya (Kenaikan Kelas, Jadwal Pelajaran/Pola Jam/Kalender Akademik, RPP, Ekstrakurikuler, konsistensi KurikulumAssignment/FaseDefaultMapping, notifikasi Akademik). Dari 10 temuan gabungan, 3 dikategorikan kritis dan menjadi "Kelompok A" — itulah plan ini. Kamu tidak perlu audit ulang, tidak perlu menulis spec baru.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-27-akademik-audit-2-kelompok-a.md` — spec lengkap. §1 WAJIB dibaca, terutama §1.1.1 yang menjelaskan KENAPA jadwal lama TIDAK BOLEH dihapus (cascade delete ke riwayat presensi siswa).
2. `.agents/plans/2026-08-27-akademik-audit-2-kelompok-a.md` — plan implementasi (3 task, kode lengkap, TDD step-by-step).

## Konteks penting — kenapa fix ini ada

Audit sistematis pasca-Priority #6 (lihat commit history `akademik-v2`) menemukan 3 celah kritis:
1. Widget "Jadwal Hari Ini" guru di dashboard mencampur jadwal lintas tahun ajaran karena tidak ada filter semester aktif.
2. `kelas.kurikulum`/`kelas.fase_id` adalah snapshot beku (disengaja, sudah di-test) yang TIDAK PUNYA mekanisme koreksi kalau assignment sumbernya salah input.
3. Nama ekskul di `catatan_wali_kelas` (tampil di rapor cetak resmi) diterima sbg teks bebas tanpa validasi ke master data `ekstrakurikuler_lembaga`.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu struktur tabel (`jadwal_pelajaran`, `kelas`, `kurikulum_assignment`, `fase_default_mapping`, `ekstrakurikuler_lembaga`, `catatan_wali_kelas`) — pakai `database-schema`, jangan buka migration manual.
- **JANGAN buat script verifikasi terpisah/tinker** — test yang ditulis plan sudah cukup.
- Kalau ragu soal `whereHas`/query scope Eloquent version-sensitive — pakai `search-docs`.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/actions.md` (Task 2: `ResyncKurikulumFaseKelasAction`)
- `.ai/rules/controllers.md` (Task 2: `ResyncKurikulumFaseController`; Task 3: `RaporController`)
- `.ai/rules/requests.md` (Task 3: `StoreCatatanWaliKelasRequest`)
- `.ai/rules/models.md` (Task 1: `JadwalPelajaran`)
- `.ai/rules/routes.md` (Task 2: route baru)
- `.ai/rules/views.md` (Task 2, 3: view baru/diedit)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dgn instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12.68.0 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Task 1 — LARANGAN KERAS**: JANGAN menghapus/memodifikasi baris `jadwal_pelajaran` mana pun. Perbaikan HANYA berupa filter query (`scopeSemesterAktif`). Kalau kamu tergoda "membersihkan data lama juga", STOP — itu akan cascade-delete riwayat presensi siswa (`sesi_pembelajaran.jadwal_pelajaran_id` punya `cascadeOnDelete()`), keputusan eksplisit user sudah menolak pendekatan itu.
- **Task 2 — LARANGAN KERAS**: JANGAN mengubah `UpdateKelasAction`/`UpdateKurikulumAssignmentAction`/`UpdateFaseDefaultMappingAction` menjadi auto-resync. Perilaku "snapshot beku" itu SENGAJA dan sudah di-test (`tests/Feature/Akademik/KelasKurikulumSnapshotTest.php`) — JANGAN sampai test itu jadi FAIL. Resync HANYA lewat aksi manual baru yang ditulis plan ini.
- **Task 2**: nilai yang disimpan saat resync HARUS dihitung ulang di server (`ResyncKurikulumFaseKelasAction::terapkan()` menghitung ulang via resolver, TIDAK menerima nilai kurikulum/fase dari request langsung) — cegah tampering.
- **Task 3**: validasi WAJIB scoped per lembaga siswa (`EkstrakurikulerLembaga::where('lembaga_id', $siswa->lembaga_id)`) — JANGAN validasi lintas semua lembaga, itu akan membuka celah tenant-isolation baru.

## Urutan eksekusi

**Task 1 → 2 → 3 murni LINEAR** (independen satu sama lain secara teknis, tapi urutan ini memudahkan review bertahap — Task 1 paling kecil/aman, Task 2 paling besar, Task 3 medium).

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini py 3 task dgn ukuran berbeda (Task 1 kecil, Task 2 besar/menyentuh Controller+View+Action baru, Task 3 medium) — boleh eksekusi manual langsung (`superpowers:executing-plans`), atau `subagent-driven-development` kalau mau ekstra hati-hati krn Task 2 menyentuh alur data lintas tenant (kurikulum/fase kelas).

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`DashboardController.php` baris 51-56, `JadwalPelajaran.php`, `KurikulumAssignmentController.php`, `StoreCatatanWaliKelasRequest.php`, `RaporController.php` baris 122-153, `catatan/edit.blade.php` baris 60-72) dan bandingkan dgn kutipan di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 4 commit total (3 task + 1 docs `PETA_PENGEMBANGAN.md`), pesan commit sudah ditulis di tiap Step terakhir.
6. **JANGAN jalankan full test suite sampai Task 3 Step 7.**

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 5** — cek `database/factories/SemesterFactory.php` untuk default `status_aktif`. Kalau test existing (`'passes teaching schedule...'`, `'shows an orang tua the latest recorded grade...'`) jadi FAIL setelah fix, itu tanda test tsb perlu tambahan `'status_aktif' => true` eksplisit — perbaiki test-nya (bukan kode Task 1), dan laporkan perubahan ini secara eksplisit di report akhir, jangan diam-diam.
2. **Task 2 Step 3** — `ResyncKurikulumFaseKelasAction::hitungDiff()` HARUS membandingkan `$kelas->kurikulum?->value` (bukan objek enum langsung) supaya perbandingan `===` benar terhadap string dari resolver.
3. **Task 2 Step 7** — nama route `admin.kurikulum-assignment.resync` (GET, index+diff) vs `admin.kurikulum-assignment.resync.apply` (POST, eksekusi) — JANGAN tertukar, plan sudah konsisten pakai keduanya secara terpisah.
4. **Task 3 Step 3** — `StoreCatatanWaliKelasRequest::ekskulOptionsUntukSiswa()` mengambil `lembaga_id` dari `$this->route('siswa')` (route model binding) — pastikan route `guru.rapor.catatan.update` memang binding `Siswa $siswa` (sudah dikonfirmasi di `RaporController::update(Siswa $siswa, ...)`), jangan asumsikan nama parameter lain.

## Pelajaran penting dari sprint-sprint akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — jalankan `php artisan test --compact` TANPA filter apa pun di Task 3 Step 7 utk klaim "full suite hijau".
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Jangan menambah scope di luar 3 task ini** — termasuk godaan mengerjakan Kelompok B (Kenaikan Kelas UX) atau Kelompok C (RPP reporting) yang disebut di spec sbg "menyusul terpisah" — itu bukan bagian plan ini.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`database-query`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: widget "Jadwal Hari Ini" guru hanya menampilkan jadwal semester aktif, `scopeSemesterAktif()` tersedia di model `JadwalPelajaran`, semua test di `DashboardTest.php` (existing + 2 baru) PASS.
- Task 2: `ResyncKurikulumFaseKelasAction` + `ResyncKurikulumFaseController` + view resync selesai, admin bisa lihat & pilih kelas mana yang mau disinkronkan kurikulum/fase-nya, semua test PASS termasuk cross-tenant guard.
- Task 3: form catatan wali kelas pakai dropdown ekskul dari master data, validasi backend menolak nama tak terdaftar/lintas lembaga, semua test PASS.
- **Full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, `PETA_PENGEMBANGAN.md` sudah dicatat tindak lanjutnya, laporan final ke user berisi angka pasti + commit hash.
