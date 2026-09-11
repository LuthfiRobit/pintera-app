# Handoff Log: Perbaikan Audit SDM Izin/Cuti (Crash Pool, HTML Rusak, Pencarian Alasan, Riwayat Admin)

- **Tanggal**: 2026-09-11
- **Branch**: `rbac-v2`
- **Spec**: `.agents/specs/2026-09-11-sdm-izin-cuti-audit-perbaikan.md`
- **Plan**: `.agents/plans/2026-09-11-sdm-izin-cuti-audit-perbaikan.md`
- **Kickoff**: `.agents/kickoff/2026-09-11-sdm-izin-cuti-audit-perbaikan-kickoff.md`

---

## 1. Apa yang Dikerjakan

Menutup 4 temuan konkret hasil 2 putaran audit (scope yayasan/lembaga + UI/UX & wiring) pada modul SDM Izin/Cuti yang semuanya berada di domain SDM:

### 1. Perbaikan Crash Pegawai Pool Yayasan (Task 1)
- **File**: `app/Domains/Sdm/Actions/AjukanIzinCutiAction.php`
- **Masalah**: Pegawai pool yayasan (`Karyawan.lembaga_id` NULL) mengajukan izin/cuti mandiri memicu crash 500 (`QueryException` SQLSTATE 23000 NOT NULL constraint di `pengajuan_izin_cuti.lembaga_id`).
- **Solusi**: Menambahkan validasi guard eksplisit di baris paling awal `execute()`:
  ```php
  if ($pegawai->lembaga_id === null) {
      throw ValidationException::withMessages([
          'pegawai' => 'Pengajuan izin/cuti mandiri belum didukung untuk pegawai pool yayasan (tanpa lembaga tetap). Silakan hubungi admin SDM untuk memprosesnya secara manual.',
      ]);
  }
  ```
- **Test**: `tests/Feature/Sdm/AjukanIzinCutiActionTest.php` (+2 test baru: pool karyawan melempar `ValidationException` tanpa partial write, non-pool karyawan tetap dapat mengajukan secara normal).

### 2. Perbaikan HTML Rusak di Empty-State Riwayat Self-Service (Task 2)
- **File**: `resources/views/sdm/izin-cuti/index.blade.php`
- **Masalah**: Tag `<tr>` dan `<td colspan="5">` dibuka pada blok `@empty` tetapi tidak pernah ditutup sebelum directive `@endforelse`.
- **Solusi**: Menambahkan penutup `</td></tr>` sebelum `@endforelse`.

### 3. Riwayat/Arsip Admin + Pencarian "Alasan" yang Berfungsi (Task 3)
- **Files**:
  - `app/Http/Controllers/Admin/ApprovalIzinCutiController.php`: Query `index()` mengganti filter status terbatas `whereIn('status', [Pending, InReview])` menjadi `whereHas('approvalRequest')` agar seluruh pengajuan (aktif dan yang sudah diputuskan) ikut dikirim ke client-side SPA.
  - `resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php`:
    - Menambahkan mapping field `alasan`, `statusLabel`, `statusTone`, `isDecided`.
    - Menambahkan toggle view mode: `Menunggu` vs `Riwayat`.
    - Input pencarian "Cari Pegawai / Alasan" dengan placeholder interaktif.
    - Menambahkan kolom header & cell `Status` dengan badge pill dinamis (`isDecided`).
    - Stat card "Menunggu Approval" tetap konsisten menghitung seluruh pending menggunakan getter `totalPending`.
    - Pill counter (Semua/Cuti/Sakit/Izin) sekarang adaptif mengikuti `itemsInView` sesuai tab yang aktif.
  - `resources/js/approval-izin-cuti-spa.js`: Logika reaktif Alpine SPA dengan getter `itemsInView`, `filteredItems` yang mencocokkan `item.nama` ATAU `item.alasan`, serta counter kategori adaptif.
  - Asset build: `npm run build` dijalankan ulang untuk mengompilasi bundle Vite (`public/build/assets/app-*.js`).
- **Test**: `tests/Feature/Admin/ApprovalIzinCutiControllerTest.php` (+1 test: memastikan pengajuan aktif dan riwayat keputusan keduanya termuat di response).

---

## 2. Keputusan Penting yang Diambil

1. **Hanya Menutup Crash untuk Pool Pegawai (Fail Gracefully)**:
   - Sesuai spesifikasi §2.1, tidak membuat kolom `pengajuan_izin_cuti.lembaga_id` menjadi nullable di database. Jika dibuat nullable, workflow resolver berbasis role lembaga akan memicu fail-closed dan pengajuan macet permanen. Fitur pengajuan pool memerlukan workflow ber-scope yayasan tersendiri di masa depan.
2. **Mempertahankan Pola Client-Side SPA**:
   - Berbeda dengan Rapor yang menggunakan AJAX server partial replacement, modul SDM sudah established dengan pola Alpine.js reaktif client-side. Seluruh data dikirim sekali di awal dan disaring secara instan tanpa round-trip.
3. **Pill Kategori Adaptif vs Stat Card Statis**:
   - Angka pill kategori (Cuti, Sakit, Izin) sengaja disesuaikan dengan tab aktif (`itemsInView`).
   - Stat card "Menunggu Approval" di header atas sengaja dipertahankan statis (`totalPending`) agar admin selalu memiliki visibility berapa pengajuan yang butuh aksi walau sedang meninjau tab Riwayat.
4. **Scope Creep Guard**:
   - Status `ApprovalStatus::RevisionRequired` sengaja tidak di-wire tombolnya di UI karena controller SDM saat ini hanya menangani `APPROVE` dan `REJECT`.

---

## 3. Hasil Verifikasi

1. **Test Suite Modul SDM**:
   - Perintah: `vendor/bin/pest tests/Feature/Sdm tests/Feature/Admin/ApprovalIzinCutiControllerTest.php --compact`
   - Hasil: **99 passed (232 assertions)** dalam 26.52s.
2. **Pint Code Formatting**:
   - Perintah: `vendor/bin/pint --dirty --format agent`
   - Hasil: Clean (`{"tool":"pint","result":"passed"}`).
3. **Full Project Test Suite (`php artisan test --compact`)**:
   - Total test dijalankan: **3,151 tests** (8,487 assertions).
   - Lulus: **3,147 passed**.
   - Gagal: Hanya 4 kegagalan yang semuanya pre-existing:
     - `Tests\Unit\M3DemoDataSeederTest` (2 tests)
     - `Tests\Feature\Akademik\SubjekTenantValidationTest` (1 test)
     - `Tests\Feature\Akademik\PersetujuanRaporRiwayatTest` (1 test, lolos 100% saat dijalankan mandiri, issue isolasi database global).
4. **Browser & UI Verification (via Browser Subagent)**:
   - Login sebagai `kepsek.sd@demo.test` ke `/admin/kehadiran-sdm/izin-cuti`.
   - Toggle 'Tampilan' (Menunggu vs Riwayat) berfungsi mulus.
   - Kolom 'Status' tampil dengan badge tone yang sesuai.
   - Pencarian berdasarkan alasan maupun nama pegawai berfungsi interaktif.
   - Perhitungan badge pill kategori dan kartu 'Menunggu Approval' terverifikasi akurat.

---

## 4. Git State & Hal yang Perlu Direview

- **Branch aktif**: `rbac-v2` (local ahead of `origin/rbac-v2`).
- **Daftar Commit untuk Task Ini**:
  - `3b6a28bb` — `docs(sdm): spec perbaikan audit izin/cuti (crash pool, HTML rusak, pencarian alasan, riwayat admin)`
  - `777e01dd` — `docs(sdm): plan perbaikan audit izin/cuti (crash pool, HTML rusak, pencarian alasan, riwayat admin)`
  - `f2ac7b7e` — `docs(sdm): kickoff perbaikan audit izin/cuti (crash pool, HTML rusak, pencarian alasan, riwayat admin)`
  - `ee7d2675` — `fix(sdm): cegah crash 500 saat pegawai pool yayasan mengajukan izin/cuti mandiri`
  - `68be8051` — `fix(sdm): tutup markup HTML yang rusak di empty-state riwayat izin/cuti pegawai`
  - `39a91cd9` — `feat(sdm): tambah tab riwayat admin izin/cuti + perbaiki pencarian alasan yang tidak berfungsi`
  - `bc15fe1e` — `docs(sdm): tandai semua task dan step plan audit perbaikan izin/cuti selesai diimplementasi dan diverifikasi`
- **Catatan Sesuai Arahan**:
  - Sesuai instruksi kickoff, branch `rbac-v2` dibiarkan apa adanya (tidak dilakukan merge maupun push manual).
