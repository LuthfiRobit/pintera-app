# 📝 Handoff Log: Migrasi 3 Modul Akademik Tersisa ke `Domains\Akademik`

- **Task Name:** Migrasi 3 Modul Akademik Tersisa ke `Domains\Akademik` (Sub-Task 05)
- **Tanggal:** 20 Agustus 2026
- **Spec File:** [`.agents/specs/2026-08-20-akademik-05-migrasi-domain-tersisa.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-20-akademik-05-migrasi-domain-tersisa.md)
- **Plan File:** [`.agents/plans/2026-08-20-akademik-05-migrasi-domain-tersisa.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-20-akademik-05-migrasi-domain-tersisa.md)
- **Branch:** `rbac-v2`
- **Status:** 🟢 **SELESAI & VERIFIKASI PENUH**

---

## 1. Apa yang Dikerjakan

Migrasi 3 modul Akademik tersisa yang sebelumnya masih menggunakan Fat Controller / Fat Model legacy ke standar modular DDD `app/Domains/Akademik/` sesuai arsitektur `laravel-feature-standard`:

### Modul 1: Kalender & Pengaturan Akademik
- **Model & Service:**
  - `app/Models/KalenderAkademik.php` $\to$ `app/Domains/Akademik/Models/KalenderAkademik.php` (dengan `newFactory()` resolution).
  - `app/Services/KalenderAkademikResolver.php` $\to$ `app/Domains/Akademik/Services/KalenderAkademikResolver.php`.
- **DTO:**
  - `app/Domains/Akademik/DataTransferObjects/HariAktifLembagaData.php`
  - `app/Domains/Akademik/DataTransferObjects/KalenderAkademikData.php`
- **Actions:**
  - `app/Domains/Akademik/Actions/Kalender/UpdateHariAktifLembagaAction.php`
  - `app/Domains/Akademik/Actions/Kalender/CreateKalenderAkademikAction.php`
  - `app/Domains/Akademik/Actions/Kalender/UpdateKalenderAkademikAction.php`
  - `app/Domains/Akademik/Actions/Kalender/DeleteKalenderAkademikAction.php`
- **Controller Refactor:**
  - `app/Http/Controllers/Admin/PengaturanAkademikController.php` (Thin Controller)
  - `app/Http/Controllers/Admin/KalenderAkademikController.php` (Thin Controller)
- **Perilaku:** Zero-behavior-change (100% identik kode asli, semua test lama lolos tanpa perubahan).

### Modul 2: Pola Jam & Jam Pelajaran
- **Model Relocation:**
  - `app/Models/PolaJam.php` $\to$ `app/Domains/Akademik/Models/PolaJam.php`
  - `app/Models/JamPelajaran.php` $\to$ `app/Domains/Akademik/Models/JamPelajaran.php`
  - Diupdate di 36 file consumer (seeder, factory, controller, model relation, test).
- **DTO:**
  - `app/Domains/Akademik/DataTransferObjects/PolaJamData.php`
  - `app/Domains/Akademik/DataTransferObjects/AssignKelasData.php`
  - `app/Domains/Akademik/DataTransferObjects/JamPelajaranData.php`
- **Actions (`app/Domains/Akademik/Actions/PolaJam/`):**
  - `CreatePolaJamAction.php`
  - `UpdatePolaJamAction.php`
  - `DeletePolaJamAction.php`
  - `AssignKelasToPolaJamAction.php`
  - `DuplicatePolaJamAction.php`
  - `CreateJamPelajaranAction.php`
  - `UpdateJamPelajaranAction.php`
  - `DeleteJamPelajaranAction.php`
- **Controller Refactor:**
  - `app/Http/Controllers/Admin/PolaJamController.php` (Thin Controller)
  - `app/Http/Controllers/Admin/JamPelajaranController.php` (Thin Controller)
- **Perilaku:** Zero-behavior-change (100% identik kode asli).

### Modul 3: Kenaikan Kelas (dengan Mitigasi Salin Jadwal Clashing)
- **DTO:**
  - `app/Domains/Akademik/DataTransferObjects/KenaikanKelasData.php`
- **Action:**
  - `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php`
- **Controller Refactor:**
  - `app/Http/Controllers/Admin/KenaikanKelasController.php` (Thin Controller)
- **Perubahan Perilaku Sengaja:** Saat `salinJadwal` dipilih, penyalinan jadwal pelajaran memanggil `CreateJadwalPelajaranAction` yang memeriksa bentrok guru & ruangan sarpras. Baris yang bentrok dilewati secara lokal (*skip-and-report*) dan dilaporkan di flash message status, tanpa menggagalkan proses kenaikan kelas atau kelulusan siswa.

---

## 2. Keputusan Penting yang Diambil

1. **Struktur Agregat Pola Jam & Jam Pelajaran:** Sesuai spec §3.2, `JamPelajaran` ditempatkan dalam sub-domain yang sama dengan `PolaJam` (`app/Domains/Akademik/Actions/PolaJam/`), bukan namespace terpisah, karena keduanya merupakan 1 siklus agregat.
2. **Penanganan Factory Model Domain:** Menambahkan static method `newFactory()` di `KalenderAkademik`, `PolaJam`, dan `JamPelajaran` untuk memastikan resolusi factory `Database\Factories\<ModelName>Factory` bekerja secara konsisten tanpa merelokasi factory.
3. **Idempotensi `salinJadwal`:** Sebelum memanggil `CreateJadwalPelajaranAction`, `salinJadwal()` melakukan pengecekan keberadaan slot yang sama persis (`exists()`) untuk mempertahankan sifat idempoten tanpa memicu false-positive error.
4. **Zero-leak Scope & Namespace Audit:** Audit ripgrep memastikan 0 import atau referensi ke model/service di path legacy `App\Models\KalenderAkademik`, `App\Models\PolaJam`, `App\Models\JamPelajaran`, maupun `App\Services\KalenderAkademikResolver`.

---

## 3. Hasil Pengujian & Verifikasi

- **Task 1:** 41 passed
- **Task 2:** 25 passed
- **Task 3:** 46 passed
- **Task 4:** 163 passed (22 test suite berelasi)
- **Task 5:** 27 passed
- **Task 6:** 7 passed
- **Task 7:** 12 passed
- **Task 8 (Scoped 3 Modul):** 95 passed, 0 failed (237 assertions)
- **Task 8 (Full Test Suite):** **1875 passed, 0 failed** (5747 assertions, 561.91s)
  - *Baseline sebelum migrasi:* 1861 passed
  - *Delta:* +14 unit tests baru untuk Action DTO Domain.

---

## 4. Hal yang Perlu Direview Manusia / Claude

- **State Git Saat Ini:**
  - Branch: `rbac-v2`
  - Status: Bersih, semua task ter-commit atomic per task (commits: `7a501d0`, `df74adb`, `7e4937f`, `f5da841`, `d9502a4`, `5bca913`, `2eb0880`).
- **Domain Coverage Akademik:**
  - Seluruh modul Akademik (Jadwal, RPP, Jurnal KBM, Presensi, Asesmen, Nilai, E-Rapor, Kalender, Pola Jam, Jam Pelajaran, Kenaikan Kelas) kini 100% berada dalam struktur standar `app/Domains/Akademik/`.
