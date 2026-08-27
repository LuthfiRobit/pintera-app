# Handoff Log: Fix Filter Jenis Asesmen di 3 Consumer Lain (Post-Priority 6 Audit)

**Tanggal**: 2026-08-27  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-27-akademik-fix-filter-jenis-asesmen-consumer-lain.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-fix-filter-jenis-asesmen-consumer-lain.md)  
**Plan**: [`.agents/plans/2026-08-27-akademik-fix-filter-jenis-asesmen-consumer-lain.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-fix-filter-jenis-asesmen-consumer-lain.md)  
**Status**: ✅ SELESAI (Semua 4 task selesai, full test suite pass: 2317 passed, 4 skipped, 0 failed, 6349 assertions)

---

## 1. Apa yang dikerjakan

Menutup 3 celah filter jenis asesmen yang teridentifikasi pasca-Priority #6 (Asesmen Diagnostik & Formatif) agar data asesmen non-rapor (`DiagnostikKognitif`, `DiagnostikNonKognitif`, `Formatif`) tidak bocor ke representasi nilai/kesiapan rapor:

1. **`CapaianKompetensiGenerator` (Narasi Rapor Cetak Resmi)**:
   - File: `app/Domains/Akademik/Services/CapaianKompetensiGenerator.php`
   - Menambahkan import `JenisAsesmen` dan filter `->whereIn('jenis', JenisAsesmen::masukRapor())` pada query `$asesmenIds`.
   - Test: `tests/Unit/Services/CapaianKompetensiGeneratorTest.php` (baru, membuktikan nilai Formatif yang lebih rendah tidak mencemari/menurunkan narasi capaian kompetensi tertinggi/terendah dari nilai Sumatif).
   - Commit: `6dba704c`.

2. **`DashboardStatsService::statistikProgressRaporKelas()` (Progress Kesiapan Rapor Guru)**:
   - File: `app/Services/DashboardStatsService.php`
   - Menambahkan import `JenisAsesmen` dan filter `->whereHas('asesmen', fn ($q) => $q->whereIn('jenis', JenisAsesmen::masukRapor()))` pada query `$totalTerisi`.
   - Test: `tests/Feature/DashboardStatsServiceAssessmentTypeTest.php` (menambahkan test bahwa penilaian Formatif tidak menghitung `terisi` sebelum ada nilai Sumatif).
   - Commit: `c1e43260`.

3. **`DashboardController` (Widget "5 Nilai Terbaru" Siswa & Orang Tua)**:
   - File: `app/Http/Controllers/Admin/DashboardController.php`
   - Branch Siswa: menambahkan `->whereHas('asesmen', fn ($q) => $q->whereIn('jenis', JenisAsesmen::masukRapor()))` sebelum `->latest('id')->limit(5)`.
   - Branch Orang Tua: menambahkan `->whereHas('asesmen', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->whereIn('jenis', JenisAsesmen::masukRapor()))` sebelum `->latest('id')->limit(5)` (konsisten dengan scope multi-tenant wali murid).
   - Test: `tests/Feature/DashboardTest.php` (menambahkan 2 test exclusion untuk siswa dan orang tua).
   - Commit: `dc44de6b`.

4. **Update Docblock Enum `JenisAsesmen::masukRapor()`, Roadmap, dan Verifikasi Suite Penuh**:
   - File: `app/Domains/Akademik/Enums/JenisAsesmen.php` (memperbarui docblock yang mendokumentasikan ke-4 consumer: `RaporCalculationService`, `CapaianKompetensiGenerator`, `DashboardStatsService`, dan `DashboardController`).
   - File: `PETA_PENGEMBANGAN.md` (mencatat tindak lanjut audit pasca-Prioritas #6).
   - Full test suite: **2317 passed, 4 skipped, 0 failed (6349 assertions)**.
   - Commit: `5f836132`.

---

## 2. Keputusan penting yang diambil

1. **Penerapan filter di level database query sebelum `limit(5)`**:
   - Pada `DashboardController`, filter `whereHas('asesmen', ...)` diletakkan sebelum query ordering & limit (`latest('id')->limit(5)`). Ini menjamin bahwa siswa/orang tua selalu mendapatkan 5 nilai *Sumatif* terbaru, bukan 5 nilai campuran yang kemudian berkurang jumlahnya di memori.
2. **Explicit non-colliding NIS/NISN di test feature `DashboardTest`**:
   - Ketika menggunakan `assertDontSeeText('40')`, faker yang secara acak membuat NIS/NISN 8-10 digit berpotensi memuat substring `'40'` di nomor identitas. Diberikan nilai eksplisit non-colliding (`'nis' => '20260001', 'nisn' => '0011111111'`) serta assertion tambahan pada view data `$response->assertViewHas('nilaiTerbaru', ...)` untuk memastikan keandalan test tanpa flakiness faker.
3. **Pola TenantScope pada Orang Tua**:
   - Query orang tua mempertahankan `withoutGlobalScope(TenantScope::class)` pada closure `asesmen`, identik dengan relasi `komponenPenilaian`, `siswa`, dan `subjek` pada method tersebut.

---

## 3. Hal yang masih perlu direview manusia/Claude

- Tidak ada blocking issue atau open questions yang tersisa dari task ini.
- Audit sistematis sudah mencakup Controllers, Services, Actions, Requests, Jobs, Console, Notifications, Events, Listeners, dan Exports — mengonfirmasi tidak ada lagi consumer Asesmen/NilaiSiswa yang lolos dari filter `JenisAsesmen::masukRapor()`.
- **Git State**:
  - Branch: `akademik-v2`
  - Commits:
    - `6dba704c` fix(akademik): CapaianKompetensiGenerator kecualikan Diagnostik/Formatif dari narasi rapor
    - `c1e43260` fix(akademik): DashboardStatsService kecualikan Diagnostik/Formatif dari progress kesiapan rapor
    - `dc44de6b` fix(akademik): DashboardController kecualikan Diagnostik/Formatif dari widget nilai terbaru siswa/orang tua
    - `5f836132` docs(akademik): perbarui docblock masukRapor(), catat tindak lanjut Prioritas 6
  - Status: Local clean, siap direview/dimerge saat user menghendaki.
