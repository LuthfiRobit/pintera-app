# Spec: Perbaikan 33 Test Seeder + 3 Seeder Cacat Pasca Penyusutan ke 1 Lembaga (SD)

- **Branch**: `rbac-v2`
- **Baseline commit**: `d5a6dbd`
- **Tanggal**: 24 Agustus 2026
- **Konteks**: ditemukan saat review independen eksekusi plan penyusutan seeder (`.agents/plans/2026-08-24-seeder-susutkan-1-lembaga-sd.md`) — full test suite menghasilkan **45 test gagal** di 33 file `tests/Unit/*SeederTest.php` + `tests/Feature/GelombangJalurRestrictionTest.php`. Seeder aplikasi itu sendiri sudah terverifikasi BERSIH (3 review independen + `migrate:fresh --seed` sukses) — kegagalan ini murni test yang masih meng-hardcode ekspektasi dunia lama (4 lembaga).

## 1. Context & Problem

Audit penuh 33 file test (baca lengkap tiap file) menemukan 2 kategori masalah:

1. **Test hardcode dunia-4-lembaga** — angka count (`Lembaga::count()->toBe(4)`), lookup NPSN (`20223311`/`20223322`/`20223344`), lookup email (`.kb@`/`.tk@`/`.smp@demo.test`), nama kelas (`VII-A`), frasa judul (`"across all K-9 institutions"`).
2. **3 seeder APLIKASI (bukan test) belum diretarget** oleh plan penyusutan sebelumnya — `GuruJabatanTambahanSeeder.php`, `RiwayatPendidikanGuruSeeder.php`, `SertifikasiGuruSeeder.php` masih hardcode email guru SMP (`budi.santoso@`, `siti.rahmawati@`, `andi.wijaya@demo.test`) yang sudah tidak pernah dibuat `UserSeeder.php`. Guard `if (!$user) { warn; continue; }` mencegah crash, tapi diam-diam skip 2-3 dari data yang seharusnya dibuat.

**Keputusan user**: perbaiki KEDUANYA — seeder aplikasi direfactor mengikuti pola yang sama seperti 20 task sebelumnya (retarget ke guru SD), BUKAN sekadar menyesuaikan test supaya lolos dengan seeder yang masih cacat.

## 2. Nilai Dunia Baru (Referensi, Terverifikasi Langsung dari Kode + `migrate:fresh --seed`)

| Entitas | Lama (4 lembaga) | Baru (1 lembaga SD) |
|---|---|---|
| `Lembaga::count()` | 4 | 1 |
| `Guru::count()` | 12 | 15 |
| `User::count()` (dari `UserSeeder` saja) | 25 | 19 |
| `Kelas::count()` (×2 TA) | 30 | 24 |
| `Siswa::count()` | — | 336 |
| `PolaJam::count()` | 4 | 1 |
| `JamPelajaran::count()` | 168 | 49 |
| `MataPelajaran::count()` | 32 | 9 |
| `JenisTesMaster::count()` | 10 | 3 |
| `TahunAjaran::count()` | 8 | 2 |
| `Semester::count()` | 16 | 4 |
| `JenisTagihan::count()` | 8 | 2 |
| `CalonMurid::count()` | 16 | 4 |
| `Pendaftaran::count()` | 16 | 4 |
| `Tagihan::count()` | 12 | 3 |
| `TagihanItem::count()` | 12 | 3 |
| `Pembayaran::count()` | 12 | 3 |
| `SkPpdb::count()` | 4 | 1 |
| `SkemaCicilan::count()` | 4 | 1 |
| `Cicilan::count()` | 12 | 3 |
| `AkunPendaftar::count()` | 4 | 1 |

**Siswa pertama SD** (hasil algoritma `NAMA_DEPAN[counter%30]`×`NAMA_BELAKANG[counter%16]`, `counter=1`, `prefix='3333'`): NIS `3333001`, nama **"Muhammad Santoso"**, `jenis_kelamin` L.

**`KomponenPenilaianSeeder.php` untuk SD**: SELALU lewat cabang `seedGenericKomponen()` (kode `TP.1`, bobot 100) karena `npsn === '20223344'` tidak pernah cocok lagi — `seedSmpKomponen()` (kode `TP.1.1`, bobot 50) jadi dead code permanen (file ini TIDAK diedit, di luar scope, tapi test-nya HARUS disesuaikan ke perilaku aktual).

**`GelombangJalurSeeder.php`**: sekarang meretarget SD (bukan lagi SMP) jadi Gelombang 1 **restricted** ke jalur `['Reguler', 'Prestasi']` — kebalikan dari perilaku lama.

## 3. Keputusan Desain

1. **3 seeder aplikasi cacat diperbaiki dulu** (Task 1, prasyarat), BARU test-nya disesuaikan.
2. **Guru pengganti untuk 3 seeder itu**: dipilih dari 12 wali kelas SD yang sudah ada (`GuruSeeder.php`), bukan guru baru:
   - `GuruJabatanTambahanSeeder.php`: `siti.rahmawati@`→`sari.wulandari@` (Wali Kelas), `andi.wijaya@`→`agus.setiawan@` (Pembina Ekstrakurikuler).
   - `RiwayatPendidikanGuruSeeder.php`: `budi.santoso@`→`sari.wulandari@` (S1 PGSD — lebih pas untuk guru_kelas SD daripada gelar S1 Matematika SMP), `siti.rahmawati@`→`agus.setiawan@`, `andi.wijaya@`→`nita.kurniawati@`.
   - `SertifikasiGuruSeeder.php`: `budi.santoso@`→`sari.wulandari@`.
3. **Anchor siswa spesifik** (Aditya Pratama/NIS 2627001) diganti ke siswa pertama SD yang deterministik (Muhammad Santoso/NIS 3333001) — BUKAN diubah jadi assertion generik "nama tidak kosong", supaya test tetap presisi (anchor ke record yang benar-benar pasti ada, bukan assertion lemah).
4. **Test perbandingan lintas-lembaga** (`PembayaranSeederTest`, `PendaftaranSeederTest`, `TagihanSeederTest`, `GelombangJalurRestrictionTest`) — SEMUA dipertahankan sebagai test isolasi data, TAPI lembaga pembanding dibuat lewat `Lembaga::factory()->create()` LANGSUNG DI TEST tersebut, TIDAK bergantung pada `DatabaseSeeder`. Ini justru desain test yang lebih benar (tidak rapuh terhadap perubahan jumlah lembaga demo di masa depan).
5. **`GelombangJalurRestrictionTest`** — assertion dibalik total: SD (bukan SMP) yang sekarang restricted ke Reguler+Prestasi.
6. **Frasa judul test** (`"across all K-9 institutions"` dst) diperbarui HANYA di file yang sudah disentuh untuk alasan lain — TIDAK ada sweep kosmetik terpisah untuk file yang assertion-nya sendiri sudah benar tanpa perlu diedit.
7. **Zero-behavior-change untuk 3 seeder yang diperbaiki** — HANYA nama guru yang diganti, struktur data (`jenis_sertifikasi`, `jenjang_pendidikan`, dst) tetap sama.

## 4. Cakupan File (36 file total)

### 4.1 Seeder aplikasi (3 file, HARUS dikerjakan PERTAMA)
`GuruJabatanTambahanSeeder.php`, `RiwayatPendidikanGuruSeeder.php`, `SertifikasiGuruSeeder.php`.

### 4.2 Test MEKANIS (16 file — ganti angka/string literal, hapus assertion lembaga yang sudah tak ada, logic test tak berubah)
`LembagaSeederTest.php`, `AkunPendaftarSeederTest.php`, `CalonMuridSeederTest.php`, `CicilanSeederTest.php`, `EssentialUserSeederTest.php`, `JamPelajaranSeederTest.php`, `JenisTagihanSeederTest.php`, `LembagaDataPeriodikSeederTest.php`, `LembagaProfileSeedersTest.php`, `NominalTagihanJalurSeederTest.php`, `PolaJamSeederTest.php`, `PpdbConfigurationSeedersTest.php`, `SemesterSeederTest.php`, `SkPpdbSeederTest.php`, `SkemaCicilanSeederTest.php`, `TagihanItemSeederTest.php`, `TahunAjaranSeederTest.php`.

(Catatan: daftar ini 17 nama meski kategori disebut "16" pada riset awal — `LembagaSeederTest.php` sempat masuk kategori campuran, dikonfirmasi ulang MEKANIS murni saat baca detail, jadi total mekanis 17.)

### 4.3 Test PERLU-JUDGEMENT (16 file — skenario perlu direstrukturisasi, bukan sekadar ganti angka)
`GuruSeederTest.php`, `UserSeederTest.php`, `KelasSeederTest.php`, `SiswaSeederTest.php`, `GuruJabatanTambahanSeederTest.php`, `JenisTesMasterSeederTest.php`, `KomponenPenilaianSeederTest.php`, `MataPelajaranSeederTest.php`, `NilaiSiswaSeederTest.php`, `PembayaranSeederTest.php`, `PendaftaranSeederTest.php`, `PresensiSeederTest.php`, `RiwayatPendidikanGuruSeederTest.php`, `SertifikasiGuruSeederTest.php`, `TagihanSeederTest.php`, `GelombangJalurRestrictionTest.php` (Feature).

## 5. Definisi Selesai

1. `php artisan test tests/Unit tests/Feature/GelombangJalurRestrictionTest.php` — 0 failed.
2. Full suite `php artisan test` hijau (izin user dulu untuk full run).
3. `grep -rn "20223311\|20223322\|20223344\|budi.santoso\|siti.rahmawati\|andi.wijaya" database/seeders/*.php tests/Unit/*.php tests/Feature/GelombangJalurRestrictionTest.php` — KOSONG total.
4. 3 seeder aplikasi (`GuruJabatanTambahanSeeder`, `RiwayatPendidikanGuruSeeder`, `SertifikasiGuruSeeder`) diverifikasi via `migrate:fresh --seed` + tinker count — jumlah row SEKARANG mencakup guru SD pengganti, bukan silently skip.

## 6. Non-Goals

1. TIDAK menyentuh `KomponenPenilaianSeeder.php`/`GelombangJalurSeeder.php`/seeder generik lain — HANYA test-nya yang disesuaikan ke perilaku aktual.
2. TIDAK melakukan sweep kosmetik judul test di file yang tidak perlu diedit untuk alasan lain.
3. TIDAK mengubah `RoleSeeder.php`/`RolePermissionAssignmentSeeder.php` (scope RBAC v2 terpisah).
