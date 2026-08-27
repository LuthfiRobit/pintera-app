# Handoff Log: Audit Sistematis Akademik Tahap 2 — Kelompok C (RPP Reporting & Test Coverage)

**Tanggal**: 2026-08-27  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-27-akademik-audit-2-kelompok-c.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-audit-2-kelompok-c.md)  
**Plan**: [`.agents/plans/2026-08-27-akademik-audit-2-kelompok-c.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-audit-2-kelompok-c.md)  
**Status**: ✅ **100% COMPLETE & VERIFIED** (Full Suite: 2355 passed, 4 skipped, 0 failed — 6459 assertions)

---

## 1. Apa yang Dikerjakan

Menutup 3 item terakhir dari audit sistematis Akademik tahap 2 dan menjalankan checkpoint test full suite gabungan:

1. **Task 1: Badge & Filter Kurikulum di Daftar RPP**
   - Menambahkan parameter opsional `?string $kurikulum = null` di `ListRppAction::execute()` dengan filter `$query->whereHas('kelas', fn ($q) => $q->where('kurikulum', $kurikulum))`.
   - Mengupdate `RppController::index()` untuk membaca query param `kurikulum`, memvalidasi terhadap enum `KurikulumFramework::cases()`, fallback otomatis ke `null` untuk nilai yang tidak dikenal tanpa error/500, serta meneruskannya ke pemanggilan tunggal `execute()` sehingga konsisten baik pada full-page response maupun AJAX fragment.
   - Menambahkan badge kurikulum di [`resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php`](file:///d:/laragon/www/pintera-app/resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php) (tone `green` untuk Merdeka, `blue` untuk K13, dan `slate` "Belum Diketahui" untuk data legacy).
   - Menambahkan filter kurikulum di [`resources/views/portals/lembaga/akademik/rpp/index.blade.php`](file:///d:/laragon/www/pintera-app/resources/views/portals/lembaga/akademik/rpp/index.blade.php) pada Alpine `x-data="filters.kurikulum"` dan select input.
   - Membuat file test baru [`tests/Feature/Akademik/RppKurikulumReportingTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/RppKurikulumReportingTest.php) (4 test: scoped badge, filter Merdeka full-page + AJAX, filter K13, dan fallback invalid kurikulum).
   - Memverifikasi 9 test regresi di [`tests/Feature/Akademik/RppWorkflowTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/RppWorkflowTest.php) tetap hijau.
   - Commit: `5cde2f80` (`feat(akademik): badge dan filter kurikulum di daftar RPP`).

2. **Task 2: Validasi Konsistensi Kelas-Semester di Form RPP**
   - Menambahkan method `withValidator()` di [`app/Http/Requests/Akademik/StoreRppRequest.php`](file:///d:/laragon/www/pintera-app/app/Http/Requests/Akademik/StoreRppRequest.php) untuk memverifikasi `$kelas->tahun_ajaran_id === $semester->tahun_ajaran_id`.
   - Menambahkan method `withValidator()` di [`app/Http/Requests/Akademik/UpdateRppRequest.php`](file:///d:/laragon/www/pintera-app/app/Http/Requests/Akademik/UpdateRppRequest.php) untuk memverifikasi `$kelas->tahun_ajaran_id === $rpp->semester->tahun_ajaran_id`.
   - Membuat test baru [`tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php) (4 test: store invalid, store valid, update invalid, update valid).
   - Memverifikasi 9 test regresi di [`tests/Feature/Akademik/RppWorkflowTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/RppWorkflowTest.php) tetap hijau.
   - Commit: `a991d91e` (`feat(akademik): validasi konsistensi kelas-semester pada form RPP`).

3. **Task 3: Test Regresi Cross-Tenant IDOR Ekstrakurikuler**
   - Menambahkan 2 test di [`tests/Feature/Admin/LembagaRelationalManagementTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Admin/LembagaRelationalManagementTest.php) membuktikan bahwa request update/destroy `EkstrakurikulerLembaga` lintas-lembaga ditolak `404 Not Found` dan record di lembaga asal tetap utuh di database. Kode produksi `EkstrakurikulerController` terbukti sudah aman dan tidak perlu diubah.
   - Commit: `a1079ddc` (`test(akademik): tambah regresi cross-tenant IDOR ekstrakurikuler_lembaga`).

4. **Task 4: Dokumentasi Roadmap & Penutup Audit Tahap 2**
   - Memperbarui [`PETA_PENGEMBANGAN.md`](file:///d:/laragon/www/pintera-app/PETA_PENGEMBANGAN.md) mencatat penyelesaian Kelompok C dan penutupan seluruh rangkaian Audit Sistematis Akademik Tahap 2.
   - Commit: `0fe0c9c7` (`docs: catat penyelesaian Kelompok C, tutup audit sistematis tahap 2 akademik`).

---

## 2. Keputusan Penting yang Diambil

1. **Eager-loading RPP Reuse Tanpa Query Tambahan**:
   - `ListRppAction::execute()` sudah meng-eager-load `kelas`, sehingga badge kurikulum dibaca langsung dari `$rpp->kelas->kurikulum?->label()` tanpa perlu query N+1 tambahan.
2. **Penanganan Fallback Nilai Kurikulum Tidak Dikenal**:
   - `?kurikulum=foobar` tidak memicu exception / 500 dan tidak menghasilkan list kosong, melainkan di-fallback ke `null` (tanpa filter).
3. **Single Point Execution untuk Konsistensi AJAX / Full-Page**:
   - Filter kurikulum dilewatkan ke pemanggilan tunggal `$this->listRppAction->execute(...)` sebelum percabangan `if ($request->ajax())`, menjamin kedua jalur respon selalu sinkron.
4. **Validasi Relasional Melengkapi `exists:` Rule**:
   - `withValidator()` di `StoreRppRequest` dan `UpdateRppRequest` bertindak sebagai guard relasional tambahan di atas rule `exists:kelas,id` dan `exists:semester,id`. Rule dasar tetap dipertahankan.
5. **Fixture Database NOT NULL `file_size_bytes`**:
   - Kolom `file_size_bytes` pada tabel `rpp` bertipe integer NOT NULL, sehingga seluruh fixture test `Rpp::create` manual menyertakan `'file_size_bytes' => 1024` secara eksplisit.

---

## 3. Hal yang Perlu Direview / Catatan Lanjutan

1. **Audit Sistematis Akademik Tahap 2 Selesai Penuh**:
   - Kelompok A (Kritis: Widget Jadwal Guru, Resync Drift Kurikulum/Fase, Validasi Master Ekskul Catatan Wali Kelas).
   - Kelompok B (Kenaikan Kelas UX: Source of truth `BentukPendidikan::isTingkatAkhir()`, saran otomatis Lulus, live warning kurikulum).
   - Kelompok C (RPP Reporting & Validasi: Badge/filter kurikulum RPP, validasi kelas-semester RPP, test regresi IDOR ekskul).
2. **Poin #10 (Notifikasi Akademik)**:
   - Tetap tercatat sebagai backlog fitur terpisah di roadmap.
3. **Technical Debt `TD-AKADEMIK-003`**:
   - Penggunaan `BentukPendidikan` baru sebagai single source of truth dapat di-retrofit ke 4 lokasi legacy di masa mendatang (`StoreFaseDefaultMappingRequest.php`, `LembagaController.php`, `AcademicProfile.php`, `RaporPdfDataBuilder.php`).
4. **Git State**:
   - Branch: `akademik-v2`
   - Commits Kelompok C:
     - `5cde2f80`: `feat(akademik): badge dan filter kurikulum di daftar RPP`
     - `a991d91e`: `feat(akademik): validasi konsistensi kelas-semester pada form RPP`
     - `a1079ddc`: `test(akademik): tambah regresi cross-tenant IDOR ekstrakurikuler_lembaga`
     - `0fe0c9c7`: `docs: catat penyelesaian Kelompok C, tutup audit sistematis tahap 2 akademik`

---

## 4. Hasil Verifikasi Full Test Suite Checkpoint

```text
Command : php artisan test --compact
Hasil   : 4 skipped, 2355 passed (6459 assertions), 0 failed
Durasi  : 490.18s
Status  : 100% HIJAU (Zero Regression)
```
