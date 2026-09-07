# Kickoff: Badge Scope & Kejujuran Wording — Menu Tahun Ajaran

**Base commit**: `821780d0` (`docs(tahun-ajaran): implementation plan badge scope & kejujuran wording`)
**Branch**: `rbac-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menu "Tahun Ajaran" (backend `TenantScope`/`TahunAjaranController`/`TahunAjaran::activate()` TERKONFIRMASI BENAR, tidak diubah sama sekali) menemukan 6 gap murni di lapisan frontend — informasi yang ditampilkan ke user tidak konkret/tidak selalu mencerminkan apa yang backend-nya benar-benar lakukan:

1. Index tidak punya badge scope yayasan/lembaga (beda dari Siswa/Karyawan/Guru yang sudah diperbaiki sesi-sesi sebelumnya).
2. Kartu Tahun Ajaran tidak menampilkan nama lembaga pemiliknya — ambigu di mode "Semua Lembaga" kalau ≥2 lembaga punya TA bernama sama.
3. Wording dialog "Aktifkan" tidak menyebut lingkup "di lembaga yang sama".
4. Modal Tambah/Edit tidak menunjukkan konteks lembaga tujuan.
5. Halaman `admin.tahun-ajaran.create` (route+controller+view) dead code, dihapus.
6. Ikon `date_range` di modal tidak terdaftar di `<x-icon>`, jatuh ke placeholder tanda tanya — ditemukan user saat review spec, diganti `calendar_month`.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-08-tahun-ajaran-scope-wording-audit.md` — spec lengkap, 6 item dengan kode current-vs-fix konkret untuk masing-masing.
2. `.agents/plans/2026-09-08-tahun-ajaran-scope-wording-audit.md` — 5 task TDD, kode lengkap tiap step, sudah self-review.
3. `.agents/logs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md` bagian 5 — referensi konkret pola badge scope yang SAMA PERSIS direplikasi di sini (jangan desain ulang, cukup bandingkan hasil akhirnya konsisten).
4. `.ai/rules/index.md` lalu file rule yang glob-nya cocok (`controllers.md`, `views.md`, `tests.md`, `routes.md`).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Backend TIDAK BOLEH disentuh sama sekali** — `TenantScope`, query `index()`/`store()`/`update()`, `TahunAjaran::activate()` semuanya sudah dikonfirmasi benar di audit. Kalau selama implementasi terasa "sekalian saja diperbaiki juga bagian X di backend" — STOP, itu di luar scope, laporkan sebagai temuan terpisah, jangan diperbaiki diam-diam.
- **`scopeHeaderData()` WAJIB pakai `resolveActiveLembagaId()`**, BUKAN `resolveLembagaId()` dari trait yang sama — keduanya ada di `ResolveLembagaScopeTrait` dan MIRIP tapi beda perilaku (`resolveLembagaId()` melempar `abort(422)` saat yayasan-scope belum pilih lembaga; badge harus tetap tampil "Semua Lembaga" tanpa error). Tertukar di sini akan membuat halaman index ERROR 422 untuk yayasan-scope yang belum switch lembaga — regresi serius, bukan cuma kosmetik.
- **Item 5 (hapus halaman mati)**: permission `tahun-ajaran.create` di seeder TETAP DIPERTAHANKAN (dipakai otorisasi `store()`/`update()`/`@can` Blade) — HANYA route, method `create()`, dan file view yang dihapus. JANGAN hapus permission dari seeder.
- **Item 4+6 digabung jadi 1 edit** di file `_modal-tahun-ajaran.blade.php` (elemen `<h3>` yang sama persis) — JANGAN dipecah jadi 2 commit/edit terpisah pada file yang sama, ikuti Task 3 di plan apa adanya.
- **Task 4 Step 1 (verifikasi grep sebelum hapus) WAJIB benar-benar dijalankan**, bukan diasumsikan aman karena audit awal sudah bilang begitu — kode bisa saja berubah sejak audit ditulis. Kalau grep menemukan referensi baru di luar 2 file yang sudah diketahui (`routes/admin/akademik-master.php` dan `TahunAjaranController.php`), STOP, laporkan sebelum lanjut hapus.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php` (file existing, Pest function-style, sudah punya helper `actingAsTahunAjaranManager(Lembaga $lembaga): User`) — JANGAN bikin file test baru, plan sudah menetapkan ini secara eksplisit di setiap task.
- **`tests/Feature/TahunAjaranActivationTest.php`** menguji `TahunAjaran::activate()` di level MODEL langsung (bukan HTTP) — TIDAK disentuh plan ini karena backend tidak berubah, tapi tetap wajib dijalankan sebagai regresi di Task 5.
- **Task 2 Step 6 ada 1 test yang bisa PASS lebih awal dari yang diharapkan** (test "hides the lembaga name label...") karena baseline count kemunculan nama lembaga sudah 1 dari badge header (Task 2 Step 3, dikerjakan lebih dulu di task yang sama) — ini SUDAH didokumentasikan di plan sebagai catatan self-review, JANGAN bingung atau menganggap ada yang salah, tetap lanjut ke Step 7 karena test SATUNYA LAGI (2 lembaga beda nama) tetap gagal sampai fix diterapkan.
- **Ikon `calendar_month` dan `event`** (dipakai di kartu untuk rentang tanggal) berbagi markup SVG yang HAMPIR identik (`<rect>` + `<path>` sama persis, `event` cuma nambah `<circle>` sebelum ditutup) — test Task 3 Step 1 test ke-4 sengaja mengecek KETIADAAN path unik ikon default/placeholder (`M9.5 9a2.5 2.5 0 0 1 4.6-1.4`), BUKAN mencocokkan markup `calendar_month` secara langsung, supaya tidak salah-cocok dengan `event` yang sudah ada di halaman yang sama.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 5, konsisten dengan kickoff-kickoff sebelumnya.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec ternyata berbeda dari kode aktual di lapangan** (kemungkinan file sudah berubah sejak spec/plan ditulis) — STOP sejenak, baca versi terkini file itu, sesuaikan edit berdasarkan ISI kode (bukan nomor baris), catat perbedaannya di laporan task.
- **Kalau Task 4 Step 1 (grep ulang) menemukan referensi ke `admin.tahun-ajaran.create` di luar 2 file yang sudah diketahui** — STOP, jangan lanjut hapus, laporkan dulu ke user.
- **Kalau full suite Task 5 menunjukkan kegagalan DI LUAR modul Tahun Ajaran/Semester** — investigasi dulu apakah terkait perubahan di plan ini (seharusnya TIDAK ADA, karena backend tidak disentuh); kalau ternyata tidak terkait sama sekali (mis. seeder demo PPDB yang memang sudah dikenal flaky dari sesi-sesi sebelumnya), catat sebagai pre-existing, bukan tanggung jawab plan ini untuk memperbaiki.

## 6. Catatan Serah Terima

- Spec ini SEMPAT direvisi 1 kali dalam sesi yang sama (Item 6 ditambahkan setelah user menemukan ikon placeholder tanda tanya saat review spec) — sudah final sekarang, tidak ada draft lama yang perlu diperhatikan.
- User secara eksplisit meminta kickoff kali ini (bukan eksekusi inline) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 5 selesai, JANGAN merge branch `rbac-v2` ke `main` — keputusan terpisah milik user (branch ini membawa banyak hasil kerja lain dari sesi-sesi sebelumnya yang belum di-merge).
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 5 Step 4) — kalau user memang menghendaki log terpisah untuk pekerjaan ini setelah selesai, itu permintaan tambahan yang akan datang belakangan, bukan bagian implisit dari kickoff ini.

## 7. Mulai dari mana

Mulai dari **Task 1** (`scopeHeaderData()` di controller) di `.agents/plans/2026-09-08-tahun-ajaran-scope-wording-audit.md`, kerjakan berurutan Task 1 → 5. Task 1 dan 2 harus berurutan (Task 2 butuh `$isYayasan`/`$activeLembaga` dari Task 1 dan eager-load `lembaga` yang sama). Task 3 independen dari Task 2 (variabel yang sama, file berbeda) tapi tetap butuh Task 1 selesai lebih dulu. Task 4 sepenuhnya independen (boleh dikerjakan kapan saja setelah Task 1-3, bahkan sebelum kalau ada alasan praktis) — tapi ikuti urutan plan (1→5) kecuali ada alasan kuat, dan catat kalau menyimpang.
