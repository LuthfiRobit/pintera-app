# 📋 Spec: Sub-Task 03c — Standardisasi Filter & Kolom NIS pada Halaman Rekap Kehadiran

- **Document ID / Slug:** `2026-08-19-0545-akademik-03c-filter-dan-nis-rekap-kehadiran`
- **Master Plan File:** [`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md) — FASE 3 (Jurnal KBM Adaptif & Presensi Siswa Multi-Jenjang)
- **Target Domain:** `App\Domains\Akademik\` & `App\Http\Controllers\Guru\Akademik\`
- **Tanggal & Waktu:** 19 Agustus 2026, 05:45 WIB
- **Status:** 🟢 APPROVED (Direct User Request)

---

## 1. Latar Belakang

Halaman **Rekap Kehadiran Semesteran** (`/guru/rekap-kehadiran`) saat ini hanya memiliki filter `kelas_id` sederhana tanpa konteks Tahun Ajaran dan Semester. Jika guru memilih kelas tanpa mengetahui tahun ajarannya, atau memiliki kelas di tahun ajaran lampau/berbeda, data tidak terfilter dengan baik. Selain itu, daftar tabel kehadiran siswa belum mencantumkan **Nomor Induk Siswa (NIS)** sebagai identitas resmi siswa.

User meminta standardisasi:
1. Filter lengkap 3-level seperti pada halaman Perangkat Ajar (RPP): **Tahun Ajaran**, **Semester**, dan **Kelas**.
2. Kolom tabel siswa dilengkapi dengan **NIS**.

---

## 2. Keputusan Desain

1. **Struktur Filter:**
   - **Tahun Ajaran (`tahun_ajaran_id`)**: Menampilkan daftar seluruh Tahun Ajaran lembaga (dengan badge status aktif). Default ke Tahun Ajaran aktif jika ada.
   - **Semester (`semester_id`)**: Menampilkan daftar Semester pada Tahun Ajaran yang dipilih, dengan opsi `— Semua Semester —` atau semester spesifik. Default ke Semester aktif jika ada.
   - **Kelas (`kelas_id`)**: Menampilkan daftar Kelas yang diampu oleh Guru sebagai Wali Kelas pada Tahun Ajaran yang dipilih.
2. **Kalkulasi Agregasi:**
   - Jika Semester spesifik dipilih, `PresensiAggregationService` memfilter rentang tanggal antara `tanggal_mulai` dan `tanggal_selesai` semester tersebut.
   - Jika `— Semua Semester —` dipilih (atau null), `PresensiAggregationService` mengagregasi seluruh sesi pada kelas tersebut.
3. **Penyajian Data Siswa:**
   - `PresensiAggregationService::agregasiPerKelas()` menyertakan `'nis' => $siswa->nis`.
   - View `rekap.blade.php` menambahkan kolom `NIS` (`font-mono text-gray-500`) berdampingan dengan kolom `Nama Siswa`.

---

## 3. Scope

### In Scope:
1. Update `App\Domains\Akademik\Services\PresensiAggregationService`:
   - Tambahkan field `'nis'` pada array output per siswa.
   - Izinkan parameter `$semester` nullable.
2. Update `App\Http\Controllers\Guru\Akademik\RekapKehadiranController`:
   - Query `tahunAjaranList`, `semesterList`, dan filter `kelasList` berdasar `tahun_ajaran_id`.
   - Teruskan filter state (`tahunAjaranId`, `semesterId`, `kelasId`, `tahunAjaranList`, `semesterList`, `kelasList`) ke view.
3. Update `resources/views/portals/guru/akademik/jurnal-kbm/rekap.blade.php`:
   - Desain filter card responsif (grid 3 kolom: Tahun Ajaran, Semester, Kelas) berstandar UI platform.
   - Tambahkan kolom NIS pada tabel data rekap.
4. Update unit test `PresensiAggregationServiceTest` dan feature test `RekapKehadiranControllerTest`.

---

## 4. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Filter Tahun Ajaran, Semester, dan Kelas tampil konsisten dan responsif di halaman `/guru/rekap-kehadiran`.
- [ ] Mengubah Tahun Ajaran memfilter pilihan Semester dan Kelas yang sesuai.
- [ ] Tabel rekap menampilkan kolom `NIS` sebelum `Nama Siswa`.
- [ ] Siswa tanpa NIS menampilkan placeholder `-`.
- [ ] Semua scoped test di `PresensiAggregationServiceTest` dan `RekapKehadiranControllerTest` lulus 100%.
