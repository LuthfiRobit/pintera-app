# 📋 Master Roadmap: Penyempurnaan Modul Akademik & Fondasi Data Induk

- **Document ID / Slug:** `2026-08-17-1015-penyempurnaan-modul-akademik`
- **Master Spec File:** [`.agents/specs/2026-08-17-1015-penyempurnaan-modul-akademik.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-17-1015-penyempurnaan-modul-akademik.md)
- **Master Plan File:** `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`
- **Master Handoff Log:** `.agents/logs/2026-08-17-1015-penyempurnaan-modul-akademik.md`
- **Tanggal & Waktu:** 17 Agustus 2026, 10:15 WIB
- **Status Master:** 🟡 IN PROGRESS (Sedang mengerjakan Sub-Task 01)

---

## 📌 PANDUAN NAVIGASI UNTUK AGENT BARU / SESI BERIKUTNYA

> **PENTING BAGI AGENT YANG MELANJUTKAN SESI INI:**
> Dokumen ini adalah **Master Blueprint**. Untuk mengeksekusi atau melihat checklist pengerjaan saat ini, buka berkas **Sub-Task Aktif** pada tabel di bawah ini:

| No | Sub-Task | File Spec Sub-Task | File Plan Sub-Task | File Handoff Log | Status |
|:---:|---|---|---|---|:---:|
| **01** | **Fondasi & Jadwal Sarpras Anti-Bentrok** | [`.agents/specs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md) | [`.agents/plans/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md) | [`.agents/logs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md) | 🟢 **SELESAI (COMPLETED)** |
| **02** | **Perangkat Ajar (RPP)** | [`.agents/specs/2026-08-17-1240-akademik-02-rpp.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-17-1240-akademik-02-rpp.md) | [`.agents/plans/2026-08-17-1240-akademik-02-rpp.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1240-akademik-02-rpp.md) | [`.agents/logs/2026-08-17-1240-akademik-02-rpp.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-08-17-1240-akademik-02-rpp.md) | 🟢 **SELESAI (COMPLETED)** |
| **03** | **Jurnal KBM & Presensi Adaptif** | `.agents/specs/akademik-03-jurnal-presensi.md` | `.agents/plans/akademik-03-jurnal-presensi.md` | `.agents/logs/akademik-03-jurnal-presensi.md` | ⚪ PENDING (SIAP DIKERJAKAN) |
| **04** | **Adaptive E-Rapor Engine** | `.agents/specs/akademik-04-e-rapor.md` | `.agents/plans/akademik-04-e-rapor.md` | `.agents/logs/akademik-04-e-rapor.md` | ⚪ PENDING |

---

## 1. Ikhtisar & Strategi Eksekusi
Rencana kerja ini mengimplementasikan spesifikasi arsitektur Modul Akademik dan Data Induk secara bertahap menggunakan prinsip **Strangler Fig Pattern** dan standar [`laravel-feature-standard`](file:///d:/laragon/www/pintera-app/.agents/skills/laravel-feature-standard/SKILL.md).

---

## 2. Checklist Rinci Tahapan Kerja (*Atomic Checklist*)

### 🚀 FASE 1: Scaffolding Domain & Integrasi Jadwal-Sarpras Anti-Bentrok
- [ ] **1.1. Scaffolding Struktur Direktori Domain:**
  - [ ] Buat direktori `app/Domains/Akademik/Actions/Jadwal/`, `app/Domains/Akademik/DataTransferObjects/`, `app/Domains/Akademik/Enums/`, `app/Domains/Akademik/Models/`, `app/Domains/Akademik/Services/`.
  - [ ] Buat direktori `app/Domains/DataInduk/Actions/` dan `app/Domains/DataInduk/DataTransferObjects/`.
- [ ] **1.2. DTO & Actions Jadwal Pelajaran:**
  - [ ] Buat `JadwalPelajaranData` readonly DTO.
  - [ ] Buat `CreateJadwalPelajaranAction` (mengintegrasikan `ValidateRoomClashAction` & anti-bentrok guru).
  - [ ] Buat `UpdateJadwalPelajaranAction` & `DuplicateJadwalPelajaranAction`.
  - [ ] Buat FormRequest `StoreJadwalPelajaranRequest` & `UpdateJadwalPelajaranRequest`.
- [ ] **1.3. Integrasi UI Jadwal & Ruangan Sarpras:**
  - [ ] Pasang dropdown `ruangan_id` di `resources/views/admin/jadwal-pelajaran/create.blade.php` & `edit.blade.php` (otomatis terisi dari `kelas.ruangan_id`).
  - [ ] Refactor `Admin\JadwalPelajaranController` menjadi Thin Controller berbasis Action.
- [x] **1.4. Pengujian Otomatis Fase 1:**
  - [x] Buat test `Tests\Feature\Akademik\JadwalSarprasCollisionTest`.
  - [x] Jalankan `php artisan test --filter=JadwalSarprasCollisionTest` dan pastikan seluruh test lulus 100%.

---

### 📚 FASE 2: Manajemen Perangkat Mengajar (RPP / Modul Ajar)
- [x] **2.1. Skema Database & Model RPP:**
  - [x] Buat file migrasi `create_rpp_table.php` (`lembaga_id`, `guru_id`, `mata_pelajaran_id`, `kelas_id`, `semester_id`, `judul_topik`, `alokasi_waktu`, `file_path`, `status`, `catatan_revisi`, `verified_by_user_id`, `verified_at`).
  - [x] Buat model `App\Domains\Akademik\Models\Rpp.php` dengan relasi `guru`, `mataPelajaran`, `kelas`, `semester`, `tahunAjaran`, `verifiedBy`.
  - [x] Buat enum `App\Domains\Akademik\Enums\StatusRpp.php` (`Draft`, `Diajukan`, `Disetujui`, `PerluRevisi`).
- [x] **2.2. DTO & Actions RPP:**
  - [x] Buat `RppData` readonly DTO.
  - [x] Buat `CreateRppAction`, `UpdateRppAction`, `SubmitRppAction`, `VerifyRppAction`, `DeleteRppAction`.
  - [x] Buat FormRequest `StoreRppRequest`, `UpdateRppRequest`, dan `VerifyRppRequest`.
- [x] **2.3. Controller & View RPP Standar Portal Scope:**
  - [x] Buat `App\Http\Controllers\Admin\RppController.php` dengan tab switcher `saya` (Guru) dan `verifikasi` (Waka Kurikulum).
  - [x] Standardisasi view di `resources/views/portals/lembaga/akademik/rpp/` (`index`, `_daftar`, `_modal-form`, `_modal-verify`).
  - [x] Integrasi native in-platform viewer via `<x-image-preview-modal />` & `$store.imagePreview` (identik pengadaan).
- [x] **2.4. Pengujian Otomatis Fase 2:**
  - [x] Buat test `Tests\Feature\Akademik\RppWorkflowTest` (8 tests passed, 33 assertions).

---

### 📝 FASE 3: Jurnal KBM Adaptif & Presensi Siswa Multi-Jenjang
- [ ] **3.1. Domain Service Agregasi Presensi:**
  - [ ] Buat `App\Domains\Akademik\Services\PresensiAggregationService.php` (menghitung total hari Sakit, Izin, Alpa per siswa dalam 1 semester).
- [ ] **3.2. Actions & DTO Jurnal KBM:**
  - [ ] Buat `JurnalKbmData` & `PresensiSiswaData`.
  - [ ] Buat `RecordJurnalKbmAction` & `RecordPresensiSiswaAction`.
- [ ] **3.3. HTTP & UI Layer Adaptif (KB/TK/SD vs SMP/SMA/SMK):**
  - [ ] Sempurnakan `Guru\SesiPembelajaranController` (atau `Guru\Akademik\JurnalKbmController`):
    - Mode Tematik / Guru Kelas (KB/TK/SD): Form Presensi Harian 1x + Jurnal Harian.
    - Mode Sesi Mapel (SMP/SMA/SMK): Form Jurnal Sesi Tatap Muka + Presensi Jam Pelajaran.
  - [ ] Buat view rekap kehadiran semesteran untuk Wali Kelas.
- [ ] **3.4. Pengujian Otomatis Fase 3:**
  - [ ] Buat test `Tests\Unit\Domains\Akademik\PresensiAggregationServiceTest`.
  - [ ] Buat test `Tests\Feature\Akademik\JurnalKbmAdaptiveTest`.

---

### 🎓 FASE 4: E-Rapor Berjenjang & Digital PDF Report Card
- [ ] **4.1. Skema Database & Model E-Rapor:**
  - [ ] Buat migrasi `create_pengajuan_rapor_table.php` (`kelas_id`, `semester_id`, `status`, `diajukan_oleh`, `diverifikasi_oleh`, `disetujui_oleh`, `catatan_revisi`, `tanggal_rapor`).
  - [ ] Buat migrasi `create_catatan_wali_kelas_table.php` (`siswa_id`, `semester_id`, `catatan_sikap`, `prestasi`, `keterangan_kenaikan`).
  - [ ] Buat model `PengajuanRapor` dan `CatatanWaliKelas`.
  - [ ] Buat enum `StatusPengajuanRapor` (`Draft`, `Diajukan`, `Diverifikasi`, `Disetujui`, `Ditolak`).
- [ ] **4.2. Domain Services Rapor:**
  - [ ] Buat `App\Domains\Akademik\Services\RaporCalculationService.php` (kalkulasi nilai akhir berbobot).
  - [ ] Buat `App\Domains\Akademik\Services\CapaianKompetensiGenerator.php` (generator narasi otomatis TP tertinggi & terendah).
- [ ] **4.3. Actions Pengajuan & Approval Rapor:**
  - [ ] Buat `SubmitPengajuanRaporAction`, `VerifyPengajuanRaporAction`, `ApprovePengajuanRaporAction`, dan `SimpanCatatanWaliKelasAction`.
- [ ] **4.4. 4 Template PDF Resmi DomPDF Berbasis Jenjang:**
  - [ ] `resources/views/pdf/rapor/paud.blade.php` (Naratif 3 elemen CP).
  - [ ] `resources/views/pdf/rapor/sd.blade.php` (Nilai pokok/tematik + narasi TP + ekskul + absensi).
  - [ ] `resources/views/pdf/rapor/smp-sma.blade.php` (Nilai umum & peminatan + narasi TP + ekskul + absensi).
  - [ ] `resources/views/pdf/rapor/smk.blade.php` (Nilai umum & kejuruan + nilai PKL Industri + UKK).
- [ ] **4.5. Controller & UI E-Rapor Berjenjang:**
  - [ ] Portal Guru/Wali Kelas (`Guru\Akademik\RaporWaliKelasController`): Input catatan sikap, ekskul, review narasi TP, & ajukan.
  - [ ] Portal Lembaga/Kurikulum (`Lembaga\Akademik\RaporController`): Verifikasi Kurikulum & Approval Kepala Sekolah (Kunci Nilai) & Cetak PDF.
- [ ] **4.6. Pengujian Otomatis Fase 4:**
  - [ ] Buat test `Tests\Feature\Akademik\RaporBerjenjangWorkflowTest`.
  - [ ] Buat test `Tests\Feature\Akademik\RaporPdfGenerationTest`.

---

### 🌐 FASE 5: Restrukturisasi Rute, Dynamic Permission Sync & Verifikasi Akhir
- [ ] **5.1. Pendaftaran Rute Modular:**
  - [ ] Susun berkas rute modular `routes/lembaga.php`, `routes/guru.php`, `routes/siswa.php`, `routes/orang-tua.php`, dan `routes/yayasan.php`.
  - [ ] Pertahankan route alias untuk backward compatibility.
- [ ] **5.2. Auto-Discovery Permissions Sync:**
  - [ ] Jalankan `php artisan permissions:sync` dan pastikan seluruh permission akademik baru terdaftar rapi.
- [ ] **5.3. Full Regression Test Suite:**
  - [ ] Jalankan `php artisan test` secara menyeluruh dan pastikan 100% test lulus hijau tanpa kegagalan.

---

## 3. Rencana Pengujian (*Testing Plan*)

### Automated Tests:
```bash
php artisan test --filter=Akademik
php artisan test --filter=Sarpras
php artisan test
```

### Manual Verification:
- **Jadwal:** Simpan jadwal dengan ruangan & guru sama di jam beririsan $\to$ harus ditolak sistem.
- **RPP:** Upload berkas oleh guru $\to$ ajukan $\to$ verifikasi oleh kurikulum.
- **Presensi & Jurnal:** Uji presensi mode harian (SD) dan mode sesi jam (SMP/SMK) $\to$ verifikasi agregasi S/I/A.
- **E-Rapor:** Input asesmen $\to$ cek narasi TP otomatis $\to$ input catatan wali $\to$ verifikasi kurikulum $\to$ approval kepsek $\to$ cetak 4 template PDF.
