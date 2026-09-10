# Spec: Standarisasi UI/UX & Tabel Rapor Wali Kelas (Guru)

- **Tanggal**: 10 September 2026
- **Status**: Implemented & Verified
- **Scope**: Portal Guru - Rapor Wali Kelas (`/guru/rapor`)

## 1. Tujuan
Menerapkan standarisasi UI/UX, filter berbasis TomSelect dengan dynamic cascading, live search tanpa reload, segmented pill tabs, 4 KPI summary cards, serta standarisasi kolom tabel dengan tombol aksi di sebelah kiri mengikuti referensi desain Pintera (halaman Komponen Penilaian / Rekap Rapor).

## 2. Scope

### In-Scope
1. **Urutan Kolom Tabel Standar**:
   - `AKSI` (Kolom paling kiri, lebar w-44).
   - `NAMA SISWA & NIS` (Inisial avatar, nama lengkap, NIS, NISN, gender).
   - `STATUS CATATAN` (Pill badge emerald checkmark untuk Lengkap, amber pulsating dot untuk Belum Lengkap).
   - `RINGKASAN CATATAN / EKSKUL` (Kutipan catatan wali kelas, badge hitungan ekskul `🏅 X Ekskul`, badge prestasi `🏆 Y Prestasi`).
2. **Styling Tombol Aksi (Outline Card)**:
   - `[ ✏️ Edit ]`: `inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-600 hover:bg-gray-50 hover:text-brand-700 shadow-2xs`
   - `[ 📄 PDF ]`: `inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900 shadow-2xs`
3. **Filter Toolbar & TomSelect**:
   - Tiga dropdown TomSelect: Tahun Ajaran, Semester, Kelas Perwalian Guru.
   - Endpoint `GET /guru/rapor/opsi` untuk cascading semester & kelas saat tahun ajaran berubah.
4. **Live Realtime Search & Segmented Pill Tabs**:
   - Realtime search dengan Alpine.js debounce 300ms (pencarian nama siswa, NIS, NISN).
   - Tabs status: `Semua Siswa`, `● Perlu Dilengkapi`, `● Catatan Lengkap`.
   - Update AJAX tanpa page reload dengan loading shimmer centered dark pill.
5. **4 KPI Summary Cards**:
   - Total Siswa, Catatan Lengkap, Nilai Asesmen, Status Alur Pengajuan.
6. **Preservasi Guard & Logic**:
   - Tetap menjaga validasi kelengkapan nilai & catatan sebelum pengajuan rapor kelas.

### Out-of-Scope
- Perubahan form input catatan individual (`/guru/rapor/siswa/{siswa}`).
- Perubahan template PDF cetak rapor.

## 3. Acceptance Criteria
1. Tidak ada reload halaman penuh saat filter tahun ajaran, semester, kelas, atau status catatan diubah.
2. Filter pencarian teks langsung memfilter data siswa melalui partial view `_daftar.blade.php`.
3. Kolom aksi berada di posisi paling kiri dengan tombol Edit & PDF berbentuk kartu outline bersudut membulat.
4. Seluruh test feature (32 tests) lulus 100%.
