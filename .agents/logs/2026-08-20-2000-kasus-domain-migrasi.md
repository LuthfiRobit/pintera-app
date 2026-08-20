# 📝 Handoff Log: Migrasi Domain Kasus ke `App\Domains\Kasus`

- **Document ID / Slug:** `2026-08-20-2000-kasus-domain-migrasi`
- **Tanggal & Waktu:** 20 Agustus 2026, 20:55 WIB
- **Branch:** `rbac-v2`
- **Spec:** [`.agents/specs/2026-08-20-2000-kasus-domain-migrasi.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-20-2000-kasus-domain-migrasi.md)
- **Plan:** [`.agents/plans/2026-08-20-2000-kasus-domain-migrasi.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-20-2000-kasus-domain-migrasi.md)
- **Status Akhir:** 🟢 **SELESAI TOTAL (13 Task, 100% PASS, Zero Regressions)**

---

## 1. Apa yang Dikerjakan

Migrasi menyeluruh modul Pendampingan (Kasus) legacy ke arsitektur modular `app/Domains/Kasus/` sesuai standar `laravel-feature-standard` dan blueprint master `2026-08-20-1800-master-refactor-domain-pattern.md`.

### Ringkasan Komponen yang Dimigrasi / Dibuat:

1. **6 Model** dipindahkan ke `app/Domains/Kasus/Models/` lengkap dengan `newFactory()`:
   - `Kasus`, `KasusConsent`, `KasusEvaluasi`, `KasusSesi`, `KasusTugas`, `KasusTugasSubmission`.
   - Update 83 consumer files (Models, Controllers, Services, Notifications, Factories, Seeders, Tests).
2. **3 Enum** dipindahkan ke `app/Domains/Kasus/Enums/`:
   - `StatusKasus`, `StatusKasusSesi`, `StatusKasusTugas`.
3. **9 DataTransferObjects (DTO)** di `app/Domains/Kasus/DataTransferObjects/`:
   - `KonselorKandidat` (dipindah dari App\DataTransferObjects).
   - `AssignKonselorData`, `AjukanKasusData`, `JadwalkanSesiData`, `UpdateStatusSesiData`, `BeriTugasBatchData`, `SubmitBuktiTugasData`, `ReviewSubmissionData`, `CatatEvaluasiData`.
4. **2 Services** dipindahkan ke `app/Domains/Kasus/Services/`:
   - `KonselorAllocationResolver`, `TugasBatchGenerator`.
5. **1 Policy Terkonsolidasi** di `app/Domains/Kasus/Policies/KasusPolicy.php`:
   - Mengkonsolidasikan 4 duplikasi otorisasi konselor/peserta: `isKonselor()`, `view()`, `downloadLampiran()`, `kelolaSesiTugas()`.
6. **13 Action Terisolasi** (1 use-case per action) di `app/Domains/Kasus/Actions/`:
   - **Manajemen:** `AssignKonselorAction`, `DestroyKasusAction`, `RestoreKasusAction`.
   - **Pengajuan:** `ListKasusUntukUserAction`, `AjukanKasusAction`.
   - **Consent:** `ApproveConsentAction`.
   - **Sesi:** `JadwalkanSesiAction`, `UpdateStatusSesiAction`.
   - **Tugas:** `BeriTugasBatchAction`, `TandaiTugasSelesaiAction`.
   - **Submission:** `SubmitBuktiTugasAction`, `ReviewSubmissionAction`.
   - **Evaluasi:** `CatatEvaluasiAction`.
7. **8 Controller Disederhanakan (Thin Controllers)**:
   - `Admin\KasusController`, `Admin\KasusAksesLogController`, `Admin\KasusTerhapusController`, `KasusController`, `KasusConsentController`, `KasusSesiController`, `KasusTugasController`, `KasusTugasBatchPreviewController`, `KasusTugasSubmissionController`, `KasusEvaluasiController`.
8. **11 Blade Views Dipindahkan**:
   - `resources/views/kasus/*` (7 file) → `resources/views/portals/kasus/*`.
   - `resources/views/admin/kasus/*` (4 file) → `resources/views/portals/lembaga/kasus/*`.
   - Seluruh route name (`route('kasus.xxx')` & `route('admin.kasus.xxx')`) tetap dipertahankan 100% tanpa perubahan.

---

## 2. Riwayat Commit Per Task

| Task | Deskripsi | Commit Hash | Hasil Scoped Test |
|:---:|---|:---:|---|
| Task 1 | Pindahkan 6 Model ke `Domains\Kasus\Models\` | `3eec1ea` | 1875 passed |
| Task 2 | Pindahkan 3 Enum & 1 DTO ke Domain | `4ca7ebc` | 31 passed |
| Task 3 | Pindahkan 2 Service ke `Domains\Kasus\Services\` | `ce5c035` | 59 passed |
| Task 4 | Buat `KasusPolicy` — Konsolidasi Otorisasi | `719f1f2` | 8 passed |
| Task 5 | Sub-Area Manajemen — Actions & Refactor 3 Controller Admin | `1cf3398` | 36 passed |
| Task 6 | Sub-Area Pengajuan — Action & Refactor `KasusController` | `0e4c62f` | 30 passed |
| Task 7 | Sub-Area Consent — Action & Refactor `KasusConsentController` | `5a078f1` | 8 passed |
| Task 8 | Sub-Area Sesi — Actions & Refactor `KasusSesiController` | `3da8fad` | 20 passed |
| Task 9 | Sub-Area Tugas — Actions & Refactor `KasusTugasController` + Preview | `b352db2` | 23 passed |
| Task 10 | Sub-Area Submission — Actions & Refactor `KasusTugasSubmissionController` | `b7872cc` | 30 passed |
| Task 11 | Sub-Area Evaluasi — Action & Refactor `KasusEvaluasiController` | `06e80c9` | 24 passed |
| Task 12 | Pindahkan 11 View ke `portals/kasus` & `portals/lembaga/kasus` | `9f89afa` | 162 passed |
| Task 13 | Master Roadmap Update & Handoff Log | *(commit saat ini)* | **1894 passed (5784 assertions)** |

---

## 3. Keputusan Penting yang Diambil

1. **Konsolidasi Otorisasi ke `KasusPolicy` & Penghapusan Trait:**
   - Trait legacy `app/Http/Controllers/Concerns/AssertsKonselorPemegangKasus.php` diverifikasi sudah 0 pemakaian di seluruh codebase setelah Task 8-9 dan telah dihapus dengan aman via `git rm`.
   - Otorisasi di controller digantikan dengan `$this->authorize('kelolaSesiTugas', $kasus)` dan `$policy->isKonselor()`, `$policy->downloadLampiran()`.
2. **Integritas Route Binding Tanpa TenantScope:**
   - `Route::bind('kasus', ...)` di `routes/kasus.php` yang secara sengaja melakukan bypass `TenantScope` (karena akun Orang Tua memiliki `lembaga_id = null`) dijaga sepenuhnya. Tidak ada pemanggilan ulang `Kasus::query()` ber-scope di dalam Action maupun Controller yang merusak akses lintas entitas ini.
3. **Pemisahan Logika View vs Route:**
   - Seluruh pemindahan folder view ke `portals/kasus/` dan `portals/lembaga/kasus/` dilakukan secara eksplisit tanpa `sed` blanket replace, menjamin bahwa nama route `route('kasus.xxx')` dan `route('admin.kasus.xxx')` tidak terkorupsi.
4. **Scoping Helper Test Standalone:**
   - Helper test seperti `actingAsGuruPengaju`, `actingAsOrangTuaPengaju`, `actingAsKasusTriaseManager`, dan `buatKasusDitugaskanKeGuruBk` dibungkus dengan `if (! function_exists(...)) { ... }` sehingga tiap file test dapat dieksekusi secara independen maupun bersamaan tanpa tabrakan redeclaration.

---

## 4. Hasil Verifikasi Akhir

1. **Zero-Leak Namespace Check:**
   - `App\Models\Kasus`, `App\Models\KasusConsent`, `App\Models\KasusSesi`, `App\Models\KasusTugas`, `App\Models\KasusEvaluasi`: **0 matches** di `app/`, `database/`, `tests/`.
   - `App\Enums\StatusKasus`: **0 matches**.
   - `App\Services\KonselorAllocationResolver`, `App\Services\TugasBatchGenerator`: **0 matches**.
   - `view('kasus.*')`, `view('admin.kasus.*')`: **0 matches**.
2. **Full Test Suite:**
   - Total Tests: **1894 passed** (5784 assertions), 0 failed, 0 errors.
   - Baseline sebelum migrasi: 1875 passed (+19 unit test baru untuk Actions dan Policy).

---

## 5. Hal yang Perlu Direview Manusia / Claude

1. **Status Branch Git:**
   - Semua perubahan sudah rapi ter-commit per-task di branch `rbac-v2`.
   - Tidak ada uncommitted files atau unstaged changes.
2. **Roadmap Selanjutnya:**
   - Modul Kasus (Sub-Task 1) telah selesai 100%. Grup berikutnya yang direkomendasikan pada Master Roadmap (§4) adalah modul **SPMB** (4 controller, 737 baris) atau **Keuangan** (8 controller, 1386 baris).
