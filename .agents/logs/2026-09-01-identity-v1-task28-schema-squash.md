# Handoff Log: Identity v1 Task 28 & Migration Schema Squash

- **Tanggal:** 2026-09-01
- **Branch:** `identity-v1`
- **Spec Terkait:** [`.agents/specs/2026-08-29-identity-v1-person-master-entity.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-29-identity-v1-person-master-entity.md)
- **Plan Terkait:** [`.agents/plans/2026-08-29-identity-v1-person-master-entity.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-29-identity-v1-person-master-entity.md)
- **Kickoff Terkait:** [`.agents/kickoff-2026-09-01-identity-v1-task28-drop-columns-schema-squash.md`](file:///d:/laragon/www/pintera-app/.agents/kickoff-2026-09-01-identity-v1-task28-drop-columns-schema-squash.md)

---

## 1. Apa yang dikerjakan

1. **Bagian 1: Task 28 (Drop Physical Legacy Identity Columns from 5 Role Tables)**
   - Menghapus 45 kolom identitas lama dari 5 tabel role melalui migrasi definitif:
     - `guru` (18 kolom): `user_id`, `nik`, `nik_hash`, `nama`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `kewarganegaraan`, `alamat_jalan`, `rt`, `rw`, `desa_kelurahan`, `kecamatan`, `kabupaten_kota`, `provinsi`, `kode_pos`, `no_hp`, `email`.
     - `karyawan` (6 kolom): `user_id`, `nik`, `nik_hash`, `nama`, `no_hp`, `email`.
     - `orang_tua` (6 kolom): `user_id`, `nama_lengkap`, `nik`, `no_hp`, `email`, `alamat`.
     - `siswa` (6 kolom): `user_id`, `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`.
     - `calon_murid` (9 kolom): `nik`, `nik_hash`, `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `no_telepon`, `email_kontak`. Menjaga `no_kk` dan `golongan_darah` sebagai data spesifik formulir SPMB.
   - Membersihkan model (`Guru`, `Karyawan`, `OrangTua`, `Siswa`, `CalonMurid`):
     - Memperbarui `$fillable` dan `casts()` agar hanya berisi kolom spesifik role masing-masing.
     - Mengubah relasi `user()` pada model role menjadi `hasOneThrough(User::class, Person::class, 'id', 'id', 'person_id', 'user_id')`.
     - Menambahkan accessor fallback `getUserIdAttribute(): ?int { return $this->person?->user_id; }` untuk backward-compatibility query/akses properti `$model->user_id`.
   - Membersihkan factories (`GuruFactory`, `KaryawanFactory`, `OrangTuaFactory`, `SiswaFactory`, `CalonMuridFactory`):
     - Menambahkan `afterMaking` unsets agar pemanggilan factory dengan atribut identitas lama tetap terakomodasi di dalam closure `person_id` tanpa mencoba melakukan raw insert kolom fisik yang sudah dihapus.
   - Memperbarui controller, services, dan seeder yang sebelumnya masih membaca/menulis `user_id` atau nama langsung ke tabel role (`LembagaController`, `JadwalPelajaranController`, `SiswaOrangTuaController`, `SiswaSeeder`, `EssentialUserSeeder`, `OrangTuaKaryawanSeeder`, `JadwalPelajaranSeeder`, `SesiPembelajaranSeeder`, `KehadiranSdmDemoSeeder`, `AsesmenSeeder`, dll).
   - Menghapus tes transisional backfill yang sudah obsolete (`tests/Feature/Identity/BackfillPersonsFromRoleTablesTest.php`).

2. **Bagian 2: Migration Schema Squash (`php artisan schema:dump --prune`)**
   - Menjalankan `php artisan schema:dump --prune` dengan mysqldump.
   - Mengonsolidasi seluruh 138 file migrasi lama menjadi 1 file canonical schema dump: [`database/schema/mysql-schema.sql`](file:///d:/laragon/www/pintera-app/database/schema/mysql-schema.sql).
   - Memverifikasi fidelity skema sebelum dan sesudah dump/restore via script `SHOW CREATE TABLE` untuk 9 tabel inti (`persons`, `guru`, `karyawan`, `orang_tua`, `siswa`, `calon_murid`, `tagihan`, `kasus`, `users`) — 100% cocok.
   - Menghapus 3 file tes transisional lama yang bergantung langsung pada file migrasi spesifik yang sudah di-prune (`LembagaIuranMigrationTest.php`, `TagihanBackfillTest.php`, `BackfillSubjekPenilaianMigrationTest.php`).
   - Menjalankan `php artisan migrate:fresh --seed` — 42/42 seeders sukses.
   - Menjalankan seluruh test suite — **2517 passed, 0 failed, 0 errors** (100% Green).

---

## 2. Keputusan penting yang diambil

1. **Eksplisit Memilih `schema:dump --prune` dibanding Penulisan Manual Create Migration:**
   - Sesuai persetujuan eksplisit user, pendekatan bawaan Laravel `schema:dump --prune` digunakan karena menjamin zero-risk human error terhadap indeks, foreign keys, cascade triggers, dan column collations.
2. **Penanganan `user_id` pada Role Model vs `Person`:**
   - `user_id` sekarang secara fisik hanya berada pada tabel `persons`.
   - Untuk menjaga kompatibilitas kode aplikasi yang memanggil `$siswa->user` atau `$guru->user`, relasi `hasOneThrough` via `Person` dipasang di semua role model.
   - Di level controller seperti `LembagaController` ketika melakukan rename cascade username, pemanggilan via `$siswa->person?->user?->update(['username' => ...])` digunakan untuk melewati `TenantScope` secara eksplisit dan aman.
3. **Pembersihan Tes Transisional Terkait Migrasi:**
   - Tes seperti `TagihanBackfillTest`, `LembagaIuranMigrationTest`, dan `BackfillSubjekPenilaianMigrationTest` yang meng-require file migrasi lama yang di-prune dihapus karena tujuannya adalah memverifikasi skrip backfill sekali jalan saat fitur tersebut pertama kali dikembangkan.

---

## 3. Hal yang masih perlu direview manusia / Claude

1. **Git State Saat Ini:**
   - Branch: `identity-v1`
   - Commit history terbaru:
     - `fd1d5acb` `feat(identity): drop legacy identity columns from role tables and clean models (Task 28)`
     - `9cb094df` `chore(migrations): squash all migrations into a single schema dump via schema:dump --prune`
     - `8846f282` `docs(plan): mark Task 28 complete in identity-v1 plan`
   - Working tree: clean.
2. **Verifikasi Lingkungan Lain:**
   - Developer lain / CI yang menjalankan `php artisan migrate:fresh --seed` akan otomatis memuat dari `database/schema/mysql-schema.sql`. Pastikan client `mysql` / `mysqldump` tersedia di PATH server/CI jika menggunakan native schema dump loader Laravel.
3. **Status Keseluruhan:**
   - Seluruh Task 1 s/d Task 28 dari plan `identity-v1` telah **SELESAI 100%**.
