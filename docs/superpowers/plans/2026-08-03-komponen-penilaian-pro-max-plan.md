# Komponen Penilaian Pro-Max & Bobot Ecosystem Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrate a mandatory `bobot` percentage dimension into the assessment ecosystem, enforce a strict 100% maximum allocation guard across Admin and Guru controllers, refactor report card (`RaporController`) evaluation from unweighted to weighted averages, and transform all UI views into reactive SPA Modals equipped with TomSelect and a Live Weight Progress Bar.

**Architecture:** We adapt the base schema directly without cluttering add-on migrations, realign seeders to produce realistic 40-30-30 weight distribution, add defensive summation logic in controllers to return descriptive HTTP 422 JSON errors when weights exceed 100%, adjust report card arithmetic to weight component scores proportionally, and decouple form interactions from page reloads using Alpine.js SPA modals.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL, Alpine.js, TomSelect, Vite/Tailwind CSS, PHPUnit/Pest.

## Global Constraints
- **Zero Schema Bloat**: Edit `2026_07_25_130000_create_komponen_penilaian_table.php` directly instead of adding new migrations.
- **Tenant & Entity Scoping**: All queries and validations must maintain tenant isolation (`lembaga_id`) and correct subject/semester boundaries.
- **Strict 100% Guard**: Total `bobot` per subject and semester cannot exceed 100%.
- **Zero Placeholders & Full Verification**: Run automated tests after every step and rebuild Vite bundle before completion.

---

### Task 1: Database Schema, Model, & Seeder Realignment

**Files:**
- Modify: `database/migrations/2026_07_25_130000_create_komponen_penilaian_table.php:11-23`
- Modify: `app/Models/KomponenPenilaian.php:16-20`
- Modify: `database/seeders/KomponenPenilaianSeeder.php`
- Modify: `tests/Unit/KomponenPenilaianSeederTest.php`
- Modify: `tests/Unit/AsesmenSeederTest.php`
- Modify: `tests/Unit/NilaiSiswaSeederTest.php`

**Interfaces:**
- Consumes: Existing database constraints and model relationships.
- Produces: `bobot` percentage column (unsignedTinyInteger 1-100) available on `KomponenPenilaian` Eloquent models and database tables.

- [ ] **Step 1: Write failing assertions in seeder unit test**

```php
// In tests/Unit/KomponenPenilaianSeederTest.php, add assertion checking that seeded components have valid bobot:
$this->assertDatabaseHas('komponen_penilaian', [
    'kode' => 'F-1',
    'bobot' => 40,
]);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="KomponenPenilaianSeederTest"`
Expected: FAIL due to missing `bobot` column or unmatched database records.

- [ ] **Step 3: Update schema, model, and seeders**

In `database/migrations/2026_07_25_130000_create_komponen_penilaian_table.php`:
```php
Schema::create('komponen_penilaian', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
    $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
    $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
    $table->string('kode')->nullable();
    $table->text('deskripsi');
    $table->unsignedTinyInteger('bobot')->default(10);
    $table->text('kktp')->nullable();
    $table->timestamps();

    $table->index(['lembaga_id', 'mata_pelajaran_id', 'semester_id'], 'idx_komp_lmbg_mapel_smt');
});
```

In `app/Models/KomponenPenilaian.php`:
```php
protected $fillable = ['mata_pelajaran_id', 'semester_id', 'lembaga_id', 'kode', 'deskripsi', 'bobot', 'kktp'];
```

In `database/seeders/KomponenPenilaianSeeder.php`, set weights for seeded items:
```php
$komposisi = [
    ['kode' => 'F-1', 'deskripsi' => 'Formatif Tugas / Harian', 'bobot' => 40, 'kktp' => '70'],
    ['kode' => 'STS', 'deskripsi' => 'Sumatif Tengah Semester (UTS)', 'bobot' => 30, 'kktp' => '70'],
    ['kode' => 'SAS', 'deskripsi' => 'Sumatif Akhir Semester (UAS)', 'bobot' => 30, 'kktp' => '70'],
];
```

- [ ] **Step 4: Execute migration refresh and run tests to verify pass**

Run: `php artisan migrate:fresh --seed` and `php artisan test --filter="SeederTest"`
Expected: Clean migration rebuild and PASS for all seeder tests.

- [ ] **Step 5: Commit changes**

```powershell
git add database/ app/Models/ tests/Unit/ ; git commit -m "feat(komponen): incorporate bobot percentage dimension into schema, model, and seeders"
```

---

### Task 2: Admin & Guru Controller Strict 100% Guard & AJAX Support

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Modify: `app/Http/Controllers/Guru/KomponenPenilaianController.php`
- Modify: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`
- Modify: `tests/Feature/Guru/KomponenPenilaianControllerTest.php`

**Interfaces:**
- Consumes: `bobot` column from Task 1.
- Produces: Backend validation logic ensuring cumulative weight $\le 100$ per subject & semester, returning descriptive 422 JSON errors when exceeded or during SPA modal requests.

- [ ] **Step 1: Write failing feature test for 100% Weight Guard**

In `tests/Feature/Admin/KomponenPenilaianCrudTest.php`:
```php
it('rejects storing a new assessment component when total bobot exceeds 100 percent', function () {
    // create initial components totaling 80%
    KomponenPenilaian::create([
        'lembaga_id' => $this->lembaga->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'semester_id' => $this->semester->id,
        'kode' => 'K-1',
        'deskripsi' => 'Existing',
        'bobot' => 80,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('admin.komponen-penilaian.store'), [
        'mata_pelajaran_id' => $this->mapel->id,
        'semester_id' => $this->semester->id,
        'kode' => 'K-2',
        'deskripsi' => 'Overload',
        'bobot' => 30, // 80 + 30 = 110%
    ]);

    $response->assertStatus(422)->assertJson(['status' => 'error']);
    $this->assertDatabaseMissing('komponen_penilaian', ['kode' => 'K-2']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="KomponenPenilaianCrudTest"`
Expected: FAIL (controller currently allows unrestricted weight saving).

- [ ] **Step 3: Implement validation logic in Admin and Guru Controllers**

In both controllers' `store` and `update` methods:
```php
$data = $request->validate([
    'mata_pelajaran_id' => ['required', 'integer'],
    'semester_id' => ['required', 'integer'],
    'kode' => ['nullable', 'string', 'max:50'],
    'deskripsi' => ['required', 'string'],
    'bobot' => ['required', 'integer', 'min:1', 'max:100'],
    'kktp' => ['nullable', 'string'],
]);

$existingSum = KomponenPenilaian::where('mata_pelajaran_id', $data['mata_pelajaran_id'])
    ->where('semester_id', $data['semester_id'])
    ->when(isset($komponenPenilaian), fn($q) => $q->where('id', '!=', $komponenPenilaian->id))
    ->sum('bobot');

if (($existingSum + (int)$data['bobot']) > 100) {
    $remaining = max(0, 100 - $existingSum);
    $msg = "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk mata pelajaran ini adalah {$remaining}%.";
    if ($request->ajax() || $request->wantsJson()) {
        return response()->json(['status' => 'error', 'message' => $msg], 422);
    }
    return back()->withInput()->withErrors(['bobot' => $msg]);
}

if ($request->ajax() || $request->wantsJson()) {
    // return JSON success payload after creating/updating model
    return response()->json(['status' => 'success', 'message' => 'Komponen penilaian berhasil disimpan.']);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="KomponenPenilaianCrudTest"` and `php artisan test --filter="KomponenPenilaianControllerTest"`
Expected: PASS for all component CRUD and validation guard tests.

- [ ] **Step 5: Commit changes**

```powershell
git add app/Http/Controllers/ tests/Feature/ ; git commit -m "feat(komponen): implement strict 100 percent weight guard in Admin and Guru controllers"
```

---

### Task 3: Weighted Report Card Evaluation Engine (`RaporController`)

**Files:**
- Modify: `app/Http/Controllers/Admin/RaporController.php:114-160`
- Modify: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Consumes: `bobot` attributes from `KomponenPenilaian` records.
- Produces: Proportionally weighted subject grade averages in report card compilations (`hitungRekap`).

- [ ] **Step 1: Write failing test verifying weighted Rapor score computation**

In `tests/Feature/Admin/RaporControllerTest.php`, assert that a subject with 40% weight component (score 100) and 60% weight component (score 50) evaluates to weighted average 70 (not unweighted arithmetic mean 75):
```php
it('computes subject grades using weighted component percentage formulas', function () {
    // create subject, 2 components (bobot 40 and 60), student assessments, and scores 100 and 50.
    // verify hitungRekap returns 70.0 for that student's subject score.
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="RaporControllerTest"`
Expected: FAIL due to existing naive unweighted average (`$scores->avg()`).

- [ ] **Step 3: Refactor `hitungRekap()` in `RaporController.php`**

Replace unweighted score averaging in `RaporController::hitungRekap()`:
```php
$rekapNilai[$siswa->id][$mapel->id] = null;
$komponenList = KomponenPenilaian::where('mata_pelajaran_id', $mapel->id)
    ->where('semester_id', $semester->id)
    ->get();

if ($komponenList->isNotEmpty()) {
    $weightedSum = 0;
    $activeBobot = 0;
    foreach ($komponenList as $komp) {
        $kompAsesmenIds = $asesmenList->where('mata_pelajaran_id', $mapel->id)
            ->filter(fn($a) => $a->komponenPenilaian->contains('id', $komp->id))
            ->pluck('id');
        
        $scores = $allNilai->whereIn('asesmen_id', $kompAsesmenIds)
            ->where('siswa_id', $siswa->id)
            ->whereNotNull('nilai_angka')
            ->pluck('nilai_angka');
            
        if ($scores->count() > 0) {
            $avg = $scores->avg();
            $weightedSum += ($avg * $komp->bobot);
            $activeBobot += $komp->bobot;
        }
    }
    if ($activeBobot > 0) {
        $rekapNilai[$siswa->id][$mapel->id] = round($weightedSum / $activeBobot, 1);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="RaporControllerTest"`
Expected: PASS with exact weighted evaluations.

- [ ] **Step 5: Commit changes**

```powershell
git add app/Http/Controllers/Admin/RaporController.php tests/Feature/Admin/RaporControllerTest.php ; git commit -m "feat(rapor): transform score calculation engine to proportionally weighted averages using bobot"
```

---

### Task 4: Admin & Guru UI Refactor - SPA Modals, TomSelect Pro-Max & Live Weight Progress Bar

**Files:**
- Create: `resources/js/komponen-penilaian-filter.js`
- Create: `resources/views/admin/komponen-penilaian/_modal-form.blade.php`
- Create: `resources/views/guru/komponen-penilaian/_modal-form.blade.php`
- Modify: `resources/views/admin/komponen-penilaian/index.blade.php`
- Modify: `resources/views/admin/komponen-penilaian/_daftar.blade.php`
- Modify: `resources/views/guru/komponen-penilaian/index.blade.php`
- Modify: `resources/views/guru/komponen-penilaian/_daftar.blade.php`

**Interfaces:**
- Consumes: JSON AJAX endpoints and validation messages from Task 2.
- Produces: Modern SPA interface with TomSelect searchability, zero page reloads, and real-time total weight indicator cards.

- [ ] **Step 1: Build reactive JS module (`resources/js/komponen-penilaian-filter.js`)**

Implement Alpine controller managing `showModalForm`, TomSelect instances for `mata_pelajaran_id` and `semester_id`, AJAX form submissions with error toast display, and `totalBobot` calculation helper that reads active items in the table to render the Live Weight Progress Bar status (Emerald when 100%, Amber when < 100%, Red when > 100%).

- [ ] **Step 2: Create modal partials and update index and table views in Admin & Guru**

In `_daftar.blade.php` (for both Admin and Guru), prepend the **Live Weight Progress Bar Widget**:
```html
@php
    $currentTotalBobot = $komponenList->sum('bobot');
@endphp
<div class="mb-4 p-4 rounded-xl border {{ $currentTotalBobot === 100 ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
    <div class="flex items-center justify-between">
        <span class="font-semibold">Total Alokasi Bobot: {{ $currentTotalBobot }}% / 100%</span>
        <span class="text-xs font-mono px-2 py-0.5 rounded {{ $currentTotalBobot === 100 ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' }}">{{ $currentTotalBobot === 100 ? 'SEMPURNA' : 'BELUM LENGKAP' }}</span>
    </div>
    <div class="w-full bg-gray-200 rounded-full h-2 mt-2 overflow-hidden">
        <div class="h-2 rounded-full {{ $currentTotalBobot === 100 ? 'bg-emerald-600' : 'bg-amber-500' }}" style="width: {{ min(100, $currentTotalBobot) }}%"></div>
    </div>
</div>
```

Replace static navigation buttons with `@click="openCreateModal()"` and `@click="openEditModal(row)"`.

- [ ] **Step 3: Compile frontend asset bundle**

Run: `cmd.exe /c "npm run build"`
Expected: Zero build errors; `app.js` and `app.css` generated cleanly.

- [ ] **Step 4: Execute entire test suite for verification**

Run: `php artisan test --filter="KomponenPenilaian"` and `php artisan test --filter="Rapor"`
Expected: 100% test pass rate across all Admin and Guru component capabilities.

- [ ] **Step 5: Commit changes and clean up**

```powershell
git add resources/ app/ tests/ ; git commit -m "feat(ui): upgrade komponen penilaian to reactive SPA modals with TomSelect and live weight bar"
```
