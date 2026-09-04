# Handoff Log: Perbaikan Audit Modul Akademik Putaran 4

- **Tanggal**: 2026-09-04
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-09-04-perbaikan-audit-akademik-putaran-4.md`
- **Plan**: `.agents/plans/2026-09-04-perbaikan-audit-akademik-putaran-4.md`
- **Base Commit**: `228c4c37`
- **Head Commit**: `cf7d8858`

---

## 1. Apa yang Dikerjakan

Paket perbaikan ke-4 dari rangkaian audit modul Akademik berhasil menyelesaikan seluruh 9 task secara berurutan dan terverifikasi penuh:

1. **Task 1 (Critical Root Fix Multi-Tenant Session Staleness)**: `app/Models/Scopes/TenantScope.php`
   - Memperbaiki root-cause celah session basi pada pembacaan data tenant-scoped di seluruh aplikasi.
   - Pada `TenantScope::apply()`, `session('active_lembaga_id')` kini diverifikasi ulang kepemilikannya: lembaga tersebut harus benar-benar milik yayasan actor (`$actor->yayasan_id !== null && Lembaga::where('id', $sessionLembagaId)->where('yayasan_id', $actor->yayasan_id)->exists()`).
   - Jika session basi terdeteksi (atau lembaga milik yayasan lain), scope secara otomatis jatuh ke fallback existing (membatasi ke seluruh lembaga milik yayasan actor), bukan melempar exception atau membiarkan query lintas-yayasan bocor.
   - Test baru ditambahkan di `tests/Feature/TenantScopeTest.php` (skenario session basi menunjuk ke lembaga milik yayasan lain), plus 1 test lama di file yang sama diperbaiki (`yayasan_id` actor sebelumnya tidak diset eksplisit).
   - Commit: `8e1e78d9` `fix(core): TenantScope verifikasi ulang kepemilikan yayasan atas active_lembaga_id session, cegah akses lintas-yayasan dari session basi`.

2. **Task 2 (Important Multi-Tenant Write-Path Guard - Kelas)**: `app/Http/Controllers/Admin/KelasController.php`
   - Memperbaiki jalur tulis `store()` pada `KelasController` dengan mengganti pembacaan session mentah `session('active_lembaga_id')` menjadi `resolveActiveLembagaId($user)`.
   - Menolak pembuatan kelas jika session basi/tidak valid milik yayasan actor saat ini (redirect back dengan error `lembaga_id`).
   - Test regresi ditambahkan di `tests/Feature/Admin/KelasCrudTest.php`.
   - Commit: `c2fd9e9f` `fix(akademik): KelasController::store() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()`.

3. **Task 3 (Important Multi-Tenant Write-Path Guard - Tahun Ajaran)**: `app/Http/Controllers/Admin/TahunAjaranController.php`
   - Memperbaiki jalur tulis `store()` pada `TahunAjaranController` dengan mengganti pembacaan session mentah `session('active_lembaga_id')` menjadi `resolveActiveLembagaId($user)`. Pesan error TIDAK berubah (`"Pilih lembaga aktif melalui pengalih lembaga sebelum membuat tahun ajaran."`).
   - Test regresi ditambahkan di `tests/Feature/Admin/TahunAjaranSemesterFeatureTest.php`; 1 test lama di `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php` ikut diperbaiki (`yayasan_id` actor sebelumnya tidak diset, cuma "berhasil" karena kelemahan `TenantScope` lama).
   - Commit: `847029f3` `fix(akademik): TahunAjaranController::store() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()`.

4. **Task 4 (Important Multi-Tenant Write-Path Guard - Pola Jam)**: `app/Http/Controllers/Admin/PolaJamController.php`
   - Mengganti pembacaan `session('active_lembaga_id')` pada `store()` dengan `$this->resolveActiveLembagaId($user)`.
   - Mempertahankan struktur ternary `$user->widestScopeLevel() === 'yayasan' ? ... : $user->lembaga_id` sehingga actor platform tidak ikut membaca session.
   - Test regresi ditambahkan di `tests/Feature/Admin/PolaJamCrudTest.php`.
   - Commit: `00aaaf35` `fix(akademik): PolaJamController::store() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()`.

5. **Task 5 (Important Multi-Tenant Write-Path Guard - Jenis Tes Master)**: `app/Http/Controllers/Admin/JenisTesMasterController.php`
   - Mengganti pembacaan `session('active_lembaga_id')` pada `store()` dengan `$this->resolveActiveLembagaId($user)` di dalam blok pengecekan `isYayasanScope`.
   - Test regresi ditambahkan di `tests/Feature/Admin/JenisTesMasterTest.php`.
   - Commit: `4ca76298` `fix(akademik): JenisTesMasterController::store() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()`.

6. **Task 6 (Important Multi-Tenant Write-Path Guard - RPP Verify)**: `app/Http/Controllers/Admin/RppController.php`
   - Mengganti pembacaan `session('active_lembaga_id')` pada `verify()` dengan `$this->resolveActiveLembagaId($user)`. Pesan error TIDAK berubah (`"Pilih lembaga aktif melalui pengalih lembaga sebelum memverifikasi RPP."`, tetap `abort_if(..., 422, ...)`).
   - Mempertahankan struktur percabangan ternary `$user->widestScopeLevel() === 'yayasan' ? ... : $user->lembaga_id`.
   - Test regresi ditambahkan di `tests/Feature/Akademik/RppWorkflowTest.php`.
   - Commit: `ed99fbc2` `fix(akademik): RppController::verify() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()`.

7. **Task 7 (Important Multi-Tenant Write-Path Guard & Dead Code Cleanup - Jadwal Pelajaran)**: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
   - Mengganti pembacaan `session('active_lembaga_id')` pada `store()` (baris ~182) dan `update()` (baris ~336) dengan `$this->resolveActiveLembagaId($user)`.
   - Memperbaiki bug kode mati pada `duplicate()` (baris ~448) yang sebelumnya membaca properti tidak ada `$user->active_lembaga_id` (selalu bernilai null), kini menggunakan `$user->widestScopeLevel() === 'yayasan' ? $this->resolveActiveLembagaId($user) : $user->lembaga_id`.
   - Mempertahankan seluruh struktur percabangan ternary di ketiga method (`store`, `update`, `duplicate`).
   - 3 test baru ditambahkan di `tests/Feature/Admin/JadwalPelajaranTenantGuardTest.php` (total 4 test di file itu setelah perubahan, termasuk 1 test lama). Sesuai catatan kejujuran di plan (lihat §2 poin 4 di bawah), ketiga test baru untuk `update()`/`duplicate()` bersifat regresi jalur normal, bukan pembuktian penutup celah aktif.
   - Commit: `ee45bae4` `fix(akademik): JadwalPelajaranController verifikasi ulang active_lembaga_id di store/update, perbaiki bug kode-mati active_lembaga_id di duplicate()`.

8. **Task 8 (Important Concurrency / Race Condition - Jadwal Pelajaran)**: `CreateJadwalPelajaranAction.php` & `UpdateJadwalPelajaranAction.php`
   - Mencegah race condition pembuatan/pembaruan jadwal pelajaran yang bentrok pada jam dan guru/ruangan yang sama saat terjadi request bersamaan.
   - Membungkus seluruh logika validasi dan penulisan di dalam `DB::transaction()`.
   - Mengambil concurrency lock di AWAL closure via `JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first()` SEBELUM pengecekan bentrok ruangan, slot kelas, atau guru dimulai.
   - Test regresi ditambahkan di `tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php`.
   - Commit: `141bbc14` `fix(akademik): cegah race condition bentrok jadwal via lockForUpdate pada JamPelajaran`.

9. **Task 9 (Full Suite Verification & Test Fixture Alignment)**:
   - Menyelaraskan fixture `yayasan_id` pada `tests/Feature/Admin/PendaftaranAdminDetailTest.php` dan `tests/Feature/Admin/SkPpdbTest.php` di mana user yayasan sebelumnya dibuat tanpa `yayasan_id`, yang bekerja sebelum Task 1 hanya karena kelemahan pengecekan `TenantScope` lama. Commit: `cf7d8858`.
   - Menjalankan full test suite tanpa proses pengujian konkuren:
     **2,792 passed (7,601 assertions)**, 0 failures, 0 errors, duration 717.91s.
   - `vendor/bin/pint --dirty --format agent`: passed clean.

---

## 2. Keputusan Penting yang Diambil

1. **Titik Perbaikan Root di `TenantScope::apply()` (Bukan Middleware)**:
   - Perbaikan dilakukan langsung di dalam `TenantScope::apply()` dan sama sekali tidak menyentuh `ResolveTenant` middleware. Keputusan ini menjamin isolasi multi-tenant tetap ditegakkan di seluruh jalur eksekusi (HTTP request, console command artisan, background worker job, hingga testing environment).
   - Saat session basi terdeteksi, perilakunya diperlakukan persis sama dengan kondisi "belum memilih lembaga" — yaitu jatuh ke fallback pembatasan ke seluruh lembaga milik yayasan actor saat ini (baris 58-82 asli dari `TenantScope.php` dipertahankan 100% tanpa modifikasi).

2. **Konsistensi Resolver Trait `ResolveLembagaScopeTrait`**:
   - Seluruh Task 2 hingga Task 7 secara konsisten menggunakan trait existing `ResolveLembagaScopeTrait::resolveActiveLembagaId(User $actor): ?int` yang telah dibuat pada putaran 3 (`app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php`). Tidak ada trait atau helper baru yang dibuat.

3. **Preservasi Struktur Percabangan Ternary**:
   - Di titik `PolaJamController`, `JenisTesMasterController`, `RppController::verify()`, dan `JadwalPelajaranController` (`store()`, `update()`, `duplicate()`), struktur percabangan ternary `$user->widestScopeLevel() === 'yayasan' ? $this->resolveActiveLembagaId($user) : $user->lembaga_id` dipertahankan persis.
   - `resolveActiveLembagaId()` TIDAK dipanggil unconditional di luar percabangan tersebut, mencegah actor platform membaca session lembaga yang tidak relevan baginya.

4. **Catatan Kejujuran Task 7 (Regression Guard vs Active Vulnerability)**:
   - Sesuai instruksi dan catatan kejujuran di spec/plan: perubahan pada `JadwalPelajaranController::update()` dan `duplicate()` (serta perbaikan bug kode mati `$user->active_lembaga_id`) adalah langkah pembersihan kode dan defense-in-depth, BUKAN penutup celah keamanan aktif baru.
   - Celah eksploitasi praktis pada titik tersebut sebelumnya sudah tertutup oleh `TenantScope` (Task 1) melalui route-model-binding atau `findOrFail()` model scoped. Klaim perbaikan pada laporan ini tetap objektif dan tidak melebih-lebihkan severity.

5. **Concurrency Lock pada `JamPelajaran` (Bukan `Semester`)**:
   - Pada Task 8, lock row database diambil terhadap record `JamPelajaran` yang sedang ditarget (`JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first()`). Hal ini berbeda dengan kasus Komponen Penilaian pada putaran 3 yang mengunci `Semester`, karena granularitas slot jam pelajaran adalah titik konkurensi aktual dari jadwal belajar-mengajar.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Non-Goals yang Dikecualikan Sesuai Scope**:
   - **PPDB Controller Session-Staleness**: `JalurPpdbController` dan `GelombangPpdbController` ditunda untuk paket audit PPDB.
   - **SPMB Controllers**: `SkPpdbController`, `TagihanSusulanController`, `PendaftaranAdminController` dicatat untuk audit modul SPMB terpisah.
   - **Presensi Cutoff**: Fitur pembatasan waktu edit presensi masih ditunda menunggu keputusan bisnis.
   - **Activitylog Kenaikan Kelas**: Log mass-update pada `ProsesKenaikanKelasAction` tetap ditunda.
   - **Dropdown JadwalPelajaranController::index() baris 71**: Filter dropdown berbasis session tidak diubah karena merupakan murni kebutuhan UI pemilihan filter.

2. **Git State Saat Ini**:
   - **Branch**: `akademik-v2` (tetap di branch yang sama).
   - **Status**: Semua task (Task 1-9) selesai dikerjakan, diuji, dan di-commit di lokal.
   - **Total Commits Paket Ini**: 10 commit sejak kickoff (`228c4c37`) sampai `05e6cbe3` (commit ini sendiri) — 8 commit fix/task (Task 1-8), 1 commit perbaikan fixture test SPMB (Task 9), 1 commit dokumentasi checklist+handoff. Belum di-push ke remote.
