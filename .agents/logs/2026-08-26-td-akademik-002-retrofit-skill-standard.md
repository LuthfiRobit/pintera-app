# Handoff Log: TD-AKADEMIK-002 (Retrofit Sprint 1-4 Akademik ke `laravel-feature-standard`)

- **Tanggal**: 2026-08-26
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-08-26-td-akademik-002-retrofit-skill-standard.md`
- **Plan**: `.agents/plans/2026-08-26-td-akademik-002-retrofit-skill-standard.md`
- **Status Akhir**: SELESAI & TERVERIFIKASI (Full Test Suite: 2238 passed, 4 skipped, 0 failed, 6162 assertions)

---

## 1. Apa yang Dikerjakan

Pembersihan teknis (technical debt) dari Sprint 1-5 Fondasi Akademik Multi-Jenjang untuk menyelaraskan struktur kode dengan konvensi arsitektur `laravel-feature-standard` (FormRequest + DTO + Action), tanpa mengubah satu pun behavior, pesan validasi, atau HTTP response code yang sudah ada.

Secara rinci per task:
1. **Task 1 — Pindahkan `Support/` → `Services/`** (`09d11bc9`):
   - Memindahkan `SubjekPenilaianKey.php` dan `AcademicProfile.php` dari `app/Domains/Akademik/Support/` ke `app/Domains/Akademik/Services/`.
   - Memperbarui `use` statement di consumer `RaporPdfDataBuilder.php` dan `RaporCalculationService.php`.
   - Memindahkan unit tests ke `tests/Unit/Services/SubjekPenilaianKeyTest.php` dan `tests/Unit/Services/AcademicProfileTest.php`.
   - Menghapus direktori `app/Domains/Akademik/Support/`.
   - Memastikan tidak ada referensi `Domains\Akademik\Support` yang tersisa di seluruh file PHP aktif.

2. **Task 2 — Retrofit `Admin\FaseDefaultMappingController`** (`130324af`):
   - Membuat DTO `app/Domains/Akademik/DataTransferObjects/FaseDefaultMappingData.php`.
   - Membuat Action `app/Domains/Akademik/Actions/FaseMapping/CreateFaseDefaultMappingAction.php` dan `UpdateFaseDefaultMappingAction.php`.
   - Membuat FormRequests `app/Http/Requests/Akademik/StoreFaseDefaultMappingRequest.php` dan `UpdateFaseDefaultMappingRequest.php`.
   - Mengubah `FaseDefaultMappingController::store()` dan `update()` agar mengonsumsi FormRequest, DTO, dan Action.
   - Menambahkan unit tests Action (`CreateFaseDefaultMappingActionTest`, `UpdateFaseDefaultMappingActionTest`).
   - Memverifikasi 9 test `FaseDefaultMappingControllerTest` tetap 100% hijau tanpa satu pun assertion diubah.

3. **Task 3 — Retrofit `Admin\KelasController`** (`6c5e674a`):
   - Membuat DTO `app/Domains/Akademik/DataTransferObjects/KelasData.php` dengan static factory `KelasData::fromValidated()`.
   - Membuat Action `app/Domains/Akademik/Actions/Kelas/CreateKelasAction.php` dan `UpdateKelasAction.php` yang menyerap logic ownership-check (guru/tahun_ajaran/pola_jam) dengan response abort 404 identik.
   - Membuat FormRequests `app/Http/Requests/Akademik/StoreKelasRequest.php` dan `UpdateKelasRequest.php` (tanpa menambahkan `exists:` baru untuk menjaga response 404).
   - Mengubah `KelasController::store()` dan `update()` agar mengonsumsi FormRequest, DTO, dan Action.
   - Menambahkan unit tests Action (`CreateKelasActionTest`, `UpdateKelasActionTest`).
   - Memverifikasi seluruh 10 test `KelasCrudTest` (termasuk 6 test cross-tenant 404), `KelasPolaJamTest`, `KelasFaseAssignmentTest`, dan `KelasFaseSuggestionTest` tetap 100% hijau tanpa satu pun assertion diubah.

4. **Task 4 — Verifikasi Penuh & Regresi**:
   - Menjalankan full test suite tanpa filter: `php artisan test`.
   - **Hasil Aktual**: **2238 passed, 4 skipped, 0 failed** (6162 assertions, durasi 530.20s).
   - Verifikasi grep nol referensi `Domains\Akademik\Support` di seluruh workspace PHP aktif.

---

## 2. Keputusan Penting yang Diambil

1. **Pemilihan `Services/` untuk Stateless Helper**:
   - `laravel-feature-standard` mendefinisikan 7 folder resmi (`Actions/DataTransferObjects/Events/Listeners/Models/Services/ViewModels`).
   - `SubjekPenilaianKey` dan `AcademicProfile` ditempatkan di `Services/` mengikuti preseden stateless service di skill (`PermissionContextService`).

2. **Pemisahan Tanggung Jawab HTTP Response vs Business Mutation**:
   - Uniqueness check `FaseDefaultMapping` dan `authorizeMappingScope()` tetap berada di `FaseDefaultMappingController` karena keduanya menghasilkan HTTP response (`back()->withErrors()`, `abort(403)`). Action murni menangani mutasi Eloquent.

3. **Pelestarian Strict HTTP Status Code pada `KelasController`**:
   - Dilarang keras menambahkan rule `exists:` baru pada `tahun_ajaran_id`, `wali_kelas_guru_id`, dan `pola_jam_id` di `StoreKelasRequest`/`UpdateKelasRequest`. Hal ini memastikan bahwa ID tidak valid/lintas-lembaga tetap menghasilkan HTTP 404 (bukan HTTP 422 redirect validation), menjaga kompatibilitas kontrak dengan `KelasCrudTest`.

4. **Penanganan `BelongsToTenant` pada `Kelas`**:
   - `CreateKelasAction` menerima `KelasData` dan parameter kedua opsional `?int $lembagaIdOverride = null` (untuk context yayasan-scope dari session).
   - Pada unit test `CreateKelasActionTest`, user lembaga di-autentikasi via `actingAs` agar trait `BelongsToTenant` mengisi `lembaga_id` secara alami seperti pada request HTTP sebenarnya.

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Git State**:
   - Branch: `akademik-v2`
   - Commit Task 1: `09d11bc9`
   - Commit Task 2: `130324af`
   - Commit Task 3: `6c5e674a`
   - Working tree: Clean.

2. **Status Technical Debt Akademik**:
   - `TD-AKADEMIK-002`: **SELESAI TOTAL**.
   - `TD-AKADEMIK-001` (`ElemenCp` vs `aspek_perkembangan` PAUD): Tetap deferred/di luar scope sesuai rencana roadmap terpisah.
