# Portal Kelembagaan 5-Tab & Relational Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the static single-form institution edit view into an interactive 5-tab portal with view-to-edit toggle, URL hash persistence ("stay on current tab"), and complete CRUD management for 4 relational entities (Data Periodik, Ekstrakurikuler, Layanan Khusus, Program Inklusi).

**Architecture:** Implement 4 specialized relational sub-controllers under `App\Http\Controllers\Admin\Lembaga\` with explicit URL-hash redirects (`#data-periodik`, `#ekstrakurikuler`, `#layanan-khusus`, `#program-inklusi`). Upgrade `edit.blade.php` to a dynamic Alpine.js 5-tab interface that initializes state directly from `window.location.hash`, syncs history via `replaceState` without reloading, and implements clean view mode cards and instant edit mode modals.

**Tech Stack:** Laravel 11 (PHP 8.3), Eloquent ORM, Blade Templates, Tailwind CSS / TailAdmin, Alpine.js, Pest / PHPUnit Feature Tests.

## Global Constraints

- Never use raw browser alert/confirm; always use standard Blade icons (`<x-icon>`) and Alpine.js interactive modals/dialogs (`confirmDialog`).
- Adhere strictly to zero N+1 query regression by eager loading all 4 relational tables in `LembagaController@edit`.
- All CRUD actions on relational tabs must return to `admin.lembaga.edit` with the specific tab hash preserved in the redirect URL so the user stays on the exact tab they just interacted with.
- Preserve all existing documentation comments and docstrings in modified code unless explicitly replaced by feature logic.

---

### Task 1: Backend Controllers & Routing for Lembaga Relational Data (TDD)

**Files:**
- Create: `tests/Feature/Admin/LembagaRelationalManagementTest.php`
- Create: `app/Http/Controllers/Admin/Lembaga/DataPeriodikController.php`
- Create: `app/Http/Controllers/Admin/Lembaga/EkstrakurikulerController.php`
- Create: `app/Http/Controllers/Admin/Lembaga/LayananKhususController.php`
- Create: `app/Http/Controllers/Admin/Lembaga/ProgramInklusiController.php`
- Modify: `routes/admin.php:45-50`
- Modify: `app/Http/Controllers/Admin/LembagaController.php:65-72`

**Interfaces:**
- Consumes: `Lembaga`, `LembagaDataPeriodik`, `EkstrakurikulerLembaga`, `LayananKhususLembaga`, `ProgramInklusiLembaga`, `Semester`.
- Produces: RESTful endpoints under `/admin/lembaga/{lembaga}/...` for managing relational institutional data with URL hash redirects.

- [ ] **Step 1: Write the failing feature test for relational institutional endpoints**

```php
<?php
// tests/Feature/Admin/LembagaRelationalManagementTest.php

use App\Models\EkstrakurikulerLembaga;
use App\Models\LayananKhususLembaga;
use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use App\Models\ProgramInklusiLembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->role = Role::firstOrCreate(['name' => 'yayasan_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $this->role->givePermissionTo(['lembaga.view', 'lembaga.edit']);
    
    $this->yayasan = Yayasan::factory()->create();
    $this->lembaga = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    
    $this->manager = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
    $this->manager->assignRole($this->role);
});

it('can store, update, and delete ekstrakurikuler with hash redirect', function () {
    $storeResponse = $this->actingAs($this->manager)->post(route('admin.lembaga.ekstrakurikuler.store', $this->lembaga), [
        'jenis_ekskul' => 'olahraga',
        'nama_ekskul' => 'Futsal',
        'no_sk' => 'SK/EKS/001',
        'jam_per_minggu' => 2,
    ]);

    $storeResponse->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#ekstrakurikuler');
    expect(EkstrakurikulerLembaga::where('lembaga_id', $this->lembaga->id)->where('nama_ekskul', 'Futsal')->exists())->toBeTrue();

    $ekskul = EkstrakurikulerLembaga::where('nama_ekskul', 'Futsal')->first();
    
    $updateResponse = $this->actingAs($this->manager)->put(route('admin.lembaga.ekstrakurikuler.update', [$this->lembaga, $ekskul]), [
        'jenis_ekskul' => 'olahraga',
        'nama_ekskul' => 'Futsal Junior',
        'jam_per_minggu' => 4,
    ]);
    
    $updateResponse->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#ekstrakurikuler');
    expect($ekskul->refresh()->nama_ekskul)->toBe('Futsal Junior');

    $deleteResponse = $this->actingAs($this->manager)->delete(route('admin.lembaga.ekstrakurikuler.destroy', [$this->lembaga, $ekskul]));
    $deleteResponse->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#ekstrakurikuler');
    expect(EkstrakurikulerLembaga::find($ekskul->id))->toBeNull();
});

it('can manage layanan khusus with hash redirect', function () {
    $this->actingAs($this->manager)->post(route('admin.lembaga.layanan-khusus.store', $this->lembaga), [
        'jenis_layanan' => 'Bimbingan Konseling',
        'no_sk' => 'SK/BK/2026',
        'keterangan' => 'Layanan konseling aktif setiap hari kerja',
    ])->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#layanan-khusus');

    expect(LayananKhususLembaga::where('jenis_layanan', 'Bimbingan Konseling')->exists())->toBeTrue();
});

it('can manage program inklusi with hash redirect', function () {
    $this->actingAs($this->manager)->post(route('admin.lembaga.program-inklusi.store', $this->lembaga), [
        'kebutuhan_khusus' => 'Tuna Netra',
        'no_sk' => 'SK/INK/2026',
        'keterangan' => 'Didukung guru pendamping khusus',
    ])->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#program-inklusi');

    expect(ProgramInklusiLembaga::where('kebutuhan_khusus', 'Tuna Netra')->exists())->toBeTrue();
});

it('can manage data periodik with hash redirect', function () {
    $semester = Semester::factory()->create();
    $this->actingAs($this->manager)->post(route('admin.lembaga.data-periodik.store', $this->lembaga), [
        'semester_id' => $semester->id,
        'waktu_penyelenggaraan' => 'pagi',
        'sumber_listrik' => 'PLN',
        'daya_listrik' => 5500,
        'akses_internet' => 'Indihome 100Mbps',
        'status_bos' => 1,
    ])->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#data-periodik');

    expect(LembagaDataPeriodik::where('lembaga_id', $this->lembaga->id)->where('semester_id', $semester->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/LembagaRelationalManagementTest.php`
Expected: FAIL with route not defined / NotFoundException.

- [ ] **Step 3: Create the 4 sub-controllers and update routes and LembagaController@edit**

Create `app/Http/Controllers/Admin/Lembaga/EkstrakurikulerController.php`:
```php
<?php

namespace App\Http\Controllers\Admin\Lembaga;

use App\Http\Controllers\Controller;
use App\Models\EkstrakurikulerLembaga;
use App\Models\Lembaga;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EkstrakurikulerController extends Controller
{
    public function store(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $validated = $request->validate([
            'jenis_ekskul' => 'required|string|max:255',
            'nama_ekskul' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tanggal_sk' => 'nullable|date',
            'jam_per_minggu' => 'nullable|integer|min:1|max:50',
        ]);

        $lembaga->ekstrakurikuler()->create($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#ekstrakurikuler')
            ->with('status', 'Ekstrakurikuler berhasil ditambahkan.');
    }

    public function update(Request $request, Lembaga $lembaga, EkstrakurikulerLembaga $ekstrakurikuler): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($ekstrakurikuler->lembaga_id === $lembaga->id, 404);

        $validated = $request->validate([
            'jenis_ekskul' => 'required|string|max:255',
            'nama_ekskul' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tanggal_sk' => 'nullable|date',
            'jam_per_minggu' => 'nullable|integer|min:1|max:50',
        ]);

        $ekstrakurikuler->update($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#ekstrakurikuler')
            ->with('status', 'Ekstrakurikuler berhasil diperbarui.');
    }

    public function destroy(Request $request, Lembaga $lembaga, EkstrakurikulerLembaga $ekstrakurikuler): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($ekstrakurikuler->lembaga_id === $lembaga->id, 404);

        $ekstrakurikuler->delete();

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#ekstrakurikuler')
            ->with('status', 'Ekstrakurikuler berhasil dihapus.');
    }
}
```

Create `app/Http/Controllers/Admin/Lembaga/LayananKhususController.php`:
```php
<?php

namespace App\Http\Controllers\Admin\Lembaga;

use App\Http\Controllers\Controller;
use App\Models\LayananKhususLembaga;
use App\Models\Lembaga;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LayananKhususController extends Controller
{
    public function store(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $validated = $request->validate([
            'jenis_layanan' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tmt' => 'nullable|date',
            'tst' => 'nullable|date|after_or_equal:tmt',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $lembaga->layananKhusus()->create($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#layanan-khusus')
            ->with('status', 'Layanan khusus berhasil ditambahkan.');
    }

    public function update(Request $request, Lembaga $lembaga, LayananKhususLembaga $layananKhusus): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($layananKhusus->lembaga_id === $lembaga->id, 404);

        $validated = $request->validate([
            'jenis_layanan' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tmt' => 'nullable|date',
            'tst' => 'nullable|date|after_or_equal:tmt',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $layananKhusus->update($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#layanan-khusus')
            ->with('status', 'Layanan khusus berhasil diperbarui.');
    }

    public function destroy(Request $request, Lembaga $lembaga, LayananKhususLembaga $layananKhusus): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($layananKhusus->lembaga_id === $lembaga->id, 404);

        $layananKhusus->delete();

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#layanan-khusus')
            ->with('status', 'Layanan khusus berhasil dihapus.');
    }
}
```

Create `app/Http/Controllers/Admin/Lembaga/ProgramInklusiController.php`:
```php
<?php

namespace App\Http\Controllers\Admin\Lembaga;

use App\Http\Controllers\Controller;
use App\Models\Lembaga;
use App\Models\ProgramInklusiLembaga;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProgramInklusiController extends Controller
{
    public function store(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $validated = $request->validate([
            'kebutuhan_khusus' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tanggal_sk' => 'nullable|date',
            'tmt' => 'nullable|date',
            'tst' => 'nullable|date|after_or_equal:tmt',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $lembaga->programInklusi()->create($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#program-inklusi')
            ->with('status', 'Program inklusi berhasil ditambahkan.');
    }

    public function update(Request $request, Lembaga $lembaga, ProgramInklusiLembaga $programInklusi): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($programInklusi->lembaga_id === $lembaga->id, 404);

        $validated = $request->validate([
            'kebutuhan_khusus' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tanggal_sk' => 'nullable|date',
            'tmt' => 'nullable|date',
            'tst' => 'nullable|date|after_or_equal:tmt',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $programInklusi->update($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#program-inklusi')
            ->with('status', 'Program inklusi berhasil diperbarui.');
    }

    public function destroy(Request $request, Lembaga $lembaga, ProgramInklusiLembaga $programInklusi): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($programInklusi->lembaga_id === $lembaga->id, 404);

        $programInklusi->delete();

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#program-inklusi')
            ->with('status', 'Program inklusi berhasil dihapus.');
    }
}
```

Create `app/Http/Controllers/Admin/Lembaga/DataPeriodikController.php`:
```php
<?php

namespace App\Http\Controllers\Admin\Lembaga;

use App\Http\Controllers\Controller;
use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DataPeriodikController extends Controller
{
    public function store(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'waktu_penyelenggaraan' => 'nullable|string|max:100',
            'sumber_listrik' => 'nullable|string|max:100',
            'daya_listrik' => 'nullable|integer',
            'akses_internet' => 'nullable|string|max:100',
            'status_bos' => 'nullable|boolean',
            'sertifikasi_iso' => 'nullable|string|max:100',
            'ketersediaan_air_bersih' => 'nullable|boolean',
            'kecukupan_air_bersih' => 'nullable|boolean',
            'jumlah_tempat_cuci_tangan' => 'nullable|integer',
            'jumlah_jamban' => 'nullable|integer',
            'stratifikasi_uks' => 'nullable|string|max:100',
            'media_kie_sanitasi' => 'nullable|boolean',
        ]);

        $validated['status_bos'] = $request->boolean('status_bos');
        $validated['ketersediaan_air_bersih'] = $request->boolean('ketersediaan_air_bersih');
        $validated['kecukupan_air_bersih'] = $request->boolean('kecukupan_air_bersih');
        $validated['media_kie_sanitasi'] = $request->boolean('media_kie_sanitasi');

        $lembaga->dataPeriodik()->updateOrCreate(
            ['semester_id' => $validated['semester_id']],
            $validated
        );

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#data-periodik')
            ->with('status', 'Data periodik berhasil disimpan.');
    }

    public function update(Request $request, Lembaga $lembaga, LembagaDataPeriodik $dataPeriodik): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($dataPeriodik->lembaga_id === $lembaga->id, 404);

        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'waktu_penyelenggaraan' => 'nullable|string|max:100',
            'sumber_listrik' => 'nullable|string|max:100',
            'daya_listrik' => 'nullable|integer',
            'akses_internet' => 'nullable|string|max:100',
            'status_bos' => 'nullable|boolean',
            'sertifikasi_iso' => 'nullable|string|max:100',
            'ketersediaan_air_bersih' => 'nullable|boolean',
            'kecukupan_air_bersih' => 'nullable|boolean',
            'jumlah_tempat_cuci_tangan' => 'nullable|integer',
            'jumlah_jamban' => 'nullable|integer',
            'stratifikasi_uks' => 'nullable|string|max:100',
            'media_kie_sanitasi' => 'nullable|boolean',
        ]);

        $validated['status_bos'] = $request->boolean('status_bos');
        $validated['ketersediaan_air_bersih'] = $request->boolean('ketersediaan_air_bersih');
        $validated['kecukupan_air_bersih'] = $request->boolean('kecukupan_air_bersih');
        $validated['media_kie_sanitasi'] = $request->boolean('media_kie_sanitasi');

        $dataPeriodik->update($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#data-periodik')
            ->with('status', 'Data periodik berhasil diperbarui.');
    }

    public function destroy(Request $request, Lembaga $lembaga, LembagaDataPeriodik $dataPeriodik): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($dataPeriodik->lembaga_id === $lembaga->id, 404);

        $dataPeriodik->delete();

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#data-periodik')
            ->with('status', 'Data periodik berhasil dihapus.');
    }
}
```

In `routes/admin.php`, import the 4 controllers and register under `admin/lembaga/{lembaga}`:
```php
use App\Http\Controllers\Admin\Lembaga\DataPeriodikController as LembagaDataPeriodikController;
use App\Http\Controllers\Admin\Lembaga\EkstrakurikulerController as LembagaEkstrakurikulerController;
use App\Http\Controllers\Admin\Lembaga\LayananKhususController as LembagaLayananKhususController;
use App\Http\Controllers\Admin\Lembaga\ProgramInklusiController as LembagaProgramInklusiController;
// ... inside route group:
    Route::resource('lembaga', LembagaController::class)->except(['show', 'destroy']);
    Route::prefix('lembaga/{lembaga}')->name('lembaga.')->group(function () {
        Route::post('data-periodik', [LembagaDataPeriodikController::class, 'store'])->name('data-periodik.store');
        Route::put('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'update'])->name('data-periodik.update');
        Route::delete('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'destroy'])->name('data-periodik.destroy');

        Route::post('ekstrakurikuler', [LembagaEkstrakurikulerController::class, 'store'])->name('ekstrakurikuler.store');
        Route::put('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'update'])->name('ekstrakurikuler.update');
        Route::delete('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'destroy'])->name('ekstrakurikuler.destroy');

        Route::post('layanan-khusus', [LembagaLayananKhususController::class, 'store'])->name('layanan-khusus.store');
        Route::put('layanan-khusus/{layananKhusus}', [LembagaLayananKhususController::class, 'update'])->name('layanan-khusus.update');
        Route::delete('layanan-khusus/{layananKhusus}', [LembagaLayananKhususController::class, 'destroy'])->name('layanan-khusus.destroy');

        Route::post('program-inklusi', [LembagaProgramInklusiController::class, 'store'])->name('program-inklusi.store');
        Route::put('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'update'])->name('program-inklusi.update');
        Route::delete('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'destroy'])->name('program-inklusi.destroy');
    });
```

In `LembagaController::edit`, eager load relationships and pass `$semesters`:
```php
    public function edit(Request $request, Lembaga $lembaga): View
    {
        $this->authorize('lembaga.edit');
        $this->authorizeOwnLembaga($request, $lembaga);

        $lembaga->load(['dataPeriodik.semester', 'ekstrakurikuler', 'layananKhusus', 'programInklusi', 'yayasan']);
        $semesters = \App\Models\Semester::orderByDesc('tahun_ajaran_id')->orderBy('semester')->get();

        return view('admin.lembaga.edit', [
            'lembaga' => $lembaga,
            'semesters' => $semesters,
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/LembagaRelationalManagementTest.php`
Expected: PASS (4 tests passed).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Admin/LembagaRelationalManagementTest.php app/Http/Controllers/Admin/Lembaga/ routes/admin.php app/Http/Controllers/Admin/LembagaController.php
git commit -m "feat(lembaga): implement relational management endpoints with tab hash persistence"
```

---

### Task 2: Interactive 5-Tab UI & View-to-Edit Toggle in Lembaga Edit Portal (Blade & Alpine.js)

**Files:**
- Modify: `resources/views/admin/lembaga/edit.blade.php:1-40` (and replace full view with modular tabs)
- Modify/Create partials if cleanly separated, or organize directly inside `edit.blade.php` keeping `_form.blade.php` intact for `create.blade.php` and Tab 1 edit mode.

**Interfaces:**
- Consumes: `$lembaga` (with eager loaded relations) and `$semesters`.
- Produces: 5-tab UI with URL hash persistence, view mode summary cards, and interactive modals/confirm dialogs for CRUD actions.

- [ ] **Step 1: Update `edit.blade.php` with Alpine.js URL hash persistence and Mode Toggle**

Implement Alpine initialization:
```blade
<div x-data="{
    mode: '{{ session('mode') ?: (old() || request()->has('mode') || (request()->header('referer') && str_contains(request()->header('referer'), '#') && !str_contains(request()->header('referer'), '#profil')) ? 'edit' : 'view') }}',
    activeTab: window.location.hash ? window.location.hash.substring(1) : 'profil',
    showAddEkskulModal: false,
    editEkskulModal: false,
    ekskulData: {},
    showAddLayananModal: false,
    editLayananModal: false,
    layananData: {},
    showAddInklusiModal: false,
    editInklusiModal: false,
    inklusiData: {},
    showAddPeriodikModal: false,
    editPeriodikModal: false,
    periodikData: {},
    init() {
        if (window.location.hash && window.location.hash !== '#profil') {
            this.mode = 'edit';
        }
        window.addEventListener('hashchange', () => {
            this.activeTab = window.location.hash ? window.location.hash.substring(1) : 'profil';
        });
        this.$watch('activeTab', value => {
            window.history.replaceState(null, '', '#' + value);
        });
    }
}">
```

- [ ] **Step 2: Build the Header Banner and 5-Tab Navigation Bar**

```blade
<div class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xs dark:border-gray-800 dark:bg-gray-900">
    <div class="border-b border-gray-200 p-6 dark:border-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-2xl font-bold text-brand-600 dark:bg-brand-900/40 dark:text-brand-400">
                    🏢
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ $lembaga->nama }}</h1>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <span>NPSN: {{ $lembaga->npsn }}</span>
                        <span>•</span>
                        <x-badge variant="info">Akreditasi: {{ strtoupper($lembaga->akreditasi ?? '-') }}</x-badge>
                        <span>•</span>
                        <x-badge variant="success">{{ ucwords($lembaga->status_sekolah ?? 'Swasta') }}</x-badge>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" @click="mode = (mode === 'view' ? 'edit' : 'view')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50 active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <x-icon x-show="mode === 'view'" name="edit" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
                    <x-icon x-show="mode === 'edit'" name="visibility" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
                    <span x-text="mode === 'view' ? 'Mode Edit' : 'Mode Lihat'"></span>
                </button>
            </div>
        </div>
    </div>
    
    {{-- 5-Tab Navigation Pills --}}
    <div class="flex flex-wrap border-b border-gray-200 bg-gray-50 px-6 dark:border-gray-800 dark:bg-gray-950/40">
        @foreach ([
            'profil' => ['label' => 'Profil & Identitas', 'icon' => 'buildings', 'count' => null],
            'data-periodik' => ['label' => 'Data Periodik', 'icon' => 'clipboard', 'count' => $lembaga->dataPeriodik->count()],
            'ekstrakurikuler' => ['label' => 'Ekstrakurikuler', 'icon' => 'sparkles', 'count' => $lembaga->ekstrakurikuler->count()],
            'layanan-khusus' => ['label' => 'Layanan Khusus', 'icon' => 'heart', 'count' => $lembaga->layananKhusus->count()],
            'program-inklusi' => ['label' => 'Program Inklusi', 'icon' => 'shield', 'count' => $lembaga->programInklusi->count()],
        ] as $key => $tab)
            <button
                type="button"
                @click="activeTab = '{{ $key }}'"
                :class="activeTab === '{{ $key }}' ? 'border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 font-medium'"
                class="inline-flex items-center gap-2 border-b-2 py-4 px-4 text-sm transition focus:outline-none"
            >
                <x-icon name="{{ $tab['icon'] }}" class="h-4 w-4" />
                <span>{{ $tab['label'] }}</span>
                @if (!is_null($tab['count']))
                    <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $tab['count'] }}</span>
                @endif
            </button>
        @endforeach
    </div>
</div>
```

- [ ] **Step 3: Implement Tab 1 (Profil), Tab 2 (Data Periodik), Tab 3 (Ekstrakurikuler), Tab 4 (Layanan Khusus), Tab 5 (Program Inklusi) with Modals**

In `edit.blade.php`, render each tab inside `x-show="activeTab === '...'"` containers.
- Tab 1 in `mode === 'edit'` renders `@include('admin.lembaga._form')` within the update form.
- Tab 2-5 render elegant tables/cards in view mode, and add "+ Tambah" button + action icons in edit mode, wired to clean Alpine modals and `confirmDialog` deletion forms.

- [ ] **Step 4: Verify test suite still passes after UI implementation**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/LembagaRelationalManagementTest.php tests/Feature/Admin/LembagaCrudTest.php`
Expected: PASS across all tests.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/lembaga/
git commit -m "feat(lembaga): upgrade edit page into 5-tab portal with view-to-edit toggle and hash persistence"
```

---

### Task 3: Full Suite Verification & Walkthrough Documentation

**Files:**
- Modify: `walkthrough.md`

- [ ] **Step 1: Run complete automated test suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: PASS across all 1000+ tests in repository without regressions.

- [ ] **Step 2: Update walkthrough documentation**

Document the new 5-Tab Institutional Portal, URL hash persistence, and relational endpoints in `walkthrough.md`.

- [ ] **Step 3: Commit and finalize working tree**

```bash
git add walkthrough.md
git commit -m "docs(lembaga): record 5-tab institutional relational portal completion in walkthrough"
```

---
