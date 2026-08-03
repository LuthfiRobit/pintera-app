# Technical Design: Komponen Penilaian Pro-Max & Bobot Ecosystem Integration
**Date**: 2026-08-03
**Status**: Validated & Ready for Review
**Approach**: Approach 1 - The Full Pro-Max Unified Ecosystem (Strict 100% Guard + SPA Modals)

---

## 1. Purpose & Objectives
The assessment component module (**Komponen Penilaian**) serves as the curricular structure defining how student grades are evaluated for each subject (*Mata Pelajaran*) in a given semester. Currently, the schema lacks a weight (*bobot*) dimension, forcing report card (*Rapor*) computations to rely on simple arithmetic unweighted averages.

This redesign introduces:
1. **Curricular Weight Integration**: A mandatory `bobot` percentage column (1–100%) directly integrated into the baseline schema and seeders.
2. **Strict 100% Weight Guard**: Business logic preventing cumulative component weights for any subject/semester from exceeding 100%, accompanied by descriptive validation error formatting.
3. **Weighted Report Card Engine**: Transformation of `RaporController` evaluation algorithms from flat averages to proportionally weighted averages.
4. **Unified Pro-Max SPA Modals**: Transitioning both Admin and Guru portals from multi-page reloads to reactive zero-redirect SPA dialogs featuring **TomSelect Pro-Max** dropdowns and an interactive **Live Weight Progress Bar**.

---

## 2. Database & Schema Architecture

### 2.1 Baseline Migration Refactor
Rather than creating an add-on migration, the core migration table definition in `database/migrations/2026_07_25_130000_create_komponen_penilaian_table.php` will be updated directly:

```php
Schema::create('komponen_penilaian', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
    $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
    $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
    $table->string('kode')->nullable();
    $table->text('deskripsi');
    $table->unsignedTinyInteger('bobot')->default(10); // NEW: percentage weight (1 - 100)
    $table->text('kktp')->nullable();
    $table->timestamps();

    $table->index(['lembaga_id', 'mata_pelajaran_id', 'semester_id'], 'idx_komp_lmbg_mapel_smt');
});
```

### 2.2 Seeder & Factory Realignment
- `KomponenPenilaianSeeder.php` will seed three canonical components per subject and semester totaling exactly 100%:
  1. **Formatif (Tugas/Harian)**: `bobot` = 40%
  2. **Sumatif Tengah Semester (UTS)**: `bobot` = 30%
  3. **Sumatif Akhir Semester (UAS)**: `bobot` = 30%
- Test factories and unit test sample payloads across `KomponenPenilaianSeederTest`, `AsesmenSeederTest`, and tenant scoping tests will be updated to include valid `bobot` attributes.

---

## 3. Business Logic: Strict 100% Guard

### 3.1 Total Weight Validation Engine
In both `App\Http\Controllers\Admin\KomponenPenilaianController` and `App\Http\Controllers\Guru\KomponenPenilaianController`, all `store` and `update` endpoints must run a defensive summation query before saving:

```php
$existingSum = KomponenPenilaian::where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
    ->where('semester_id', $validated['semester_id'])
    ->when(isset($komponen), fn($q) => $q->where('id', '!=', $komponen->id))
    ->sum('bobot');

if (($existingSum + (int)$validated['bobot']) > 100) {
    $remaining = max(0, 100 - $existingSum);
    return response()->json([
        'status' => 'error',
        'message' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk mata pelajaran ini adalah {$remaining}%."
    ], 422);
}
```

---

## 4. Weighted Rapor Calculation Engine

### 4.1 RaporController Mathematical Formulation
In `RaporController::hitungRekap()`, student grades per component are averaged first, multiplied by the component's assigned weight, and normalized against total active weights:

$$\text{Nilai Akhir Mapel} = \frac{\sum_{i=1}^{n} (\bar{X}_{\text{asesmen } i} \times \text{Bobot}_i)}{\sum_{i=1}^{n} \text{Bobot}_i}$$

Where $\bar{X}_{\text{asesmen } i}$ is the arithmetic average of a student's scores (`nilai_angka`) in assessments tied to component $i$.

If a teacher has only entered assessments for components totaling 70% so far, dividing by $\sum \text{Bobot}_i = 70$ guarantees that provisional report cards still display mathematically accurate interim grades without distortion.

---

## 5. UI/UX Transformation: SPA Modals & Live Weight Bar

### 5.1 Interactive Live Weight Widget
At the top of the component table (in both Admin and Guru portals), a dynamic banner displays the cumulative weight allocation for the filtered subject and semester:
- **State 1: Complete (100%)**: Emerald green glowing card — *"Alokasi Bobot Sempurna: 100% (Siap dievaluasi ke Rapor)"*.
- **State 2: Incomplete (< 100%)**: Amber warning card — *"Alokasi Bobot: 70% / 100% (Tersisa kuota 30% untuk dialokasikan)"*.
- **State 3: Zero / Empty (0%)**: Slate neutral guidance banner prompting instant component creation.

### 5.2 SPA Modals with TomSelect Pro-Max
- **Zero Page Reloads**: All operations (Create, Edit, Delete, Duplicate) execute via asynchronous JSON Fetch requests.
- **TomSelect Dropdowns**: Searchable single-select dropdowns for selecting Mata Pelajaran and Semester inside the modal dialogs, synchronized via Alpine `$nextTick()` reactivity.

---

## 6. Verification & TDD Strategy

### 6.1 Automated Verification Plan
1. **Migration & Seeder Validation**:
   - Run `php artisan migrate:fresh --seed` to confirm database re-initialization passes cleanly with the new `bobot` column and realistic weighted seeders.
   - Run `php artisan test --filter="KomponenPenilaianSeederTest"` to verify seeding assumptions.
2. **Controller Guard & CRUD Tests**:
   - Update and execute `KomponenPenilaianCrudTest` (Admin) and `KomponenPenilaianControllerTest` (Guru).
   - Add explicit unit tests verifying that requests attempting to push cumulative weight over 100% are rejected with appropriate HTTP 422 errors.
3. **Rapor Evaluation Tests**:
   - Run `RaporControllerTest` and add assertions verifying that grades reflect weighted calculation rather than unweighted averages.
4. **Vite Bundle Build**:
   - Run `npm run build` to verify front-end JavaScript controllers and styles compile cleanly.
