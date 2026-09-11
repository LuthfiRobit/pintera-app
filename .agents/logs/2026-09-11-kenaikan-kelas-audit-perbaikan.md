# Handoff Log: Perbaikan Audit Menyeluruh Halaman Kenaikan Kelas

**Tanggal**: 11 September 2026  
**Branch**: `rbac-v2` (tidak di-merge / tidak di-push ke remote, sesuai instruksi)  
**Spec**: [`.agents/specs/2026-09-11-kenaikan-kelas-audit-perbaikan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-11-kenaikan-kelas-audit-perbaikan.md)  
**Plan**: [`.agents/plans/2026-09-11-kenaikan-kelas-audit-perbaikan.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-11-kenaikan-kelas-audit-perbaikan.md)  
**Konteks Pra-kondisi**: [`.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md)  
**Commit Range**: `7a9617cf`..`2c93d9c5` (7 commits atomic)

---

## 1. Apa yang Dikerjakan

Menutup secara tuntas seluruh 7 kelompok temuan audit (1 Critical, 2 High, 2 Medium, 1 gabungan High+Medium, 1 gabungan Low) pada halaman Kenaikan Kelas, dilaksanakan secara TDD task-by-task:

1. **Task 1 (Critical)** — `7a9617cf`:
   - Mengganti mass-update Query Builder (`Siswa::where(...)->update(...)`) di `ProsesKenaikanKelasAction` dengan per-siswa iteration.
   - Tindakan `lulus` menggunakan jalur resmi `UpdateStatusSiswaAction::execute($siswa, StatusSiswa::Lulus)` sehingga akun siswa (`user.is_active`) otomatis dinonaktifkan dan `kelas_terakhir_id` tercatat.
   - Tindakan `naik` menggunakan `$siswa->update(['kelas_id' => $kelasBaru->id])` sehingga model event `updated` / `wasChanged('kelas_id')` terpicu, mendispatch `StudentUpdatedClass` (untuk pembuatan tagihan SPP otomatis) dan activity log Spatie.
   - Return value diperluas: `array{jadwalGagal: array, siswaNaik: int, siswaLulus: int, kelasDilewati: int}`.

2. **Task 2 (High)** — `cdafbdf0`:
   - Menambahkan validasi komparatif tahun ajaran sumber vs tujuan di `KenaikanKelasController::index()` sebelum render tabel pemetaan.
   - Menolak kombinasi sumber = tujuan sama persis atau tahun tujuan lebih lama dari tahun sumber, dengan pesan error spesifik dan mengosongkan list kelas agar tidak dapat diproses salah.
   - Mengirim `$errorTahunAjaran` ke view.

3. **Task 3 (High + A11y)** — `cbfeb2ba`:
   - Mengubah dropdown picker Tahun Ajaran (sumber & tujuan) di `index.blade.php` agar menyertakan nama lembaga (`{{ $tahunAjaran->nama }} — {{ $tahunAjaran->lembaga->nama }}`) guna mengatasi ambiguitas nama tahun ajaran identik pada mode multi-lembaga agregat.
   - Memperbaiki aksesibilitas dengan menghubungkan `<x-input-label for="...">` dengan `id="tahun_ajaran_id"` dan `id="tahun_ajaran_tujuan_id"`.

4. **Task 4 (Medium)** — `c1ba995f`:
   - Menambahkan validasi `exists:kelas,id` untuk `mapping.*.kelas_baru_id`.
   - Menambahkan validasi kondisional `required_if:mapping.*.salin_jadwal,1` + `exists:semester,id` untuk `mapping.*.semester_tujuan_id` agar pencentangan salin jadwal tidak gagal senyap saat semester tujuan lupa dipilih.

5. **Task 5 (Medium)** — `cb44c628`:
   - Memanfaatkan return value baru dari `ProsesKenaikanKelasAction` untuk menyajikan flash message sukses yang informatif: jumlah siswa naik kelas, jumlah siswa diluluskan, dan jumlah kelas dilewati.

6. **Task 6 (High+Medium UX)** — `52084c85`:
   - Membuat komponen JS Alpine `resources/js/kenaikan-kelas-form.js` yang menghitung ringkasan aksi DOM secara real-time saat submit dan menampilkan dialog konfirmasi non-blocking via `window.confirmDialog()`.
   - Mengaktifkan proteksi double-submit dengan `x-bind:disabled="submitting"`.
   - Memberikan highlight visual border kiri amber (`border-l-4 border-amber-400 bg-amber-50/30`) pada baris kelas yang memiliki peringatan kurikulum/tingkat tidak wajar tanpa merusak getter Alpine per-baris existing.
   - Registrasi di `resources/js/app.js` dan recompile asset via `npm.cmd run build`.

7. **Task 7 (Low Polish)** — `2c93d9c5`:
   - Menambahkan banner error jika terdapat `$errorTahunAjaran`.
   - Menambahkan teks bantuan kecil pada header kolom "Salin Jadwal ke Semester".
   - Mengubah opsi tindakan "Lewati" agar selalu tersedia di semua kelas (bukan hanya kelas kosong), dengan label dinamis `Lewati (sudah kosong)`.
   - Menggunakan human-friendly label untuk kurikulum (`data-kurikulum-label`) pada teks peringatan perbedaan kurikulum.
   - Menyelaraskan padding dan style tabel (`px-5 py-3.5`, header `bg-gray-50/50 text-xs font-bold`).

8. **Task 8 (Verifikasi & Regresi)**:
   - Full suite Kenaikan Kelas (36 test): 100% Pass.
   - Laravel Pint formatting: Clean passed.
   - Full project suite (`php artisan test --compact`, 3.169 test): 3.166 passed, 3 failed (murni 3 kegagalan pre-existing).

---

## 2. Keputusan Penting yang Diambil

1. **Anti-Duplikasi Logika Status Siswa**:
   - `UpdateStatusSiswaAction` dipakai langsung tanpa membuat logika baru untuk manipulasi akun siswa lulus.
2. **Tidak Melakukan Hard-Block di Backend untuk Tingkat/Kurikulum**:
   - Sesuai kontrak arsitektur yang sudah ditegaskan pada `tests/Feature/Admin/KenaikanKelasControllerTest.php:286-307`, backend tidak memblokir kombinasi tingkat apa pun (misal tinggal kelas atau lompat jenjang yang sah menurut kebijakan sekolah), melainkan peringatan visual dipertegas di frontend.
3. **Penyelarasan Nilai Return Action**:
   - Return type `ProsesKenaikanKelasAction::execute()` tetap mempertahankan key lama `jadwalGagal` sehingga tidak mematahkan konsumsi kode/test lama, sambil menambahkan rincian count `siswaNaik`, `siswaLulus`, dan `kelasDilewati`.

---

## 3. Hasil Verifikasi & Fakta Operasional

- **Hasil Kenaikan Kelas Test Suite**:
  ```
  vendor/bin/pest tests/Unit/Domains/Akademik/Actions/KenaikanKelas tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php tests/Feature/Akademik/KenaikanKelasIndicatorTest.php --compact
  Tests: 36 passed (127 assertions)
  ```
- **Hasil Full Project Test Suite**:
  ```
  php artisan test --compact
  Tests: 3 failed, 3166 passed (8530 assertions)
  ```
  Kegagalan yang terjadi hanya 3 test pre-existing yang sudah terdokumentasi:
  1. `Tests\Unit\M3DemoDataSeederTest > it seeds a spread of pendaftaran states across K-9 institutions...`
  2. `Tests\Unit\M3DemoDataSeederTest > it is idempotent when the full DatabaseSeeder is run twice`
  3. `Tests\Feature\Akademik\SubjekTenantValidationTest > it rejects a komponen penilaian whose mata_pelajaran...`
- **Query Dampak Data Lama (Task 8 Step 5)**:
  ```php
  Siswa::where('status', 'lulus')->whereHas('user', fn($q) => $q->where('is_active', true))->count();
  // Hasil: 0
  ```
  Tidak ditemukan data anomali siswa lulus lama dengan akun aktif di database lokal/dev.

---

## 4. Hal yang Perlu Direview Manusia / Claude

- **Status Git**:
  - Branch: `rbac-v2`
  - Working tree: Bersih (untracked: `storage/debugbar/` saja).
  - Belum di-merge ke branch utama dan belum di-push ke remote.
- **Frontend Assets**:
  - Vite build manifest telah diperbarui melalui `npm.cmd run build` dan siap digunakan langsung.
