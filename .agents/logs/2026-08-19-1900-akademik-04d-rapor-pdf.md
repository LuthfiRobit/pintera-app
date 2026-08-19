# 📋 Handoff Log: Sub-Task 04d — Adaptive E-Rapor Engine: 4 Template PDF Berjenjang

- **Spec:** [`.agents/specs/2026-08-19-1900-akademik-04d-rapor-pdf.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1900-akademik-04d-rapor-pdf.md)
- **Plan:** [`.agents/plans/2026-08-19-1900-akademik-04d-rapor-pdf.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-1900-akademik-04d-rapor-pdf.md)
- **Status:** 🟢 SELESAI (COMPLETED)

## Ringkasan & Apa yang Dikerjakan

1. **Skema & Model Komponen Penilaian (`elemen_cp` + `kktp_minimal`):**
   - Migrasi `2026_08_19_190000_add_elemen_cp_to_komponen_penilaian_table.php` menambahkan kolom `elemen_cp` (string 30, nullable) pada tabel `komponen_penilaian`.
   - Enum baru `App\Domains\Akademik\Enums\ElemenCapaianPembelajaran` dengan 3 nilai: `NilaiAgamaMoral` ('nilai_agama_moral'), `JatiDiri` ('jati_diri'), `LiterasiSteam` ('literasi_steam').
   - Model `KomponenPenilaian`, DTO `KomponenPenilaianData` & `UpdateKomponenPenilaianData`, Actions `CreateKomponenPenilaianAction` & `UpdateKomponenPenilaianAction`, serta 4 FormRequest (`Store/UpdateKomponenPenilaianRequest` dan `Store/UpdateKomponenPenilaianSendiriRequest`) diperluas untuk mendukung `elemen_cp`.

2. **Perbaikan UI Form Komponen Penilaian:**
   - Menambahkan field input `kktp_minimal` (ambang batas numerik narasi TP capaian) pada form Create & Edit Komponen Penilaian di portal Admin dan Guru (memperbaiki celah dari 04b).
   - Menambahkan dropdown kondisional `elemen_cp` untuk jenjang PAUD (`KB`, `TPA`, `SPS`, `TK`) pada form Create & Edit Komponen Penilaian (Admin & Guru).

3. **Service `RaporPdfDataBuilder`:**
   - Menyatukan seluruh data yang dibutuhkan untuk perenderan PDF: identitas siswa & lembaga, rekap nilai siswa, narasi per mapel, catatan wali kelas, absensi semester, metadata pengajuan, status draft/watermark, serta nama aktor penandatangan sistem (Wali Kelas, Kepala Sekolah, Orang Tua).
   - Mendukung logika adaptif Semester Ganjil vs Genap:
     - Deteksi semester Genap (`urutan === 2`).
     - Deteksi tingkat akhir jenjang (`isTingkatAkhir`) untuk label dinamis: *"Keterangan Kelulusan"* (Tingkat 6 SD/SLB, 9 SMP, 12 SMA/SMK) vs *"Keterangan Kenaikan Kelas"*.
     - Kalkulasi akumulasi kehadiran tahunan (Ganjil + Genap) & rata-rata nilai tahunan jika semester Ganjil tersedia.
   - Mapping template otomatis berdasarkan jenjang (`templateUntukJenjang()`): `pdf.rapor.paud`, `pdf.rapor.sd`, `pdf.rapor.smp-sma`, `pdf.rapor.smk`.

4. **Shared Partials & 4 Template DomPDF Berjenjang:**
   - Shared partial `_identitas.blade.php`: Header kop resmi, judul semester/tahun ajaran, info lembaga & siswa.
   - Shared partial `_tanda-tangan.blade.php`: Kolom 3 tanda tangan aktor sistem (Orang Tua, Wali Kelas, Kepala Sekolah) dengan fallback.
   - Template `paud.blade.php`: Narasi Capaian Pembelajaran dikelompokkan ke dalam 3 Elemen CP Kurikulum Merdeka + Tabel Pertumbuhan Fisik (antropometri: TB, BB, LK) + Catatan Wali Kelas.
   - Template `sd.blade.php`: Tabel Nilai Akademik (+ kolom Rata-Rata Tahunan pada Genap) + Narasi TP + Ekstrakurikuler + Kehadiran (+ Akumulasi Tahunan pada Genap) + Catatan Sikap & Kenaikan/Kelulusan.
   - Template `smp-sma.blade.php`: Tabel Nilai Akademik dengan sub-header kelompok mata pelajaran (`KelompokMataPelajaran` enum) + seluruh komponen seperti SD.
   - Template `smk.blade.php`: Struktur lengkap seperti SMP/SMA ditambah Tabel Keterangan PKL (`catatan_wali_kelas.pkl_info`).
   - Watermark "DRAFT" otomatis aktif jika rapor belum disetujui (`status !== Disetujui`).

5. **Endpoint Streaming PDF & Integrasi UI:**
   - `Guru\RaporController::cetak(Siswa $siswa)` route `guru.rapor.cetak` (guard: `rapor.input-wali` & kepemilikan kelas wali).
   - `Lembaga\Rapor\PersetujuanController::cetak(PengajuanRapor $pengajuanRapor, Siswa $siswa)` route `admin.rapor.persetujuan.cetak` (guard: `rapor.verify` / `rapor.approve`).
   - Link "Cetak PDF" pada tabel siswa portal Guru (Wali Kelas) dan pada detail pengajuan portal Lembaga (Waka Kurikulum / Kepala Sekolah).

---

## Keputusan Penting yang Diambil

1. **PDF Drafting Anytime:** Rapor dapat dicetak kapan saja sebelum diajukan atau disetujui dengan watermark CSS semi-transparan "DRAFT", memudahkan guru dan waka melakukan proofreading awal tanpa harus mengunci approval terlebih dahulu.
2. **Akses Cetak Waka/Kepsek Non-Step-Guarded:** Berbeda dari method `show()` dan `decision()` yang memerlukan guard pencocokan step workflow saat ini, method `cetak()` mengizinkan verifikator/approver mencetak PDF pratinjau kapan saja selama memiliki permission `rapor.verify` atau `rapor.approve`.
3. **Pemberian Nama Semester Eksplisit di Testing:** Pengujian TDD `SemesterFactory` wajib mencantumkan `'nama' => 'Genap'` ketika membuat semester kedua pada tahun ajaran yang sama untuk menghormati unique constraint DB `['tahun_ajaran_id', 'nama']`.

---

## Verifikasi & Kualitas

- **Scoped Regression Bundle (72 tests):** PASS 100%.
- **Full Test Suite (`php artisan test`):** **1841 tests passed (5677 assertions), 0 failed** (durasi ~7.5 menit).
- **Semua 4 template PDF Blade terbukti compile dan render secara sukses tanpa runtime error.**

---

## Hal yang Perlu Direview / Catatan Lanjutan

1. **Pre-existing TenantScope Gap (dari 04a):** Query tanpa filter yayasan ketika `session('active_lembaga_id')` kosong pada aktor yayasan masih tercatat sebagai open finding untuk audit arsitektur menyeluruh berikutnya.
2. **PKL & UKK Numerik SMK:** Format SMK saat ini memuat tabel deskriptif PKL (`pkl_info`). Nilai numerik terpisah & portofolio UKK berada di luar scope sesuai kesepakatan spec §1.

## Final Whole-Sub-Task Review (sesi terpisah, setelah handoff)

Dilakukan review independen terhadap diff penuh `f2d1818..68b31d6`, memverifikasi 9 area berisiko tertinggi langsung terhadap kode aktual: aritmatika Ganjil/Genap (`absensiTahunan`/`nilaiRataRataTahunan`, termasuk memverifikasi test benar-benar meng-override tanggal `SemesterFactory` yang secara default identik antar-instance), sumber nama tanda tangan (aktor sistem, bukan data profil registrasi), mapping tingkat akhir (SD=6/SMP=9/SMA-SMK=12), asimetri otorisasi `cetak()` (Waka/Kepsek boleh cetak draft kapan saja, TIDAK digerbang step seperti `show()`/`decision()`), kebenaran gating semua 4 template terhadap `$isGenap`, backward-compatibility UI `elemen_cp`/`kktp_minimal`, penggunaan DomPDF, dan keabsahan fix `d9d51d6` (unique constraint `['tahun_ajaran_id','nama']` pada `SemesterFactory` — dikonfirmasi nyata dan bukan tambalan superfisial).

**Tidak ditemukan bug fungsional.** Ditemukan 2 celah cakupan test (bukan bug kode — keduanya berasal dari plan 04d yang tidak lengkap menulis test-nya untuk sisi Guru): test conditional-rendering `elemen_cp`/`kktp_minimal` belum ada di `Guru\KomponenPenilaianControllerTest` (hanya Admin), dan test cross-tenant 404 belum ada untuk `PersetujuanController::cetak()` (hanya `show()`). Keduanya ditambahkan (`edda4d7`) dan LULUS TANPA perubahan kode aplikasi — membuktikan implementasi sudah benar sejak awal, murni menutup gap dokumentasi test.

**Verdict: siap merge, modul E-Rapor Engine (04a-04d) SELESAI SEPENUHNYA dengan cakupan test lengkap.**

**Seluruh 4 Sub-Task modul Adaptive E-Rapor Engine (04a, 04b, 04c, 04d) telah SELESAI SEPENUHNYA.**
