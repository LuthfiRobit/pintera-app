# Handoff Log: Asesmen Diagnostik & Formatif (Prioritas 6)

**Tanggal**: 27 Agustus 2026  
**Branch**: `akademik-v2`  
**Git Commits**:
- `61279f1a` — `feat(akademik): retire JenisAsesmen::v1Didukung(), tambah masukRapor()`
- `a8e8026e` — `feat(akademik): buka 6 jenis asesmen ke form guru, validasi ikut enum`
- `125abcca` — `fix(akademik): RaporCalculationService kecualikan Diagnostik & Formatif dari rekap rapor`
- `27eb0230` — `test(akademik): buktikan siklus penuh Asesmen Formatif (create-input-show-exclusion)`
- `19287bde` — `docs: tandai Prioritas 6 Roadmap Kurikulum Dinamis SELESAI`
- `379c08d8` — `docs(plan): tandai seluruh checklist langkah implementasi Prioritas 6 selesai`

**Spec**: [`.agents/specs/2026-08-27-akademik-asesmen-diagnostik-formatif.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-27-akademik-asesmen-diagnostik-formatif.md)  
**Plan**: [`.agents/plans/2026-08-27-akademik-asesmen-diagnostik-formatif.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-27-akademik-asesmen-diagnostik-formatif.md)  
**Test Status**: **2308 passed, 4 skipped, 0 failed (6315 assertions)**

---

## 1. Apa yang dikerjakan

1. **Retire `JenisAsesmen::v1Didukung()` & Tambah `JenisAsesmen::masukRapor()`** (`app/Domains/Akademik/Enums/JenisAsesmen.php`):
   - Menghapus method usang `v1Didukung()` secara total (tanpa deprecation/shim).
   - Menambahkan `JenisAsesmen::masukRapor(): array` yang mengembalikan hanya 3 jenis Sumatif (`SumatifLingkupMateri`, `SumatifAkhirSemester`, `SumatifAkhirJenjang`) dengan docblock kontrak semantik yang jelas.
   - Menggunakan `JenisAsesmen::cases()` untuk seluruh varian asesmen yang didukung sistem (6 jenis).
   - Retrofit unit test di `tests/Unit/Enums/JenisAsesmenTest.php`.

2. **Form Guru & Validasi Dinamis** (`app/Http/Controllers/Guru/AsesmenController.php`, `app/Http/Requests/Akademik/StoreAsesmenRequest.php`):
   - Controller mengirim `JenisAsesmen::cases()` ke view pembuatan asesmen.
   - Form request menggunakan `Rule::enum(JenisAsesmen::class)` menggantikan string hardcode.
   - Aturan `komponen_id` tetap wajib minimal 1 untuk seluruh jenis asesmen.
   - Retrofit feature test di `tests/Feature/Guru/AsesmenControllerTest.php` dengan 3 test sukses mandiri (`DiagnostikKognitif`, `DiagnostikNonKognitif`, `Formatif`).

3. **Filter Query `RaporCalculationService` (Data Isolation Blocker)** (`app/Domains/Akademik/Services/RaporCalculationService.php`):
   - Menambahkan filter query `whereIn('jenis', JenisAsesmen::masukRapor())` pada satu-satunya titik query `Asesmen` di `hitungRekapKelas()`.
   - Menjamin asesmen `DiagnostikKognitif`, `DiagnostikNonKognitif`, dan `Formatif` tidak pernah diagregasikan ke rekap rapor, rata-rata kelas (`classAvg`), maupun nilai tertinggi (`highestScore`).
   - Menambahkan feature test komprehensif di `tests/Feature/Akademik/RaporCalculationJenisAsesmenTest.php` yang menguji eksklusi murni dan pembuktian isolasi nilai (nilai sumatif 88 tetap 88 meskipun ada nilai formatif/diagnostik 100 pada siswa+subjek+semester yang sama).

4. **Pengujian Siklus Usability Penuh**:
   - Menambahkan `tests/Feature/Guru/AsesmenDiagnostikFormatifUsabilityTest.php` untuk memverifikasi siklus end-to-end: guru membuat asesmen Formatif → membuka halaman detail/show → menginput nilai siswa → melihat nilai tersimpan di show → memastikan nilai tetap terisolasi dari rekap rapor.

5. **Regresi Penuh & Roadmap**:
   - Audit grep 0 referensi liar `v1Didukung`.
   - Menyesuaikan penanganan nama siswa dengan escaping HTML (`e()`) pada `RaporPdfDataBuilderTest.php` untuk kompatibilitas data acak Faker.
   - Eksekusi full test suite: 2308 passed, 4 skipped, 0 failed (6315 assertions).
   - Menandai Prioritas #6 SELESAI di `PETA_PENGEMBANGAN.md`.

---

## 2. Keputusan Penting yang Diambil

1. **Ortogonalitas Jenis Asesmen dan Tipe Penilaian**:
   - `jenis` (Asesmen) dan `assessment_type` (KomponenPenilaian/TP) sepenuhnya ortogonal. Guru bebas memadukan jenis asesmen (termasuk Diagnostik Non-Kognitif) dengan tipe angka/predikat/naratif sesuai kebutuhan kelas tanpa pembatasan kaku buatan.

2. **Kewajiban Komponen TP**:
   - Asesmen Diagnostik dan Formatif tetap terikat pada Tujuan Pembelajaran (`komponen_id` wajib ≥ 1), menjaga konsistensi integritas relasi subjek dan komponen penilaian di seluruh sistem.

3. **Penyempurnaan Assert HTML pada Test PDF Builder**:
   - Menggunakan helper `e($siswa->nama_lengkap)` pada `RaporPdfDataBuilderTest` agar karakter khusus seperti apostrof pada nama siswa hasil Faker (mis. *Stanley O'Hara*) cocok dengan HTML-escape Blade `{{ ... }}`.

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Git State**:
   - Branch saat ini: `akademik-v2` (bersih, seluruh commit rapi dan lengkap).
2. **Prioritas Terakhir di Roadmap Kurikulum Dinamis**:
   - Prioritas 1, 2, 3, dan 6 telah **SELESAI**.
   - Prioritas 4 dan 5 telah **SENGAJA DITUNDA** (menunggu kebutuhan riil pelanggan K13 / Kemenag).
   - Prioritas 7 (UX Polish UI Mata Pelajaran vs Elemen CP untuk PAUD) dapat dikerjakan sewaktu-waktu.
