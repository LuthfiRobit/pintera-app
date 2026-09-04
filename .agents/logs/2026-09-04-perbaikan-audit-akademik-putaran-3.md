# Handoff Log: Perbaikan Audit Modul Akademik Putaran 3

- **Tanggal**: 2026-09-04
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-09-04-perbaikan-audit-akademik-putaran-3.md`
- **Plan**: `.agents/plans/2026-09-04-perbaikan-audit-akademik-putaran-3.md`
- **Base Commit**: `336fb5b0`
- **Head Commit**: `c000f12c`

---

## 1. Apa yang Dikerjakan

Paket perbaikan ke-3 dari rangkaian audit modul Akademik berhasil menyelesaikan 8 task (Task 1 hingga Task 8) secara berurutan dan terverifikasi penuh:

1. **Task 1 (Critical Financial Bug)**: `JenisTagihanSasaranMatcher.php`
   - Membatasi semua query siswa penerima tagihan pada `resolveSasaran()` (`semua_siswa`, `per_tingkat`, `per_jurusan`, `per_kelas`, `per_kelompok`) hanya untuk siswa berstatus aktif (`status = 'aktif'`).
   - Mencegah siswa non-aktif (lulus, keluar, pindah) memicu tagihan billing baru.
   - Commit: `ff90002c` `fix(keuangan): JenisTagihanSasaranMatcher exclude siswa non-aktif dari semua jalur billing`.

2. **Task 2 (Important Security & Correctness)**: `StoreRppRequest.php`, `RppController.php`, `_modal-form.blade.php`
   - Menghapus fallback pemilihan guru acak (`Guru::where('lembaga_id', ...)->first()`) yang keliru mengatribusikan RPP admin ke sembarang guru.
   - Menambahkan field `guru_id` opsional di request, dengan aturan validasi bersyarat: jika user bukan profil Guru, `guru_id` wajib diisi, bertipe integer, dan harus milik lembaga yang sama dengan `kelas_id`.
   - Menambahkan dropdown pilihan Guru pada modal form RPP untuk role admin/kurikulum.
   - Commit: `1f8fe682` `fix(akademik): guru_id eksplisit tervalidasi di RPP, hapus fallback guru acak`.

3. **Task 4 (Important Multi-Tenant Cross-Tenant Validation)**: `RppController.php`
   - Pada jalur admin (`$guru === null`), menambahkan re-check tenant untuk `mata_pelajaran_id`: `abort_if($mapel === null || $mapel->lembaga_id !== $kelas->lembaga_id, 404)`.
   - Commit: `691c5510` `fix(akademik): re-check tenant mata_pelajaran_id di jalur admin RppController::store()`.

4. **Task 3 (Important Concurrency / Race Condition)**: `CreateKomponenPenilaianAction.php` & `UpdateKomponenPenilaianAction.php`
   - Mencegah race condition penghitungan total bobot komponen penilaian (> 100%) saat ada submit paralel dengan membungkus seluruh aksi ke dalam `DB::transaction()` dan melakukan `Semester::where('id', ...)->lockForUpdate()->first()` sebelum query `sum('bobot')`.
   - Mempertahankan baris valid `->where('id', '!=', $komponen->id)` pada update.
   - Commit: `f71c1ef6` `fix(akademik): cegah race condition validasi total bobot Komponen Penilaian via lockForUpdate pada Semester`.

5. **Task 5 (Usability & Data Integrity)**: `SiswaController.php`
   - Memfilter dropdown `kelasList` pada `create()` dan `edit()` hanya ke kelas dari tahun ajaran aktif (`status_aktif = true`).
   - Mempertahankan (preserve) kelas lama siswa saat `edit()` jika siswa saat ini masih tercatat di kelas dari TA non-aktif, sehingga form tidak rusak saat dibuka dan submit tanpa ubah field tidak memindahkan siswa.
   - Commit: `9897f0bf` `fix(akademik): filter dropdown kelas ke TA aktif di create/edit siswa, tetap preservasi kelas existing`.

6. **Task 6 (Security / Session-Staleness)**: `ResolveLembagaScopeTrait.php`, `GuruController.php`, `KalenderAkademikController.php`, `PengaturanAkademikController.php`
   - Menambahkan method baru `resolveActiveLembagaId(User $actor): ?int` pada trait `ResolveLembagaScopeTrait` untuk verifikasi kepemilikan `active_lembaga_id` di sesi bagi actor yayasan.
   - Mengganti method internal `resolveLembagaId()` pada `GuruController` agar mendelegasikan ke `resolveActiveLembagaId()`.
   - Memperbaiki titik resolve pada `KalenderAkademikController` (`store()`, `update()`, `destroy()`) dan `PengaturanAkademikController` (`index()`, `updateHariAktif()`).
   - Commit: `f93e6b0c` `fix(akademik): verifikasi ulang session active_lembaga_id di titik pakai -- GuruController, KalenderAkademikController, PengaturanAkademikController`.

7. **Task 7 (Data History / Audit Trail)**: `ProsesKenaikanKelasAction.php`
   - Mengisi kolom `kelas_terakhir_id` dari nilai `kelas_id` saat siswa dinyatakan lulus massal via fitur Kenaikan Kelas.
   - Urutan array update disusun ketat: `'kelas_terakhir_id' => DB::raw('kelas_id')` sebelum `'kelas_id' => null` untuk evaluasi berurutan SQL.
   - Commit: `fb1efb33` `fix(akademik): isi kelas_terakhir_id saat siswa lulus massal lewat Kenaikan Kelas`.

8. **Task 8 (Full Test Suite Verification)**:
   - Menjalankan `php artisan test --compact` untuk seluruh test suite di proyek:
     **2,782 passed (7,579 assertions)**, 0 failures, 0 regressions.
   - `vendor/bin/pint --dirty --format agent`: passed.
   - Commit: `c000f12c` `docs: selesaikan seluruh checklist plan audit akademik putaran 3`.

---

## 2. Keputusan Penting yang Diambil

1. **Task 1 Scope Guard**:
   - `TagihanBillingGenerator.php` dan `GenerateTagihanForUpdatedClass.php` sama sekali tidak disentuh sesuai instruksi ketat user (karena branch `keuangan-v2` sedang menggarap generator secara paralel). Root cause hanya diselesaikan di matcher target (`JenisTagihanSasaranMatcher`).
2. **Task 2 Guru Query Scoping**:
   - Validasi rule pada `StoreRppRequest` menggunakan `Guru::withoutGlobalScopes()->find($guruId)` untuk pengecekan lembaga, karena `Guru` menerapkan `BelongsToTenant`.
3. **Task 6 Non-PPDB Scope Isolation**:
   - Controller PPDB (`JalurPpdbController` dan `GelombangPpdbController`) sama sekali tidak disentuh di putaran ini sesuai instruksi user (akan ditangani terpisah).
   - Method `resolveLembagaId(Request $request)` di `GuruController` tetap dipertahankan namanya dan mendelegasikan ke method trait baru `resolveActiveLembagaId($request->user())`.
4. **Task 7 Array Order**:
   - Urutan `'kelas_terakhir_id' => DB::raw('kelas_id')` diletakkan tepat sebelum `'kelas_id' => null` untuk mencegah nullification sebelum copy nilai di level database engine.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Utang Teknis PPDB (#7-PPDB)**:
   - `JalurPpdbController` dan `GelombangPpdbController` yang memiliki pola session-staleness serupa dengan Task 6 telah dicatat sebagai utang teknis untuk siklus audit PPDB berikutnya.
2. **Utang Teknis Activitylog Kenaikan Kelas (#9)**:
   - `ProsesKenaikanKelasAction` menggunakan Eloquent mass-update (`Siswa::where(...)->update(...)`) yang secara desain tidak mentrigger model events / Activitylog. Ditunda sampai ada kebutuhan requirement spesifik untuk histori kenaikan kelas granular.
3. **Git State Saat Ini**:
   - Branch: `akademik-v2`
   - Status: Clean working tree, semua commit tersimpan lokal di branch `akademik-v2`. Belum di-push ke remote repository (menunggu konfirmasi user/tim).
