# Handoff Log: Audit Sistematis Akademik Tahap 2 — Kelompok A (Kritis)

**Tanggal**: 2026-08-27  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-27-akademik-audit-2-kelompok-a.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-audit-2-kelompok-a.md)  
**Plan**: [`.agents/plans/2026-08-27-akademik-audit-2-kelompok-a.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-audit-2-kelompok-a.md)  
**Status**: ✅ **100% COMPLETE & VERIFIED** (Full Suite: 2331 passed, 4 skipped, 0 failed — 6381 assertions)

---

## 1. Apa yang Dikerjakan

Menutup 3 temuan kritis dari hasil audit sistematis Akademik tahap 2 pada area yang belum pernah diaudit sebelumnya:

1. **Task 1: Fix widget "Jadwal Hari Ini" guru (Filter Semester Aktif)**
   - Menambahkan query scope `scopeSemesterAktif(Builder $query)` pada model [`app/Models/JadwalPelajaran.php`](file:///d:/laragon/www/pintera-app/app/Models/JadwalPelajaran.php) sebagai guard default standar untuk semua consumer jadwal saat ini.
   - Menerapkan `->semesterAktif()` pada query `$jadwalHariIni` di [`app/Http/Controllers/Admin/DashboardController.php`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/DashboardController.php) sehingga widget guru tidak mencampur jadwal semester/tahun ajaran lama.
   - Menjaga integritas data: **TIDAK ADA penghapusan jadwal lama** (menghindari cascade-delete riwayat presensi siswa via FK `sesi_pembelajaran.jadwal_pelajaran_id`).
   - Menambahkan 2 test di [`tests/Feature/DashboardTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/DashboardTest.php) (seluruh 17 test di file tersebut pass).
   - Commit: `0fc569e1` (`fix(akademik): widget jadwal hari ini guru filter semester aktif`).

2. **Task 2: Resync manual drift `kelas.kurikulum` / `kelas.fase_id`**
   - Membuat Action [`app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php) yang menyediakan:
     - `hitungDiff(int $lembagaId, int $tahunAjaranId)`: Menghitung perbedaan live antara nilai tersimpan di database dengan resolver (`KurikulumAssignmentResolver` & `FaseDefaultResolver`), menangani `KurikulumAssignmentNotFoundException` dengan aman tanpa crash.
     - `terapkan(array $kelasIds)`: Menerapkan sinkronisasi dalam `DB::transaction` dengan nilai yang dihitung ulang di sisi server (anti-tampering).
   - Membuat Controller [`app/Http/Controllers/Admin/ResyncKurikulumFaseController.php`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/ResyncKurikulumFaseController.php) dengan proteksi otorisasi `kurikulum-assignment.view` & `kurikulum-assignment.edit`, multi-tenant scope check (`authorizeScope`), dan cross-tenant validation.
   - Mendaftarkan routes di [`routes/admin/akademik-master.php`](file:///d:/laragon/www/pintera-app/routes/admin/akademik-master.php) (`admin.kurikulum-assignment.resync` [GET] & `admin.kurikulum-assignment.resync.apply` [POST]).
   - Membuat view [`resources/views/admin/kurikulum-assignment/resync.blade.php`](file:///d:/laragon/www/pintera-app/resources/views/admin/kurikulum-assignment/resync.blade.php) dan menambahkan tombol navigasi di [`resources/views/admin/kurikulum-assignment/index.blade.php`](file:///d:/laragon/www/pintera-app/resources/views/admin/kurikulum-assignment/index.blade.php).
   - Membuat test lengkap di [`tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php) (5 test) dan [`tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php) (3 test), serta memastikan snapshot test [`tests/Feature/Akademik/KelasKurikulumSnapshotTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/KelasKurikulumSnapshotTest.php) tetap 100% pass (5 test).
   - Commit: `4e98501b` (`feat(akademik): aksi resync manual drift kurikulum/fase kelas`).

3. **Task 3: Validasi nama ekskul di catatan wali kelas terhadap master data lembaga**
   - Menambahkan validasi `Rule::in` pada `ekstrakurikuler.*.nama` di [`app/Http/Requests/Akademik/StoreCatatanWaliKelasRequest.php`](file:///d:/laragon/www/pintera-app/app/Http/Requests/Akademik/StoreCatatanWaliKelasRequest.php) yang di-scope strictly per lembaga siswa (`$siswa->lembaga_id`).
   - Menambahkan data `ekskulOptions` pada method `edit()` di [`app/Http/Controllers/Guru/RaporController.php`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Guru/RaporController.php).
   - Mengubah input teks bebas menjadi `<select>` di [`resources/views/portals/guru/rapor/catatan/edit.blade.php`](file:///d:/laragon/www/pintera-app/resources/views/portals/guru/rapor/catatan/edit.blade.php) dengan backward-compatibility yang menampilkan label `(tidak terdaftar lagi)` jika catatan lama memuat ekskul yang sudah dinonaktifkan dari master data.
   - Membuat unit/feature test di [`tests/Feature/Akademik/CatatanWaliKelasEkstrakurikulerValidationTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/CatatanWaliKelasEkstrakurikulerValidationTest.php) (4 test) dan menyelaraskan fixture test di [`tests/Feature/Guru/RaporControllerTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Guru/RaporControllerTest.php) (seluruh 17 test pass).
   - Commit: `e87a174c` (`fix(akademik): validasi nama ekskul di catatan wali kelas terhadap master data lembaga`).

4. **Task 4: Dokumentasi Roadmap**
   - Memperbarui [`PETA_PENGEMBANGAN.md`](file:///d:/laragon/www/pintera-app/PETA_PENGEMBANGAN.md) dengan mencatat penyelesaian Kelompok A (Kritis) dari Audit Sistematis Akademik Tahap 2 dan status Kelompok B (Kenaikan Kelas UX) serta Kelompok C (RPP reporting + test coverage).
   - Commit: `a2168ea8` (`docs: catat penyelesaian Kelompok A audit sistematis tahap 2 akademik`).

---

## 2. Keputusan Penting yang Diambil

1. **Jadwal Lama Tidak Dihapus**:
   - `JadwalPelajaran` historis tetap dipertahankan karena tabel `sesi_pembelajaran` memiliki relasi `cascadeOnDelete()` ke `jadwal_pelajaran_id`. Menghapus baris jadwal lama akan melenyapkan riwayat kehadiran/presensi siswa di kelas dan semester lampau. Solusi difokuskan murni pada query filter (`scopeSemesterAktif`).
2. **Snapshot Tetap Beku, Resync Dibatasi Hanya Melalui Tool Eksplisit**:
   - `UpdateKelasAction`, `UpdateKurikulumAssignmentAction`, dan `UpdateFaseDefaultMappingAction` tetap tidak melakukan auto-cascade / mutasi otomatis. Resync hanya dijalankan oleh admin melalui interface koreksi `admin.kurikulum-assignment.resync`.
3. **Anti-Tampering pada Resync**:
   - Controller `ResyncKurikulumFaseController::apply` hanya menerima array `kelas_ids` dan memverifikasi kepemilikan lembaga, sementara nilai baru kurikulum dan fase dihitung ulang langsung di server via resolver.
4. **Validasi Ulang Menyeluruh pada Ekskul**:
   - Tidak ada partial skip pada validasi array `ekstrakurikuler`. Setiap kali form catatan wali kelas disubmit, semua baris ekskul wajib valid terhadap master data aktif lembaga saat ini.
5. **Fixture `Fase::firstOrCreate`**:
   - Karena `Fase` merupakan tabel master statis tanpa factory class, pengujian menggunakan `Fase::firstOrCreate(['kode' => 'a'], ['nama' => 'Fase A', 'urutan' => 1])`.

---

## 3. Hal yang Perlu Direview / Catatan Lanjutan

1. **Kelompok B & C Audit Tahap 2**:
   - **Kelompok B (Kenaikan Kelas UX Safety-Net)**: Validasi kecocokan kurikulum kelas tujuan, saran otomatis "lulus" di tingkat akhir, guard `bentuk_pendidikan` — ditunda untuk spec/plan terpisah.
   - **Kelompok C (RPP Reporting & Test Coverage)**: Reporting kurikulum, validasi kelas-semester, regression test cross-tenant IDOR — ditunda untuk spec/plan terpisah.
   - **Poin #10 (Notifikasi Akademik)**: Dicatat sebagai backlog fitur terpisah.
2. **Git State**:
   - Branch: `akademik-v2`
   - Unmerged commits on branch:
     - `0fc569e1`: `fix(akademik): widget jadwal hari ini guru filter semester aktif`
     - `4e98501b`: `feat(akademik): aksi resync manual drift kurikulum/fase kelas`
     - `e87a174c`: `fix(akademik): validasi nama ekskul di catatan wali kelas terhadap master data lembaga`
     - `a2168ea8`: `docs: catat penyelesaian Kelompok A audit sistematis tahap 2 akademik`

---

## 4. Hasil Verifikasi Akhir

- **Full Suite Run**: `php artisan test --compact`
- **Output**: `Tests: 4 skipped, 2331 passed (6381 assertions)`
- **Duration**: ~607s
- **Status**: 0 Failures / 100% Green
