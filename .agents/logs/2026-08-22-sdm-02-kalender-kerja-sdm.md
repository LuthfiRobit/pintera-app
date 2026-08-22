# Handoff Log — Sub-project 2: Kalender Kerja SDM

- **Tanggal**: 2026-08-22
- **Branch**: `sdm-v1`
- **Spec**: `.agents/specs/2026-08-22-sdm-02-kalender-kerja-sdm.md`
- **Plan**: `.agents/plans/2026-08-22-sdm-02-kalender-kerja-sdm.md`
- **Status Akhir**: Selesai 100% (10/10 Task lulus, 1962/1962 test suite hijau, 0 error, 0 fail).

---

## 1. Apa yang Dikerjakan

Mengimplementasikan Sub-project 2 (Kalender Kerja SDM) yang membuat sistem kalender kerja kepegawaian mandiri, terpisah dari kalender akademik siswa/pembelajaran:

1. **Task 1 (Commit `5448122`)**: Migrasi penambahan kolom JSON `hari_libur_mingguan_sdm` (default `[0]`) pada tabel `lembaga`, pembuatan tabel `kalender_kerja_sdm`, pembuatan enum `TipeKalenderKerjaSdm` (`Libur`, `Kerja`), penambahan enum case `AttendanceMethod::System`, dan pendaftaran cast/fillable di model `Lembaga`.
2. **Task 2 (Commit `54b0bbe`)**: Model `KalenderKerjaSdm` (menggunakan `BelongsToTenant`) dan service `KalenderKerjaSdmResolver` dengan bypass `withoutGlobalScope(TenantScope::class)` eksplisit agar entri libur nasional terbaca dengan benar oleh pengguna login ber-scope `lembaga`. Unit test 7/7 passed, feature tenant isolation test 1/1 passed.
3. **Task 3 (Commit `1de6f1c`)**: DTO `HariKerjaSdmData` dan action `SetHariLiburMingguanSdmAction` yang mengonversi daftar positif hari kerja dari UI/DTO menjadi daftar negatif hari libur di database. Test 2/2 passed.
4. **Task 4 (Commit `23a0a33`)**: Exception `AttendanceOnHolidayException`, perluasan DTO `RecordManualAttendanceData` dengan parameter `$overrideHariLibur = false`, dan integrasi validasi kalender ke `RecordManualAttendanceAction` (sebelum transaksi tulis event/aggregator). Test 5/5 passed.
5. **Task 5 (Commit `1150618`)**: Integrasi validasi kalender ke `ScanQrAttendanceAction` yang menolak scan QR di hari libur secara ketat tanpa jalur override. Test 4/4 passed.
6. **Task 6 (Commit `9911f1a`)**: Command terjadwal `sdm:tandai-alpa-otomatis` (`TandaiAlpaOtomatisSdm`) yang menandai pegawai aktif (guru/karyawan) yang tidak memiliki catatan kehadiran pada hari kerja kemarin (H-1) dengan status `Alpa` via `AttendanceMethod::System`, serta pendaftaran jadwal `dailyAt('01:00')` di `routes/console.php`. Test 5/5 passed.
7. **Task 7 (Commit `9717ccb`)**: Action `CopyKalenderAkademikNasionalAction` untuk menyalin entri kalender akademik nasional ke kalender SDM sebagai snapshot independen dengan dedup key `tanggal|nama`. Sifat snapshot read-only menjamin tidak pernah ada operasi tulis ke tabel akademik. Test 3/3 passed.
8. **Task 8 (Commit `c73b48b`)**: Perluasan `AttendanceConfigurationController` dengan method `updateHariKerja`, `storeKalenderEntri`, `updateKalenderEntri`, `destroyKalenderEntri`, `kalenderSalinTersedia`, dan `kalenderSalin` serta 6 rute admin baru di `routes/admin/kehadiran-sdm.php`. Test 6/6 passed + 4/4 baseline controller test passed.
9. **Task 9 (Commit `4eb980b`)**: Integrasi penanganan `AttendanceOnHolidayException` pada controller, penambahan checkbox override di `create.blade.php`, dan pemisahan layout tab "Metode & Titik Absen" dan "Kalender Kerja" pada `konfigurasi.blade.php`. View tests 2/2 passed + 3/3 baseline attendance controller test passed.
10. **Task 10 (Verifikasi Akhir & Test Suite)**:
    - Grep audit: 0 `hasRole()` hardcoded di domain SDM, 0 write operations ke `KalenderAkademik`.
    - Scoped SDM Test Suite (Sub-project 1 + Sub-project 2): 58 passed, 141 assertions, 0 failed.
    - Full Test Suite Suite: **1962 passed, 5971 assertions, 0 failed**.

---

## 2. Keputusan Penting yang Diambil

1. **Bypass `TenantScope` di `KalenderKerjaSdmResolver`**:
   `KalenderKerjaSdm` menggunakan trait `BelongsToTenant`. Ketika aktor `scope_level: lembaga` sedang terautentikasi, query global scope otomatis menambahkan filter `lembaga_id = user->lembaga_id`. Tanpa `withoutGlobalScope(TenantScope::class)`, query `whereNull('lembaga_id')` untuk libur nasional akan menghasilkan 0 baris (hilang). Bypass eksplisit diterapkan pada query entri lembaga maupun nasional, kemudian difilter secara manual berdasarkan `lembaga_id` dan `yayasan_id`.
2. **Kesesuaian Helper Test `actingAsYayasanSuperAdminKalender`**:
   Untuk mencegah issue caching dan database refresh pada Pest tests, helper test memastikan pembuatan permission `kehadiran-sdm.view` dan `kehadiran-sdm.kelola-konfigurasi` sebelum penugasan ke role `yayasan_super_admin`.
3. **Dedup Key `tanggal|nama` pada Salin Kalender Akademik**:
   Menghindari masalah entri berulang tahunan (misal "Hari Kemerdekaan RI") di tahun berbeda agar tidak terabaikan saat disalin.
4. **`AttendanceMethod::System`**:
   Ditambahkan khusus untuk pencatatan otomatis sistem (seperti auto-alpa), dipisahkan dari metode absensi fisik/admin di UI konfigurasi (`$method->value !== 'system'`).

---

## 3. Hal yang Masih Perlu Direview Manusia/Claude

1. **Git State Saat Ini**:
   - Branch: `sdm-v1`
   - Total Commit Sub-project 2: 9 commits (`5448122`, `54b0bbe`, `1de6f1c`, `23a0a33`, `1150618`, `9911f1a`, `9717ccb`, `c73b48b`, `4eb980b`).
   - Status Branch: Lokal pada `sdm-v1` (belum dimerge ke main, siap untuk lanjut ke Sub-project 3 atau PR).
2. **Cakupan Sub-project Berikutnya**:
   - Sub-project 3 akan menangani Jam Kerja & Shift SDM, Toleransi Keterlambatan, dan Jadwal Khusus per Pegawai. Sub-project 2 tidak memasukkan fitur shift kerja sesuai batasan spec.
