# Handoff Log: Guru Piket — Pengisian Jurnal KBM Real-Time Pengganti (Fase 1)

> **Dokumen Terkait**:
> - Spec: [`.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md)
> - Implementation Plan: [`.agents/plans/2026-09-06-guru-piket-jurnal-kbm.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-06-guru-piket-jurnal-kbm.md)
> - Kickoff: [`.agents/kickoff/2026-09-06-guru-piket-jurnal-kbm-kickoff.md`](file:///d:/laragon/www/pintera-app/.agents/kickoff/2026-09-06-guru-piket-jurnal-kbm-kickoff.md)
> - Tanggal Selesai: 6 September 2026
> - Branch: `akademik-v2` (TIDAK di-merge ke `main`, sesuai instruksi)

---

## 1. Apa yang Dikerjakan

Menuntaskan **Proyek C (Fase 1)**: mekanisme guru piket untuk pengisian jurnal KBM dan presensi siswa secara real-time pada hari H saat guru pengajar berhalangan hadir dadakan.

### Ringkasan Per Task & Commit Hash:

1. **Task 1: Migrasi & Model Dasar** (`1558eb3b`)
   - Membuat migrasi tabel `jadwal_piket_mingguan`, `piket_harian`, dan penambahan kolom `diisi_oleh_guru_id` pada `sesi_pembelajaran`.
   - Membuat model `App\Domains\Akademik\Models\JadwalPiketMingguan` dan `App\Domains\Akademik\Models\PiketHarian`.
   - Menambahkan relasi `diisiOlehGuru(): BelongsTo` pada `SesiPembelajaran`.
   - Test: `tests/Unit/Domains/Akademik/PiketModelsTest.php` (4 passed).

2. **Task 2: GenerateJadwalPiketHarianAction** (`83f91ef4`)
   - Membuat `App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction`.
   - Idempotent (`firstOrCreate`), menggunakan `KalenderAkademikResolver` untuk mengabaikan hari libur akademik lembaga.
   - Test: `tests/Unit/Domains/Akademik/GenerateJadwalPiketHarianActionTest.php` (3 passed).

3. **Task 3: RegenerateJadwalPiketHarianAction** (`ee3488d8`)
   - Membuat `App\Domains\Akademik\Actions\Piket\RegenerateJadwalPiketHarianAction`.
   - Transaksional 3 langkah berurutan: ambil kandidat yang dapat dihapus (`sumber = 'otomatis_mingguan'`, `tanggal >= today`) → saring dan lindungi baris yang sudah dipakai (`SesiPembelajaran.diisi_oleh_guru_id`) → hapus baris yang aman lalu generate ulang dari jadwal mingguan aktif.
   - Melindungi 3 kondisi baris beku: `sumber = 'override_manual'`, `tanggal < today`, dan yang memiliki akuntabilitas pengisian.
   - Test: `tests/Unit/Domains/Akademik/RegenerateJadwalPiketHarianActionTest.php` (4 passed).

4. **Task 4: PiketAccessChecker** (`83f74fe8`)
   - Membuat service helper `App\Domains\Akademik\Services\PiketAccessChecker`.
   - Tenant-safe: memverifikasi `$sesi->lembaga_id` (bukan lembaga asal guru) dan memastikan tanggal sesi sama dengan hari ini.
   - Test: `tests/Unit/Domains/Akademik/PiketAccessCheckerTest.php` (4 passed).

5. **Task 5: Wiring Guard Akses Piket** (`c6305305`)
   - Mengintegrasikan `PiketAccessChecker::bisaAkses()` ke dalam `JurnalKbmController::authorizeMilikGuru()` dan `UpdateJurnalPresensiRequest::authorize()`.
   - Keduanya menggunakan helper tunggal yang sama, mencegah celah inkonsistensi otorisasi.
   - Test: `tests/Feature/Guru/JurnalKbmPiketAksesTest.php` (4 passed).

6. **Task 6: Kolom Akuntabilitas `diisi_oleh_guru_id`** (`b51d91f0`)
   - Menambahkan parameter opsional `?int $diisiOlehGuruId = null` pada `RecordJurnalDanPresensiAction::execute()`.
   - Pada `JurnalKbmController::update()`, kolom `diisi_oleh_guru_id` diisi hanya jika `$guru->id !== $sesi->guru_id` (jika guru pemilik mengisi sesinya sendiri, tetap `null`).
   - Test: `tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php` (2 passed).

7. **Task 7: UI Index Jurnal KBM — Seksi "Sesi Piket Hari Ini"** (`b38d48ff`)
   - Memperbarui `JurnalKbmController::index()` untuk memuat daftar `$sesiPiket` hanya jika guru yang login bertugas sebagai guru piket hari ini.
   - Menambahkan kartu visual bernuansa indigo yang elegan di atas tabel sesi reguler di `resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php`.
   - Test: `tests/Feature/Guru/JurnalKbmSesiPiketTest.php` (2 passed).

8. **Task 8: Permission `piket.kelola` & Role Seeding** (`92002025`)
   - Menambahkan permission `piket.kelola` di `database/seeders/PermissionSeeder.php`.
   - Mengalokasikan permission ke role `wakasek_kesiswaan` dan `operator_akademik` di `database/seeders/RoleSeeder.php`.
   - Catatan: Aksi mengisi presensi tetap menggunakan permission `presensi.isi` yang sudah ada (tidak ada permission baru untuk pengisian).

9. **Task 9: Admin CRUD JadwalPiketMingguan** (`fe1a1d6c`)
   - Menambahkan routes di `routes/admin/akademik-master.php`.
   - Membuat `App\Http\Controllers\Admin\JadwalPiketMingguanController`.
   - Logika penentuan Action berbasis kondisi data: `store()` mengecek keberadaan data `PiketHarian` semester ini (jika belum ada -> `Generate`, jika sudah ada -> `Regenerate`); `update()` dan `destroy()` selalu memanggil `Regenerate`.
   - Membuat view Blade: `index.blade.php`, `create.blade.php`, `edit.blade.php`.
   - Test: `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php` (5 passed).

10. **Task 10: Admin Override Manual PiketHarian Per-Tanggal** (`ac55790a`)
    - Menambahkan endpoint `admin.piket-harian.store` dan `admin.piket-harian.destroy` di `routes/admin/akademik-master.php`.
    - Membuat `App\Http\Controllers\Admin\PiketHarianController` dengan validasi tenant-safety yang ketat (guru harus milik lembaga admin, destroy memverifikasi lembaga_id).
    - Menambahkan seksi form dan tabel daftar aktif override manual di `piket-guru/index.blade.php`.
    - Test: `tests/Feature/Admin/PiketHarianControllerTest.php` (3 passed).

11. **Task 11: Full Test Suite, Pint & Dokumentasi**
    - Memperbarui `tests/Feature/RolePermissionSeederTest.php` untuk permission baru (152 total).
    - Menjalankan Pint: `vendor/bin/pint --dirty --format agent` (passed).
    - Memverifikasi regresi: 32 tests di Guru JurnalKbm feature tests passed, seluruh fitur A2 (notifikasi WA), A3 (scan kartu digital), dan Proyek A (batas edit presensi) 100% aman dan lulus uji.

---

## 2. Keputusan Penting yang Diambil

1. **Koreksi Tanggal Mulai Generate (Self-Correction Task 2)**:
   - Pada draf awal spesifikasi, generate harian dimulai dari `semester->tanggal_mulai`.
   - Saat menyusun implementation plan, ditemukan potensi celah jika jadwal mingguan baru diinput/diperbarui di pertengahan semester: men-generate dari awal semester akan membuat baris piket harian untuk tanggal lampau.
   - Sesuai prinsip wewenang piket yang **hanya berlaku hari ini** (`now()->toDateString()`), `GenerateJadwalPiketHarianAction` dikoreksi untuk memulai perulangan dari `max(today, semester->tanggal_mulai)`.
2. **Kriteria Pemilihan Generate vs Regenerate Berbasis Kondisi Data**:
   - `JadwalPiketMingguanController::store()` memeriksa `PiketHarian::where('lembaga_id', $lembagaId)->where('tanggal', '>=', $semester->tanggal_mulai)->exists()`.
   - Jika `false` (lembaga baru pertama kali setup jadwal semester ini), panggil `GenerateJadwalPiketHarianAction`.
   - Jika `true` (sudah pernah digenerate sebelumnya), panggil `RegenerateJadwalPiketHarianAction` untuk melindungi override manual dan catatan akuntabilitas yang sudah terbentuk.
   - Untuk `update()` dan `destroy()`, selalu memanggil `RegenerateJadwalPiketHarianAction`.
3. **PiketAccessChecker Tenant Scoping**:
   - Pengecekan piket wajib memvalidasi `$sesi->lembaga_id === $guru->lembaga_id`, bukan hanya mengecek jadwal piket guru di lembaganya sendiri. Ini mencegah guru piket di Lembaga A mengakses sesi KBM di Lembaga B.
4. **Guard Otorisasi Tunggal di Dua Titik**:
   - `JurnalKbmController::authorizeMilikGuru()` (yang menjaga `show()`, `resolveKartu()`) dan `UpdateJurnalPresensiRequest::authorize()` (yang menjaga `update()`) keduanya mendelegasikan pengecekan piket ke `PiketAccessChecker::bisaAkses($sesi, $guru)`. Tidak ada duplikasi logika otorisasi.
5. **Form Request Validation & Penanganan TenantScope pada Feature Test**:
   - `UpdateJurnalPresensiRequest` memvalidasi array `presensi` tidak boleh kosong (`['required', 'array']`). Uji fitur yang menguji pengisian presensi oleh guru piket menyertakan minimal 1 siswa hadir.
   - Karena `SesiPembelajaran` menggunakan trait `BelongsToTenant`, upaya akses URL oleh guru dari lembaga berbeda langsung menghasilkan 404 (Route Model Binding tenant scoping) sebelum masuk ke controller. Assertions pada pengujian lintas lembaga mencakup toleransi 403 atau 404.

---

## 3. Hal yang Masih Perlu Direview Manusia/Claude

1. **Fase 2 (Backlog Terpisah)**:
   - Modul `LaporanPiket` (rekap resume harian yang diisi guru piket di akhir jam sekolah).
   - Alur verifikasi / persetujuan oleh Kepala Sekolah atas catatan piket harian.
   - Cetak dokumen resmi fisik SK / Jadwal Piket per semester.
   - *Catatan*: Seluruh fitur Fase 2 ini sengaja tidak disentuh pada implementasi Fase 1 sesuai batasan spec.
2. **Sinkronisasi Kalender Akademik Dinamis**:
   - Jika admin lembaga menambahkan hari libur baru di tengah semester setelah jadwal piket harian ter-generate, jadwal piket harian pada tanggal libur tersebut tidak dihapus otomatis secara reaktif (tidak ada observer / event listener di Kalender Akademik untuk ini). Ini adalah trade-off yang disepakati di spec §2 poin 6 (akan disinkronkan kembali jika admin mengedit jadwal piket mingguan, atau dibersihkan manual via override).
3. **Status Git Saat Ini**:
   - Branch: `akademik-v2`
   - Tidak di-merge ke `main` (menunggu review komprehensif bersama fitur-fitur pendahulu di branch ini: A2, A3, Proyek A, dan Proyek C Fase 1).
   - Status git clean (seluruh unit test dan feature test baru lulus).
