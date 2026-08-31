# Handoff Log: Identity v1 Task 28 & Migration Schema Squash

- **Tanggal:** 2026-09-01
- **Branch:** `identity-v1`
- **Spec Terkait:** [`.agents/specs/2026-08-29-identity-v1-person-master-entity.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-29-identity-v1-person-master-entity.md)
- **Plan Terkait:** [`.agents/plans/2026-08-29-identity-v1-person-master-entity.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-29-identity-v1-person-master-entity.md)
- **Kickoff Terkait:** [`.agents/kickoff-2026-09-01-identity-v1-task28-drop-columns-schema-squash.md`](file:///d:/laragon/www/pintera-app/.agents/kickoff-2026-09-01-identity-v1-task28-drop-columns-schema-squash.md)

---

## 1. Apa yang dikerjakan

1. **Bagian 1: Task 28** — drop 45 kolom identitas lama dari 5 tabel role (`guru` 18, `karyawan` 6, `orang_tua` 6, `siswa` 6, `calon_murid` 9), bersih-bersih `$fillable`/`casts()` di 5 model, plus perbaikan lanjutan di beberapa controller/seeder lain yang masih membaca kolom lama secara langsung (`LembagaController`, `JadwalPelajaranController`, `SiswaOrangTuaController`, dan beberapa seeder) — SEMUA diverifikasi benar lewat review.
2. **Bagian 2: `php artisan schema:dump --prune`** — 138 file migration lama disatukan jadi `database/schema/mysql-schema.sql`.
3. **Review pasca-eksekusi menemukan 2 masalah nyata**, keduanya sudah diselesaikan (lihat §2 dan §3 di bawah).

## 2. Temuan review — pelanggaran proses (dikonfirmasi ke user, diselesaikan)

**4 file test dihapus tanpa izin eksplisit**, melanggar aturan proyek ("Do not delete tests or test files without approval"):

- `LembagaIuranMigrationTest.php`, `TagihanBackfillTest.php`, `BackfillSubjekPenilaianMigrationTest.php` — **TIDAK BISA DIHINDARI**: ketiganya melakukan `require database_path('migrations/<file-spesifik>.php')` lalu memanggil `->up()` langsung. `schema:dump --prune` menghapus fisik SEMUA file migration lama, jadi ketiga test ini otomatis jadi fatal-error (file not found) begitu di-prune, terlepas dari domain (bukan cuma identity). Ini konsekuensi struktural dari pendekatan `schema:dump` yang seharusnya diperkirakan di kickoff, bukan kesalahan independen agent eksekusi.
- `BackfillPersonsFromRoleTablesTest.php` — BERBEDA karakter: menguji command `identity:backfill-persons` yang secara fisik MASIH ADA (bukan migration file). Dikonfirmasi ke user: command ini (plus `identity:verify-backfill`) sekarang permanen no-op karena kolom sumber yang dibacanya sudah di-drop Task 28, dan setiap row sudah dijamin punya `person_id` (NOT NULL + FK sejak Task 27). **User memilih menghapus KEDUA command ini** (bukan hanya mengembalikan test-nya) — commit `fb4e2d72`. Hasil akhir konsisten: tidak ada command mati tanpa test tersisa.

## 3. Temuan review — bug lingkungan KRITIS (ditemukan, didiagnosis, diperbaiki)

**`schema:dump --prune` mensyaratkan client CLI `mysql`/`mysqldump`** (dikonfirmasi dari dokumentasi resmi Laravel 12 — "utilizes the database's command-line client", tidak ada fallback PDO-only). Di mesin dev ini (Laragon/Windows), binary tersebut ADA di `D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\` tapi **TIDAK ada di PATH sistem** — akibatnya, setiap `migrate:fresh` (termasuk yang dipicu otomatis oleh `RefreshDatabase` di awal test run) GAGAL TOTAL dengan `ProcessFailedException` ("operable program or batch file" — pesan Windows klasik untuk executable tidak ditemukan).

**Dampak sebelum diperbaiki: 2403 dari 2517 test GAGAL** — jauh dari klaim "2517 passed, 0 failed" di laporan awal. Klaim itu ternyata dijalankan di sesi shell yang kebetulan sudah punya `mysql` di PATH-nya sendiri (tidak terverifikasi ulang oleh reviewer sebelum dipercaya), sementara verifikasi independen dari sesi terpisah (proses baru) langsung membongkar masalah ini.

**Perbaikan**: PATH environment variable user Windows ditambahkan permanen dengan `D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin` (lewat `[Environment]::SetEnvironmentVariable(..., 'User')`, dikonfirmasi tersimpan di registry). **Perubahan ini baru berlaku untuk sesi/terminal/IDE BARU** — proses yang sudah berjalan (termasuk sesi kerja saat verifikasi ini) tidak otomatis membaca ulang PATH, sehingga verifikasi lanjutan dilakukan dengan meng-export PATH secara eksplisit per-command sampai terbukti benar.

Setelah PATH diperbaiki: `migrate:fresh --seed` berhasil (42 seeder), full suite kembali hijau — mengonfirmasi pendekatan `schema:dump --prune` itu sendiri SAH dan BENAR, masalahnya murni environment yang belum siap, bukan cacat desain.

**Untuk developer lain / CI yang menjalankan project ini**: pastikan `mysql`/`mysqldump` ada di PATH sebelum `migrate:fresh`/test suite dijalankan. Di Windows+Laragon, ini SERING perlu ditambahkan manual (tidak otomatis oleh installer Laragon).

## 4. Hasil verifikasi (angka pasti, tiap tahap)

| Tahap | Hasil |
|---|---|
| Setelah Task 28 + squash, SEBELUM PATH diperbaiki (verifikasi independen) | **2403 failed**, 114 passed — environment rusak total |
| Setelah PATH diperbaiki, sebelum hapus command mati | **2517 passed, 0 failed** (6965 assertions) — cocok persis dengan klaim awal setelah environment benar |
| Setelah hapus `BackfillPersonsFromRoleTables`+`VerifyPersonsBackfill`+test-nya — **FINAL** | **2515 passed, 0 failed** (6962 assertions) — selisih 2 test sesuai perhitungan (VerifyPersonsBackfillTest punya 2 test) |

Baseline sebelum Task 28 (dari sesi remediasi Task 11-27 sebelumnya): 2530 passed, 4 skipped. Selisih ke 2515 (final) dijelaskan sepenuhnya oleh: 4 file test dihapus (§2) yang totalnya berisi ~17 test (termasuk 4 yang sebelumnya berstatus skipped, semuanya hilang bersama file-nya).

## 5. Keputusan penting yang diambil

1. **`schema:dump --prune` dipilih dibanding tulis ulang manual** (keputusan user sebelum kickoff ditulis) — terbukti benar setelah environment diperbaiki; kalau tidak diverifikasi ulang secara independen, klaim "green" yang sebenarnya environment-dependent nyaris lolos tanpa terdeteksi.
2. **2 command (`identity:backfill-persons`, `identity:verify-backfill`) dihapus, bukan dipertahankan** — keputusan eksplisit user setelah disodorkan 3 opsi, karena keduanya permanen tidak berguna dan meninggalkan dead code tanpa test itu lebih buruk daripada menghapus bersih.
3. **PATH environment diperbaiki secara permanen (user-level), bukan direvert ke 138 migration lama** — dikonfirmasi user, karena akar masalahnya adalah environment yang belum siap, bukan cacat pendekatan `schema:dump` itu sendiri.

## 6. Hal yang masih perlu direview manusia / Claude

1. **Git state**: branch `identity-v1`, commit terbaru `fb4e2d72`, working tree bersih (kecuali `storage/debugbar/` yang untracked, tidak relevan).
2. **PATH fix berlaku HANYA di mesin ini** — kalau project ini dikerjakan di mesin lain (developer lain, CI runner), fix yang sama (menambahkan direktori bin `mysql`/`mysqldump` ke PATH) perlu diulang di sana.
3. **Task 28 dan squash migration SEKARANG SELESAI TOTAL** — seluruh plan identity-v1 (Task 1-28) sudah tuntas, direview, dan diverifikasi dengan angka pasti di setiap tahap.
