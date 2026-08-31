# Kickoff Prompt — Identity v1 Task 28 (Drop Legacy Columns) + Full Migration Schema Squash

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis dokumen ini — dikerjakan agent eksternal seperti Antigravity, atau lanjutan sesi Claude Code yang sama kalau diminta).

---

Kamu akan mengerjakan DUA hal berurutan pada branch `identity-v1` di app Laravel 12 multi-tenant "Pintera" (`pintera-app`):

1. **Task 28** dari plan identity-v1 (`.agents/plans/2026-08-29-identity-v1-person-master-entity.md`) — menghapus fisik kolom identitas lama dari 5 tabel role (`guru`, `karyawan`, `orang_tua`, `siswa`, `calon_murid`), yang sudah tidak dipakai lagi sejak Stage 4-6 (semua identitas sekarang dibaca/ditulis lewat `persons`).
2. **Inisiatif baru (BUKAN bagian spec identity-v1 asli)**: memakai `php artisan schema:dump --prune` untuk menyatukan SELURUH 138 file migration project ini (bukan cuma yang identity-related) menjadi satu file schema dump — proyek masih tahap development, `php artisan migrate:fresh --seed` aman dijalankan kapan saja (tidak ada data produksi asli, hanya seed data role/permission/demo).

**Kedua langkah ini disetujui eksplisit oleh user setelah membandingkan 2 pendekatan** — user MEMILIH `schema:dump --prune` (snapshot skema database asli) dibanding menulis ulang manual semua migration jadi "clean create table". Alasannya: sesi kerja identity-v1 sebelumnya BERULANG KALI menemukan bug nyata dari menulis ulang skema secara manual (kehilangan `enum('L','P')` constraint, kehilangan `default('WNI')`, kolom NOT NULL yang terlewat — semua terjadi di Task 3 migration yang HANYA menyentuh 5 tabel, dan butuh 2 putaran perbaikan untuk benar). Menulis ulang manual 138 file migration akan punya risiko yang SAMA tapi berlipat skalanya. **JANGAN mengubah pendekatan ini ke "tulis ulang manual create table" — itu sudah dipertimbangkan dan ditolak user.**

## Baca dulu, urutan ini

1. `.agents/logs/2026-08-29-identity-v1-person-master-entity-remediation.md` — riwayat lengkap bug yang ditemukan+diperbaiki di identity-v1 (termasuk pola "menulis ulang skema manual = risiko tinggi" yang jadi alasan pemilihan `schema:dump`).
2. `.agents/specs/2026-08-29-identity-v1-person-master-entity.md` §3 (DDL final) dan §8 (urutan migrasi, langkah 6 = Task 28) — konfirmasi kolom mana yang harus di-drop.
3. `.agents/plans/2026-08-29-identity-v1-person-master-entity.md`, cari "### Task 28" — deskripsi Task 28 yang sudah ada di plan (garis besar, bukan kode detail).

## Bagian 1: Task 28 — Drop Kolom Identitas Lama

### Kolom yang harus di-drop (dikonfirmasi langsung dari skema live saat ini, bukan tebakan)

**`guru`** — drop 18 kolom: `user_id`, `nik`, `nik_hash`, `nama`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `kewarganegaraan`, `alamat_jalan`, `rt`, `rw`, `desa_kelurahan`, `kecamatan`, `kabupaten_kota`, `provinsi`, `kode_pos`, `no_hp`, `email`.
Sisa kolom: `id`, `person_id`, `lembaga_id`, `nuptk`, `nip`, `jenis_ptk`, `kapasitas_kasus_aktif`, `status_kepegawaian`, `golongan_pangkat`, `tmt_tugas`, `tmt_pns`, `status_aktif`, timestamps.

**`karyawan`** — drop 6 kolom: `user_id`, `nik`, `nik_hash`, `nama`, `no_hp`, `email`.
Sisa: `id`, `person_id`, `yayasan_id`, `lembaga_id`, `jenis_karyawan_id`, `status_aktif`, `kapasitas_kasus_aktif`, timestamps.

**`orang_tua`** — drop 6 kolom: `user_id`, `nama_lengkap`, `nik`, `no_hp`, `email`, `alamat`.
Sisa: `id`, `person_id`, `pekerjaan`, timestamps.

**`siswa`** — drop 6 kolom: `user_id`, `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`.
Sisa: `id`, `person_id`, `lembaga_id`, `kelas_id`, `calon_murid_id`, `pendaftaran_asal_id`, `sumber_data`, `nis`, `nisn`, `status`, timestamps.

**`calon_murid`** — drop 9 kolom: `nik`, `nik_hash`, `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `no_telepon`, `email_kontak`.
Sisa: `id`, `person_id`, `yayasan_id`, `no_kk`, `nisn`, `golongan_darah`, timestamps. **`no_kk` dan `golongan_darah` SENGAJA TIDAK di-drop** — keduanya tidak punya padanan di `persons` (gap yang sudah dicatat di spec, di luar scope identity-v1, JANGAN sentuh).

### Kendala teknis penting — FK dan unique index harus di-drop SEBELUM kolom

`guru.user_id`, `karyawan.user_id`, `orang_tua.user_id`, `siswa.user_id` masing-masing punya foreign key constraint DAN unique index sendiri (konvensi penamaan Laravel standar, dikonfirmasi langsung dari `information_schema`):

| Tabel | FK constraint | Unique index |
|---|---|---|
| `guru` | `guru_user_id_foreign` | `guru_user_id_unique` |
| `karyawan` | `karyawan_user_id_foreign` | `karyawan_user_id_unique` |
| `orang_tua` | `orang_tua_user_id_foreign` | `orang_tua_user_id_unique` |
| `siswa` | `siswa_user_id_foreign` | `siswa_user_id_unique` |

MySQL TIDAK mengizinkan drop kolom yang masih terikat foreign key tanpa drop FK-nya dulu. `$table->dropForeign(['user_id'])` (sintaks array Laravel, otomatis resolve ke nama konvensi di atas) HARUS dipanggil sebelum `$table->dropColumn('user_id')` untuk keempat tabel ini. Unique index biasanya otomatis ikut ter-drop bersama kolomnya di MySQL, tapi tulis `dropUnique` eksplisit juga untuk kejelasan dan portabilitas.

### Migration yang harus ditulis

Buat `database/migrations/{timestamp}_drop_legacy_identity_columns_from_role_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn([
                'user_id', 'nik', 'nik_hash', 'nama', 'jenis_kelamin', 'tempat_lahir',
                'tanggal_lahir', 'agama', 'kewarganegaraan', 'alamat_jalan', 'rt', 'rw',
                'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
                'no_hp', 'email',
            ]);
        });

        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn(['user_id', 'nik', 'nik_hash', 'nama', 'no_hp', 'email']);
        });

        Schema::table('orang_tua', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn(['user_id', 'nama_lengkap', 'nik', 'no_hp', 'email', 'alamat']);
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn(['user_id', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama']);
        });

        Schema::table('calon_murid', function (Blueprint $table) {
            $table->dropColumn([
                'nik', 'nik_hash', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir',
                'tanggal_lahir', 'agama', 'no_telepon', 'email_kontak',
            ]);
        });
    }

    public function down(): void
    {
        // Ireversibel secara data (kolom yang di-drop tidak bisa dikembalikan isinya).
        // down() sengaja tidak diimplementasikan penuh -- kalau butuh rollback,
        // pulihkan dari backup/snapshot sebelum migration ini, bukan lewat down().
        throw new \RuntimeException('Migration ini tidak reversibel. Restore dari backup jika perlu rollback.');
    }
};
```

**Sebelum menjalankan migration ini**, jalankan grep audit terakhir untuk memastikan tidak ada kode yang masih membaca/menulis kolom-kolom ini secara langsung (bukan lewat accessor `person`):

```bash
grep -rn "->nik\b\|->nama\b\|->jenis_kelamin\b\|->tempat_lahir\b\|->tanggal_lahir\b\|->agama\b\|->kewarganegaraan\b\|->no_hp\b\|->nama_lengkap\b\|->no_telepon\b\|->email_kontak\b" app/ --include="*.php" | grep -v "person->"
```

Kalau ada hasil di luar accessor definition itu sendiri (`app/Models/{Guru,Karyawan,OrangTua,Siswa,CalonMurid}.php`'s `getXAttribute()` methods, yang memang harus mengandung nama field ini), STOP dan laporkan — jangan lanjut drop kolom sebelum titik itu diperbaiki.

### Bersihkan model Eloquent (Guru, Karyawan, OrangTua, Siswa, CalonMurid)

Setelah migration dijalankan, `$fillable` dan `casts()` di 5 model ini masih mereferensikan kolom yang sudah tidak ada secara fisik — tidak menyebabkan error (Eloquent tidak mem-validasi `$fillable`/`casts()` terhadap skema aktual), tapi ini kode mati yang membingungkan. Bersihkan:

- **`app/Models/Guru.php`**: `$fillable` sekarang jadi `['person_id', 'lembaga_id', 'nuptk', 'nip', 'jenis_ptk', 'status_kepegawaian', 'golongan_pangkat', 'tmt_tugas', 'tmt_pns', 'status_aktif', 'kapasitas_kasus_aktif']`. Hapus `'nik' => 'encrypted'` dari `casts()` (sisakan `tanggal_lahir`/`tmt_tugas`/`tmt_pns` date casts kalau masih ada di sana — cek dulu, kemungkinan `tanggal_lahir` cast sudah tidak relevan lagi karena kolomnya di-drop, HANYA `tmt_tugas`/`tmt_pns` yang tetap ada sebagai kolom `guru` sendiri).
- **`app/Models/Karyawan.php`**: `$fillable` jadi `['person_id', 'yayasan_id', 'lembaga_id', 'jenis_karyawan_id', 'status_aktif', 'kapasitas_kasus_aktif']`.
- **`app/Models/OrangTua.php`**: `$fillable` jadi `['person_id', 'pekerjaan']`.
- **`app/Models/Siswa.php`**: `$fillable` jadi `['person_id', 'lembaga_id', 'kelas_id', 'calon_murid_id', 'pendaftaran_asal_id', 'sumber_data', 'nis', 'nisn', 'status']`. Hapus `tanggal_lahir` date cast dari `casts()` (kolomnya sudah di-drop) — TAPI JANGAN hapus `sumber_data`/`status` enum casts, itu tetap kolom asli `siswa`.
- **`app/Models/CalonMurid.php`**: `$fillable` jadi `['person_id', 'yayasan_id', 'no_kk', 'nisn', 'golongan_darah']` (SISAKAN `no_kk`/`golongan_darah`, itu tidak ikut dipindah ke `persons`). Bersihkan `casts()` sesuai — sisakan cast untuk `no_kk` (`encrypted`) kalau ada, hapus cast untuk kolom yang sudah di-drop.

Baca tiap file SEBELUM edit untuk memastikan tidak ada bagian lain (relasi, accessor `person()`, method lain) yang ikut terganggu — jangan asumsikan struktur file dari ringkasan ini.

### Verifikasi Task 28

1. `php artisan migrate` (menjalankan migration drop-column di atas terhadap skema yang sudah ada).
2. `php artisan test --compact` — HARUS 0 failed (baseline sebelum Task 28: 2530 passed, 4 skipped, 0 failed — angka ini TIDAK BOLEH turun).
3. `php artisan migrate:fresh --seed` — verifikasi dari nol juga berjalan bersih (dev-stage, aman dijalankan).
4. `php artisan test --compact` sekali lagi setelah `migrate:fresh --seed`, untuk memastikan urutan migrasi dari nol (termasuk backfill/verify-backfill/NOT-NULL/drop-column semuanya berjalan berurutan dengan benar) tidak punya masalah.
5. Commit sebagai 1-2 commit terpisah (migration+model cleanup bisa jadi 1 commit) dengan pesan dimulai `feat(identity):` atau `refactor(identity):`.

## Bagian 2: Squash Seluruh Migration via `schema:dump --prune`

**Baru dikerjakan SETELAH Bagian 1 selesai dan full suite hijau.** Proyek ini punya 138 file migration total (bukan cuma identity-related) — `schema:dump --prune` menyatukan SEMUANYA, bukan cuma yang identity, karena tujuannya "satu sumber kebenaran skema" untuk seluruh app.

1. Pastikan working tree bersih dan semua migration (termasuk Task 28 di atas) sudah diterapkan ke database lokal (`php artisan migrate` — pastikan `migrations` table mencerminkan state final).
2. Jalankan `php artisan schema:dump --prune`. Command ini:
   - Men-dump skema database SAAT INI (struktur tabel + data tabel `migrations` sendiri) ke `database/schema/mysql-schema.sql` (nama file tergantung driver DB — MySQL di project ini).
   - **`--prune` menghapus SEMUA file migration lama** di `database/migrations/` setelah dump berhasil dibuat.
3. **Verifikasi krusial — jangan percaya begitu saja**: setelah prune, jalankan `php artisan migrate:fresh --seed` dari kondisi database kosong. Laravel otomatis me-load `database/schema/mysql-schema.sql` dulu (kalau ada), baru menjalankan migration yang tersisa (harusnya nol, karena semua di-prune) dan seeder. Kalau proses ini gagal atau skema hasil load berbeda dari sebelum squash, JANGAN lanjut — investigasi dulu (jangan buang schema dump lama sebelum yakin yang baru benar; commit schema dump dulu di git sehingga bisa dibandingkan/rollback lewat git kalau perlu).
4. Bandingkan skema sebelum dan sesudah squash secara eksplisit — sebelum menjalankan `schema:dump`, catat `SHOW CREATE TABLE` untuk minimal 5-10 tabel representatif (termasuk `persons` dan kelima tabel role, plus 2-3 tabel dari domain lain seperti `tagihan`/`kasus`) ke file sementara; setelah `migrate:fresh` dari schema dump baru, jalankan `SHOW CREATE TABLE` yang sama dan diff manual untuk memastikan tidak ada kolom/index/FK yang hilang selama proses dump-dan-load. Ini menggantikan kebutuhan "baca ulang 138 file migration satu-satu" — cukup verifikasi hasil akhirnya identik, karena `schema:dump` adalah operasi mekanis Laravel yang sudah teruji, bukan tulisan tangan yang rawan salah.
5. Jalankan `php artisan test --compact` sekali lagi setelah squash — HARUS tetap 0 failed dengan jumlah yang SAMA PERSIS seperti sebelum squash (`schema:dump` seharusnya 100% tidak mengubah perilaku aplikasi, murni housekeeping file).
6. Commit: `database/schema/mysql-schema.sql` (file baru) + penghapusan seluruh 138 file migration lama, dalam 1 commit dengan pesan jelas, misal: `chore(migrations): squash all migrations into a single schema dump via schema:dump --prune`.

**Catatan efek samping yang perlu diketahui** (bukan bug, tapi perilaku Laravel yang perlu dipahami sebelum lanjut):
- Setelah squash, migration BARU yang ditulis ke depannya akan dianggap "migration setelah titik dump ini" — Laravel tidak menjalankan ulang migration lama yang sudah di-prune, sehingga skema dump JADI baseline permanen. Kalau ada kebutuhan menambah kolom di masa depan, itu tetap migration baru yang normal seperti biasa.
- Kalau proyek ini juga jalan di lingkungan CI/testing yang punya cache/artifact skema lama, pastikan environment tersebut juga rebuild dari schema dump baru (biasanya otomatis kalau CI selalu `migrate:fresh`/`migrate` dari kosong, tapi worth dicek).

## Project Rules gate

Buka `.ai/rules/index.md`, baca rule file untuk `database/migrations/**` dan `app/Models/**` sebelum menyentuh apa pun.

## Kalau menemukan sesuatu yang tidak sesuai

STOP, jangan menebak. Kalau `schema:dump --prune` menghasilkan sesuatu yang mencurigakan (skema berbeda dari yang diharapkan, test yang tadinya hijau jadi merah setelah squash), JANGAN paksa lanjut atau "perbaiki" test-nya — itu tanda squash-nya sendiri yang bermasalah, bukan test-nya. Laporkan ke user dengan detail SHOW CREATE TABLE before/after yang berbeda.

## Wajib di akhir: Handoff Log

Tulis `.agents/logs/2026-09-01-identity-v1-task28-schema-squash.md` (format sama seperti `.agents/logs/2026-08-29-identity-v1-person-master-entity-tasks-11-27.md`) mencakup: kolom yang di-drop per tabel (konfirmasi final), hasil `php artisan test --compact` sebelum dan sesudah Task 28, sebelum dan sesudah squash (angka pasti), commit hash untuk kedua bagian, dan konfirmasi `database/schema/mysql-schema.sql` ter-commit dengan benar.

## Definisi selesai

- Task 28: 5 tabel role tidak lagi punya kolom identitas duplikat, hanya `person_id` + kolom role-spesifik. Full suite tetap 0 failed dengan jumlah SAMA seperti sebelum Task 28 (2530 passed, 4 skipped).
- Squash: `database/migrations/` berisi hanya file-file BARU (kalau ada, seharusnya nol untuk saat ini) plus tidak ada lagi 138 file lama; `database/schema/mysql-schema.sql` ter-commit; `migrate:fresh --seed` dari kosong berhasil dan menghasilkan skema yang diverifikasi identik (via `SHOW CREATE TABLE` diff) dengan skema sebelum squash; full suite tetap hijau dengan jumlah sama.
