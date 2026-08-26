# Handoff Log: Fondasi Akademik Multi-Jenjang — Sprint 1 (Subjek Penilaian Polymorphic)

**Tanggal Execution**: 2026-08-26  
**Branch Kerja**: `akademik-v2`  
**Spec Document**: [.agents/specs/2026-08-26-akademik-multi-jenjang-fondasi.md](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-26-akademik-multi-jenjang-fondasi.md)  
**Plan Document**: [.agents/plans/2026-08-26-akademik-multi-jenjang-sprint1.md](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-26-akademik-multi-jenjang-sprint1.md)  

---

## 1. Apa yang dikerjakan

Seluruh 6 Task pada Implementation Plan Sprint 1 telah dieksekusi secara berurutan dan terverifikasi 100% via pengujian otomatis (Pest) dan pemrosesan migrasi `migrate:fresh --seed`.

### Detail Per Task & Commit History
1. **Task 1: Fondasi Model `ElemenCp`, Contract, dan Morph Map** (`feea19b8`)
   - Dibuat tabel `elemen_cp` (`kode`, `nama`, `no_urut`) beserta model `ElemenCp`.
   - Dibuat interface `SubjekPenilaian` dan helper `SubjekPenilaianKey::dari(Model $subjek)`.
   - Didaftarkan morph map di `AppServiceProvider`: `'mata_pelajaran' => MataPelajaran::class`, `'elemen_cp' => ElemenCp::class`.
   - Dibuat `ElemenCpFactory` dan `ElemenCpSeeder` (idempotent, 3 elemen CP PAUD Kurikulum Merdeka).
   - Unit Test: 4 test case lulus.

2. **Task 2: Migrasi Kolom `subjek_type` dan `subjek_id` Nullable** (`38f4113d`)
   - Dibuat migrasi `2026_08_26_100100_add_subjek_columns_to_komponen_penilaian_and_asesmen.php`.
   - Menambahkan kolom `subjek_type` & `subjek_id` (nullable) pada tabel `komponen_penilaian` dan `asesmen`.
   - Unit Test: 1 test case lulus.

3. **Task 3: Migrasi Data Backfill Zero-Downtime** (`57186661`)
   - Dibuat migrasi `2026_08_26_100200_backfill_subjek_penilaian.php`.
   - Backfill data eksisting dengan precedensi `elemen_cp` > `mata_pelajaran_id` serta guard fail-fast pada data unmapped.
   - Feature Test: 4 test case lulus.

4. **Task 4: Refactor Layer Domain & Http ke Polymorphic Subjek** (`54155742`)
   - Model `KomponenPenilaian` & `Asesmen` menggunakan relasi `morphTo('subjek')`.
   - Update Action, DTO, Form Request, Controller (`Admin` & `Guru`), serta Service (`DashboardStatsService`, `RaporCalculationService`, `RaporPdfDataBuilder`, `CapaianKompetensiGenerator`).
   - Composite key pada service rapor diperbarui dari format `mata_pelajaran_id` murni menjadi format `subjek_type:subjek_id` (e.g. `'mata_pelajaran:5'` vs `'elemen_cp:5'`) untuk mencegah bentrok ID antar tipe subjek.
   - Feature Test: `SubjekTenantValidationTest` & `RaporCalculationCompositeKeyTest` lulus.

5. **Task 5: Refactor UI Blade & Form Toggle Subjek** (`e2bda301`)
   - Refactor 11 berkas Blade view (`siswa.blade.php`, `orang-tua.blade.php`, serta portal Guru & Lembaga).
   - Ditambahkan UI toggle (radio button `subjek_type`) pada form create/edit `komponen-penilaian` dan `asesmen` di portal Guru dan Lembaga.
   - Feature Test: `KomponenPenilaianElemenCpUiTest` lulus.

6. **Task 6: Cleanup Refactor Test Suite, Seeders, & Drop Legacy Column** (`45ad862f`)
   - Refactor seluruh test suite eksisting (`RaporCalculationServiceTest`, `AsesmenControllerTest`, `KomponenPenilaianControllerTest`, `KomponenPenilaianCrudTest`, `RaporControllerTest`, `CapaianKompetensiGeneratorTest`, `RaporPdfDataBuilderTest`, `AkademikTenantScopeTest`, `RaporApprovalActionsTest`, `GenerateNarasiPerkembanganActionTest`).
   - Refactor seeders (`KomponenPenilaianSeeder`, `AsesmenSeeder`, `NilaiSiswaSeeder`).
   - Dibuat dan dijalankan migrasi `2026_08_26_100300_drop_mata_pelajaran_id_from_komponen_penilaian_and_asesmen.php`.
   - Dipastikan zero rujukan kolom `mata_pelajaran_id` pada `app/` dan `database/` untuk `komponen_penilaian` dan `asesmen`.

---

## 2. Keputusan Penting yang Diambil

1. **Skema Migrasi 2-Fase (Zero-Downtime Pattern)**:
   - Kolom polymorphic ditambahkan sebagai `nullable` terlebih dahulu (Task 2).
   - Data dibackfill dengan query `DB::table()` murni tanpa bergantung pada Eloquent Model (Task 3).
   - Kolom legacy `mata_pelajaran_id` baru di-drop secara aman pada Task 6 setelah seluruh layer aplikasi dan test suite diperbarui.

2. **Perlakuan Tenant Scope Polymorphic Subjek**:
   - `MataPelajaran` terikat tenant (`lembaga_id`). Form Request dan Action memvalidasi bahwa `subjek_id` milik lembaga yang sama dengan `semester_id`.
   - `ElemenCp` bersifat acuan nasional/global (tanpa `lembaga_id`), sehingga validasi lembaga pada `ElemenCp` dilompati secara tepat.

3. **Format Composite Key Rekap Nilai**:
   - Penggunaan string `subjek_type:subjek_id` (misal: `mata_pelajaran:12` vs `elemen_cp:12`) pada `RaporCalculationService` dan `RaporPdfDataBuilder` memastikan bahwa ketika `MataPelajaran` dan `ElemenCp` memiliki nilai ID numerik yang sama (sama-sama ID `12`), rekap nilai tidak saling tertimpa atau bentrok.

---

## 3. Hasil Verifikasi Review & State Akhir

### Resolution 4 Temuan Review:
1. **Temuan #1 (DashboardTest.php)**: Refaktor `tests/Feature/DashboardTest.php` baris 162 & 190 ke `subjek_type` & `subjek_id`. LULUS (13/13 tests).
2. **Temuan #2 (Drop Column elemen_cp & NOT NULL Contraint)**: Migrasi `2026_08_26_100300_drop_mata_pelajaran_id_from_komponen_penilaian_and_asesmen.php` diperbarui untuk men-drop `elemen_cp` serta meng-enforce `NOT NULL` pada `subjek_type` & `subjek_id`.
3. **Temuan #3 (Revert Permission Migration)**: Migrasi `2026_07_12_073217_create_permission_tables.php` dikembalikan ke enum original (`['yayasan', 'lembaga', 'diri_sendiri']`).
4. **Temuan #4 (Dokumentasi Helper)**: Dokumentasi helper diperbarui ke `SubjekPenilaianKey::dari(Model $subjek)`.
5. **PAUD Rapor Template Fix**: `resources/views/pdf/rapor/paud.blade.php` diperbarui menggunakan polymorphic `ElemenCp` check.

### Verifikasi Full Test Suite (Pest):
- **Command Executed**: `php vendor/bin/pest`
- **Hasil Execution**: **2,151 passed, 0 failed, 0 skipped** (509.83s).

### Git Commit History (Branch `akademik-v2`):
```text
f7e4a4b9 fix(akademik): selesaikan temuan review - DashboardTest refactor, drop elemen_cp, NOT NULL subjek columns, & PAUD rapor pdf fix
45ad862f refactor(akademik): selesaikan migrasi subjek polymorphic & drop legacy column mata_pelajaran_id
e2bda301 feat(akademik): tambah UI toggle subjek elemen_cp pada form komponen & asesmen
54155742 refactor(akademik): geser Model/Action/DTO/Request/Controller/Service ke subjek polymorphic
57186661 feat(akademik): migration backfill subjek_type/subjek_id dgn precedence elemen_cp > mata_pelajaran_id
38f4113d feat(akademik): tambah kolom subjek_type/subjek_id nullable ke komponen_penilaian & asesmen
feea19b8 feat(akademik): tambah ElemenCp, SubjekPenilaian interface, SubjekPenilaianKey helper, morph map
```

---

## 4. Hal yang Masih Perlu Direview Manusia / Claude

- Seluruh 4 temuan review dari verifikasi langsung telah 100% selesai dan teruji tanpa ada test yang gagal atau skipped.
- Branch `akademik-v2` berada dalam kondisi hijau sempurna dan siap dilanjutkan ke **Sprint 2** sesuai roadmap spec `.agents/specs/2026-08-26-akademik-multi-jenjang-fondasi.md`.

