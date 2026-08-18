# 📝 Handoff Log: Sub-Task 03c — Standardisasi Filter & Kolom NIS pada Halaman Rekap Kehadiran

- **Tanggal & Waktu:** 19 Agustus 2026, ~05:45 WIB
- **Terkait Spec:** [`.agents/specs/2026-08-19-0545-akademik-03c-filter-dan-nis-rekap-kehadiran.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-0545-akademik-03c-filter-dan-nis-rekap-kehadiran.md)
- **Terkait Plan:** [`.agents/plans/2026-08-19-0545-akademik-03c-filter-dan-nis-rekap-kehadiran.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-0545-akademik-03c-filter-dan-nis-rekap-kehadiran.md)
- **Status Sub-Task:** 🟢 SELESAI (COMPLETED)

---

## 1. Apa yang Dikerjakan

Menstandarisasi filter dan tampilan tabel pada halaman **Rekap Kehadiran Semesteran** Guru Wali Kelas (`/guru/rekap-kehadiran`) sesuai kebutuhan UX sistem:

1. **Service `PresensiAggregationService`**:
   - Memperbarui method `agregasiPerKelas(int $kelasId, ?Semester $semester = null): Collection` untuk mengembalikan field `nis` dari model `Siswa`.
   - Mendukung parameter `$semester` bernilai `null` untuk agregasi seluruh semester pada kelas tersebut.
   - Unit test `tests/Unit/Services/PresensiAggregationServiceTest.php` diperbarui dan lulus (5 passed).

2. **Controller `RekapKehadiranController`**:
   - Menambahkan query dropdown berjenjang 3-level yang selaras dengan halaman Perangkat Ajar (RPP):
     - `tahun_ajaran_id`: Daftar seluruh Tahun Ajaran lembaga (dengan indikator status aktif). Default ke Tahun Ajaran aktif jika tidak ada di query URL.
     - `semester_id`: Daftar Semester pada Tahun Ajaran terpilih. Default ke Semester aktif jika ada.
     - `kelas_id`: Daftar Kelas yang diampu oleh Guru sebagai Wali Kelas pada Tahun Ajaran terpilih.
   - Feature test `tests/Feature/Guru/RekapKehadiranControllerTest.php` diperbarui untuk memvalidasi filter baru dan tampilan NIS (4 passed).

3. **Blade View `rekap.blade.php`**:
   - Menata ulang tata letak header dengan breadcrumb: `Ruang Guru > Akademik > Rekap Kehadiran`.
   - Menambahkan Filter Card responsif (grid 3 kolom: Tahun Ajaran, Semester, Kelas) dengan auto-submit `onchange`.
   - Menambahkan kolom **NIS** (`font-mono text-gray-500`) pada tabel rekap sebelum kolom Nama Siswa.
   - Menyediakan empty state informatif jika tidak ada kelas yang di-wali-i pada Tahun Ajaran yang dipilih.

**Hasil Pengujian:**
- Scoped regression test set (9 file test): **49 passed, 0 failed** (108 assertions).

---

## 2. Keputusan Penting yang Diambil

1. **Konsistensi UI Filter dengan Modul RPP**:
   - Mengikuti pola filter grid 3 dropdown (Tahun Ajaran, Semester, Kelas) yang sudah mapan di platform, memastikan guru dapat melihat riwayat kelas wali di tahun ajaran lampau tanpa kebingungan.
2. **Keterbacaan Identitas Siswa**:
   - Kolom NIS ditempatkan paling depan dengan format font monospace abu-abu (`text-gray-500`) untuk memudahkan identifikasi siswa selain nama lengkap.

---

## 3. Hal yang Perlu Direview Manusia / Tahap Selanjutnya

1. **Verifikasi Visual di Browser**:
   - Login sebagai guru wali kelas → buka menu **"Rekap Kehadiran"** (`/guru/rekap-kehadiran`).
   - Cek filter Tahun Ajaran, Semester, dan Kelas: ubah dropdown dan pastikan data tabel ter-update dengan benar.
   - Pastikan kolom NIS muncul dan terisi sesuai data siswa kelas.
2. **Status Git**:
   - Commit telah tersimpan di branch `akademik-v2` lokal:
     - `a3e0d45` `feat(akademik): sertakan NIS dan dukung opsi semester nullable pada PresensiAggregationService`
     - `a7c0079` `feat(akademik): tambah filter Tahun Ajaran dan Semester serta kolom NIS pada Rekap Kehadiran`
     - Commit dokumentasi handoff log Sub-Task 03c.
