# Modernisasi Modul Tahun Ajaran & Semester (Data Induk) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memodernisasi modul Tahun Ajaran & Semester dengan mengkonversi alur pencatatan menjadi SPA Modals pop-up, menerapkan Batch Upsert untun konfigurasi simultan Semester Ganjil & Genap dalam 1 form, serta menampilkan struktur antarmuka Informative Executive Cards bermeta-data lengkap layaknya Komponen Penilaian.

**Architecture:** 
- Menggunakan arsitektur MVC Laravel berbantuan Blade & Alpine.js untuk kontrol modal interaktif berakurasi *Zero Page Reload Navigation*.
- Backend memproses integrasi transaksi ganda (*Batch Upsert*) `updateOrCreate` saat penyimpanan form semester berdasarkan kunci `tahun_ajaran_id` dan `urutan` (`1` untuk Ganjil, `2` untuk Genap).
- Standar penataan UI mematuhi sistem desain Navy Portal (tinggi input konstan 42px, kartu bernuansa jernih `shadow-card`, dan pemisahan panel yang tanggap terhadap status aktif kurikulum).

**Tech Stack:** Laravel 11, Tailwind CSS, Alpine.js, PHPUnit/Pest.

## Global Constraints
- **3-Input Standard**: Entitas $\le 3$ input diatur langsung lewat modal SPA pada halaman utama, bukan di halaman terpisah.
- **Unified Semester Form**: Semua pengeditan maupun penambahan semester (Ganjil dan Genap) berjalan secara berbarengan per `tahun_ajaran_id` dalam sekali simpan.
- **Proteksi Integritas Data Induk**: Pengaktifan suatu semester dilarang keras oleh domain jika Tahun Ajaran induknya berposisi nonaktif (*inactive/false*).
- **Desain Executive Card & Compact KPI Tile**: Kartu statistik wajib bergaya horisontal ringkas. Panel daftar menempatkan rincian kedua semester bersebelahan dalam grid 2 kolom (`grid-cols-1 md:grid-cols-2`).

---

### Task 1: Backend & Routing (Tahun Ajaran Update & Semester Batch Upsert Store)

**Files:**
- Create: `tests/Feature/Admin/TahunAjaranSemesterFeatureTest.php`
- Modify: `app/Http/Controllers/Admin/TahunAjaranController.php`
- Modify: `app/Http/Controllers/Admin/SemesterController.php`
- Modify: `routes/admin.php:108-112`

**Interfaces:**
- Consumes: `App\Models\TahunAjaran`, `App\Models\Semester`
- Produces: `PUT /admin/tahun-ajaran/{tahunAjaran}` (`admin.tahun-ajaran.update`), `POST /admin/semester` (`admin.semester.store` untuk Batch Upsert)

- [ ] **Step 1: Write the failing feature test**

```php
// tests/Feature/Admin/TahunAjaranSemesterFeatureTest.php
<?php

use App\Models\Lembaga;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;

function createAdminTahunAjaranUser(): array {
    $lembaga = Lembaga::create([
        'nama' => 'SMP Pintera Test',
        'domain' => 'smp.pintera.test',
        'tingkat' => 'smp',
    ]);

    $role = Role::create([
        'nama' => 'Administrator Test',
        'guard_name' => 'web',
        'level' => 'lembaga',
        'scope_level' => 'lembaga',
        'lembaga_id' => $lembaga->id,
    ]);
    
    foreach (['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'] as $pName) {
        $p = Permission::firstOrCreate(['nama' => $pName], ['guard_name' => 'web', 'kategori' => 'akademik', 'label' => $pName]);
        $role->permissions()->attach($p->id);
    }

    $user = User::factory()->create(['name' => 'Admin Mapel', 'email' => 'admin.ta@pintera.test', 'status_aktif' => true]);
    $user->assignRole($role);
    
    return [$user, $lembaga];
}

it('updates existing tahun ajaran attributes via SPA edit modal action', function () {
    [$user, $lembaga] = createAdminTahunAjaranUser();
    $ta = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01',
        'tanggal_selesai' => '2026-06-30',
        'status_aktif' => true
    ]);

    $response = $this->actingAs($user)->put(route('admin.tahun-ajaran.update', $ta), [
        'nama' => '2025/2026 Revisi',
        'tanggal_mulai' => '2025-07-15',
        'tanggal_selesai' => '2026-06-25',
    ]);

    $response->assertRedirect(route('admin.tahun-ajaran.index'))->assertSessionHas('status');
    $this->assertDatabaseHas('tahun_ajaran', [
        'id' => $ta->id,
        'nama' => '2025/2026 Revisi',
    ]);
});

it('creates and updates ganjil and genap semesters via batch upsert endpoint in one request', function () {
    [$user, $lembaga] = createAdminTahunAjaranUser();
    $ta = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => true
    ]);

    $response = $this->actingAs($user)->post(route('admin.semester.store'), [
        'tahun_ajaran_id' => $ta->id,
        'ganjil_kode_dapodik' => '20261',
        'ganjil_tanggal_mulai' => '2026-07-01',
        'ganjil_tanggal_selesai' => '2026-12-31',
        'genap_kode_dapodik' => '20262',
        'genap_tanggal_mulai' => '2027-01-01',
        'genap_tanggal_selesai' => '2027-06-30',
    ]);

    $response->assertRedirect(route('admin.tahun-ajaran.index'))->assertSessionHas('status', 'Konfigurasi semester Ganjil & Genap berhasil disimpan.');
    expect(Semester::where('tahun_ajaran_id', $ta->id)->count())->toBe(2);
    $this->assertDatabaseHas('semester', ['tahun_ajaran_id' => $ta->id, 'nama' => 'Ganjil', 'urutan' => 1, 'kode_dapodik' => '20261']);
    $this->assertDatabaseHas('semester', ['tahun_ajaran_id' => $ta->id, 'nama' => 'Genap', 'urutan' => 2, 'kode_dapodik' => '20262']);

    // Second hit should update in place without duplication
    $this->actingAs($user)->post(route('admin.semester.store'), [
        'tahun_ajaran_id' => $ta->id,
        'ganjil_kode_dapodik' => '20261-NEW',
        'ganjil_tanggal_mulai' => '2026-07-05',
        'ganjil_tanggal_selesai' => '2026-12-20',
        'genap_kode_dapodik' => '20262-NEW',
        'genap_tanggal_mulai' => '2027-01-05',
        'genap_tanggal_selesai' => '2027-06-25',
    ]);
    
    expect(Semester::where('tahun_ajaran_id', $ta->id)->count())->toBe(2);
    $this->assertDatabaseHas('semester', ['tahun_ajaran_id' => $ta->id, 'nama' => 'Ganjil', 'kode_dapodik' => '20261-NEW']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TahunAjaranSemesterFeatureTest`  
Expected: FAIL with route `admin.tahun-ajaran.update` not defined and validation failure on batch semester payload.

- [ ] **Step 3: Write minimal implementation in Controller & Route**

Update `routes/admin.php`:
```php
    Route::resource('tahun-ajaran', TahunAjaranController::class)->except(['show', 'edit', 'destroy']);
    Route::patch('tahun-ajaran/{tahunAjaran}/activate', [TahunAjaranController::class, 'activate'])->name('tahun-ajaran.activate');
    Route::post('semester', [SemesterController::class, 'store'])->name('semester.store');
    Route::patch('semester/{semester}/activate', [SemesterController::class, 'activate'])->name('semester.activate');
```

Update `TahunAjaranController.php`:
```php
    public function update(Request $request, TahunAjaran $tahunAjaran): RedirectResponse
    {
        $this->authorize('tahun-ajaran.create'); // Uses same permission or manage authority

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:20'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ]);

        $tahunAjaran->update($data);

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Tahun ajaran berhasil diperbarui.');
    }
```

Update `SemesterController.php`:
```php
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('semester.create');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer'],
            'ganjil_kode_dapodik' => ['nullable', 'string', 'max:10'],
            'ganjil_tanggal_mulai' => ['required', 'date'],
            'ganjil_tanggal_selesai' => ['required', 'date', 'after:ganjil_tanggal_mulai'],
            'genap_kode_dapodik' => ['nullable', 'string', 'max:10'],
            'genap_tanggal_mulai' => ['required', 'date', 'after:ganjil_tanggal_selesai'],
            'genap_tanggal_selesai' => ['required', 'date', 'after:genap_tanggal_mulai'],
        ]);

        $tahunAjaran = TahunAjaran::withoutGlobalScopes()->findOrFail($data['tahun_ajaran_id']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $tahunAjaran) {
            Semester::updateOrCreate(
                ['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 1],
                [
                    'lembaga_id' => $tahunAjaran->lembaga_id,
                    'nama' => 'Ganjil',
                    'kode_dapodik' => $data['ganjil_kode_dapodik'],
                    'tanggal_mulai' => $data['ganjil_tanggal_mulai'],
                    'tanggal_selesai' => $data['ganjil_tanggal_selesai'],
                ]
            );

            Semester::updateOrCreate(
                ['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 2],
                [
                    'lembaga_id' => $tahunAjaran->lembaga_id,
                    'nama' => 'Genap',
                    'kode_dapodik' => $data['genap_kode_dapodik'],
                    'tanggal_mulai' => $data['genap_tanggal_mulai'],
                    'tanggal_selesai' => $data['genap_tanggal_selesai'],
                ]
            );
        });

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Konfigurasi semester Ganjil & Genap berhasil disimpan.');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TahunAjaranSemesterFeatureTest`  
Expected: PASS with 2 assertions passed.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Admin/TahunAjaranSemesterFeatureTest.php app/Http/Controllers/Admin/TahunAjaranController.php app/Http/Controllers/Admin/SemesterController.php routes/admin.php
git commit -m "feat(akademik): implement batch upsert for semester and update endpoint for tahun ajaran"
```

---

### Task 2: Frontend SPA Modals & Alpine Components

**Files:**
- Create: `resources/views/admin/tahun-ajaran/_modal-tahun-ajaran.blade.php`
- Create: `resources/views/admin/tahun-ajaran/_modal-semester.blade.php`

**Interfaces:**
- Consumes: Alpine bindings (`showModalTahunAjaran`, `modalTahunAjaranMode`, `showModalSemester`, etc.)
- Produces: Modal containers cleanly styled with Navy Portal design system (no overflow clipping, standardized input sizing, clear section dividers for Ganjil & Genap).

- [ ] **Step 1: Write `_modal-tahun-ajaran.blade.php` component**

Create a clean modal file with 3 inputs (Nama, Tanggal Mulai, Tanggal Selesai) utilizing Alpine.js binding for dynamic form actions (Create vs Edit mode):
```html
<div x-show="showModalTahunAjaran" class="fixed inset-0 z-50 overflow-y-auto" x-cloak style="display: none;">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" @click="showModalTahunAjaran = false"></div>
        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl border border-gray-200">
            <div class="border-b border-gray-100 bg-gray-50/50 px-6 py-4 flex items-center justify-between">
                <p class="font-display text-sm font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="date_range" class="h-4 w-4 text-brand-500" />
                    <span x-text="modalTahunAjaranMode === 'create' ? 'Tambah Tahun Ajaran Baru' : 'Edit Tahun Ajaran'"></span>
                </p>
                <button type="button" @click="showModalTahunAjaran = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form :action="modalTahunAjaranAction" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="modalTahunAjaranMode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <div>
                    <x-input-label value="Nama Tahun Ajaran *" />
                    <x-text-input type="text" name="nama" x-model="formTa.nama" required placeholder="Contoh: 2026/2027" class="mt-1.5 block w-full" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Tanggal Mulai *" />
                        <x-text-input type="date" name="tanggal_mulai" x-model="formTa.tanggal_mulai" required class="mt-1.5 block w-full" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Selesai *" />
                        <x-text-input type="date" name="tanggal_selesai" x-model="formTa.tanggal_selesai" required class="mt-1.5 block w-full" />
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="showModalTahunAjaran = false" class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Batal</button>
                    <x-primary-button type="submit">Simpan Tahun Ajaran</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Write `_modal-semester.blade.php` (Unified Batch Form)**

Create the dual-section modal for configuring Ganjil & Genap in one window:
```html
<div x-show="showModalSemester" class="fixed inset-0 z-50 overflow-y-auto" x-cloak style="display: none;">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" @click="showModalSemester = false"></div>
        <div class="relative w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-xl border border-gray-200">
            <div class="border-b border-gray-100 bg-gray-50/50 px-6 py-4 flex items-center justify-between">
                <div>
                    <p class="font-display text-sm font-bold text-gray-900 flex items-center gap-2">
                        <x-icon name="view_timeline" class="h-4 w-4 text-brand-500" />
                        Konfigurasi Semester Ganjil &amp; Genap
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">Tahun Ajaran <b class="text-gray-800" x-text="selectedTaName"></b>. Atur kalender dan kode Dapodik untuk kedua semester sekaligus.</p>
                </div>
                <button type="button" @click="showModalSemester = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form action="{{ route('admin.semester.store') }}" method="POST" class="p-6 space-y-6">
                @csrf
                <input type="hidden" name="tahun_ajaran_id" :value="selectedTaId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Sesi Ganjil --}}
                    <div class="rounded-xl border border-blue-100 bg-blue-50/30 p-4 space-y-3.5">
                        <div class="flex items-center justify-between border-b border-blue-100 pb-2.5">
                            <span class="font-display text-xs font-bold uppercase tracking-wider text-blue-800 flex items-center gap-1.5">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-[10px] text-white font-mono">1</span>
                                Semester Ganjil
                            </span>
                        </div>
                        <div>
                            <x-input-label value="Kode Dapodik (Opsional)" />
                            <x-text-input type="text" name="ganjil_kode_dapodik" x-model="formSem.ganjil_kode_dapodik" placeholder="Misal: 20261" class="mt-1 block w-full text-sm" />
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <x-input-label value="Tanggal Mulai *" />
                                <x-text-input type="date" name="ganjil_tanggal_mulai" x-model="formSem.ganjil_tanggal_mulai" required class="mt-1 block w-full text-sm" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal Selesai *" />
                                <x-text-input type="date" name="ganjil_tanggal_selesai" x-model="formSem.ganjil_tanggal_selesai" required class="mt-1 block w-full text-sm" />
                            </div>
                        </div>
                    </div>

                    {{-- Sesi Genap --}}
                    <div class="rounded-xl border border-amber-100 bg-amber-50/30 p-4 space-y-3.5">
                        <div class="flex items-center justify-between border-b border-amber-100 pb-2.5">
                            <span class="font-display text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-1.5">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-600 text-[10px] text-white font-mono">2</span>
                                Semester Genap
                            </span>
                        </div>
                        <div>
                            <x-input-label value="Kode Dapodik (Opsional)" />
                            <x-text-input type="text" name="genap_kode_dapodik" x-model="formSem.genap_kode_dapodik" placeholder="Misal: 20262" class="mt-1 block w-full text-sm" />
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <x-input-label value="Tanggal Mulai *" />
                                <x-text-input type="date" name="genap_tanggal_mulai" x-model="formSem.genap_tanggal_mulai" required class="mt-1 block w-full text-sm" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal Selesai *" />
                                <x-text-input type="date" name="genap_tanggal_selesai" x-model="formSem.genap_tanggal_selesai" required class="mt-1 block w-full text-sm" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="showModalSemester = false" class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Batal</button>
                    <x-primary-button type="submit">Simpan Konfigurasi Semester</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
```

- [ ] **Step 3: Commit modal files**

```bash
git add resources/views/admin/tahun-ajaran/_modal-tahun-ajaran.blade.php resources/views/admin/tahun-ajaran/_modal-semester.blade.php
git commit -m "feat(akademik): build SPA modal components for tahun ajaran and unified batch semester"
```

---

### Task 3: Executive Informative Card Index Dashboard & Integration

**Files:**
- Modify: `resources/views/admin/tahun-ajaran/index.blade.php`
- Delete: `resources/views/admin/tahun-ajaran/create.blade.php`
- Test: `tests/Feature/Admin/TahunAjaranSemesterFeatureTest.php`

**Interfaces:**
- Consumes: SPA Modal components, `tahunAjaranList` collection with eager loaded `semester`.
- Produces: Executive dashboard displaying Compact Horizontal KPI Cards and structured cards per Tahun Ajaran with 2-column Semester grids.

- [ ] **Step 1: Transform `index.blade.php` with modern layouting & Alpine controller**

Replace the old index structure with integrated breadcrumbs, KPI statistic tiles, and informative executive cards:
```html
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6" x-data="tahunAjaranDashboard()">
        {{-- Flash Messages & Toast --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Tahun Ajaran &amp; Semester</h1>
                <p class="text-sm text-gray-500">
                    Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tahun Ajaran &amp; Semester</b>
                </p>
            </div>
            @can('tahun-ajaran.create')
            <button type="button" @click="openCreateTaModal()" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 transition">
                <span class="text-lg leading-none">&plus;</span> Tambah Tahun Ajaran
            </button>
            @endcan
        </div>

        {{-- Compact Horizontal Statistic Cards --}}
        @php
            $aktifTa = $tahunAjaranList->firstWhere('status_aktif', true);
            $aktifSem = $aktifTa?->semester->firstWhere('status_aktif', true);
        @endphp
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Tahun Ajaran</p>
                    <p class="mt-1 font-display text-xl font-bold text-gray-900">{{ $tahunAjaranList->count() }}</p>
                </div>
                <div class="rounded-xl bg-brand-50 p-3 text-brand-600"><x-icon name="date_range" class="h-6 w-6" /></div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Tahun Ajaran Aktif</p>
                    <p class="mt-1 font-display text-xl font-bold {{ $aktifTa ? 'text-brand-600' : 'text-gray-400' }}">{{ $aktifTa?->nama ?? 'Belum Ada' }}</p>
                </div>
                <div class="rounded-xl bg-green-50 p-3 text-green-600"><x-icon name="check_circle" class="h-6 w-6" /></div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Semester Berjalan</p>
                    <p class="mt-1 font-display text-xl font-bold {{ $aktifSem ? 'text-amber-600' : 'text-gray-400' }}">{{ $aktifSem ? $aktifSem->nama . ' ' . $aktifTa->nama : 'Belum Aktif' }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 p-3 text-amber-600"><x-icon name="view_timeline" class="h-6 w-6" /></div>
            </div>
        </div>

        {{-- Executive Informative Academic Cards --}}
        <div class="space-y-6">
            @forelse ($tahunAjaranList as $ta)
                @php
                    $ganjil = $ta->semester->firstWhere('urutan', 1) ?? $ta->semester->firstWhere('nama', 'Ganjil');
                    $genap = $ta->semester->firstWhere('urutan', 2) ?? $ta->semester->firstWhere('nama', 'Genap');
                @endphp
                <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
                    {{-- Header Kartu TA --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 bg-gray-50/60 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <h3 class="font-display text-xl font-bold text-gray-900">{{ $ta->nama }}</h3>
                            @if ($ta->status_aktif)
                                <span class="inline-flex items-center rounded-full bg-success-50 px-3 py-0.5 text-xs font-bold text-success-700 border border-success-200/60">Aktif &amp; Berjalan</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-0.5 text-xs font-medium text-gray-600">Nonaktif / Selesai</span>
                            @endif
                            <span class="text-xs font-medium text-gray-500 flex items-center gap-1">
                                <x-icon name="event" class="h-3.5 w-3.5 text-gray-400" />
                                {{ $ta->tanggal_mulai?->translatedFormat('d M Y') }} &ndash; {{ $ta->tanggal_selesai?->translatedFormat('d M Y') }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            @unless ($ta->status_aktif)
                                @can('tahun-ajaran.activate')
                                <form method="POST" action="{{ route('admin.tahun-ajaran.activate', $ta) }}" x-data @submit.prevent="confirmDialog('Aktifkan Tahun Ajaran?', @js('Mengaktifkan ' . $ta->nama . ' akan menonaktifkan tahun ajaran dan semester aktif sebelumnya.'), { confirmLabel: 'Ya, Aktifkan' }).then(confirmed => { if (confirmed) $el.submit() })">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 border border-emerald-200/60 hover:bg-emerald-100 transition">Aktifkan TA</button>
                                </form>
                                @endcan
                            @endunless
                            @can('tahun-ajaran.create')
                            <button type="button" @click="openEditTaModal(@js($ta->id), @js($ta->nama), @js($ta->tanggal_mulai?->format('Y-m-d')), @js($ta->tanggal_selesai?->format('Y-m-d')))" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">Edit TA</button>
                            @endcan
                            @can('semester.create')
                            <button type="button" @click="openSemesterModal(@js($ta->id), @js($ta->nama), @js($ganjil), @js($genap))" class="rounded-lg bg-brand-50 border border-brand-200/60 px-3 py-1.5 text-xs font-bold text-brand-700 hover:bg-brand-100 transition">Atur Semester</button>
                            @endcan
                        </div>
                    </div>

                    {{-- Body Kartu TA (2-Column Semester Grid) --}}
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ([1 => ['title' => 'Semester Ganjil (1)', 'data' => $ganjil, 'border' => 'border-blue-100 bg-blue-50/20', 'badge' => 'bg-blue-100 text-blue-800'], 2 => ['title' => 'Semester Genap (2)', 'data' => $genap, 'border' => 'border-amber-100 bg-amber-50/20', 'badge' => 'bg-amber-100 text-amber-800']] as $urutan => $meta)
                            @php $sem = $meta['data']; @endphp
                            <div class="rounded-xl border {{ $sem ? 'border-gray-200 bg-white shadow-sm' : $meta['border'] }} p-4 transition hover:border-gray-300">
                                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                    <span class="font-display text-sm font-bold text-gray-900 flex items-center gap-2">
                                        <span class="rounded-md px-2 py-0.5 text-[11px] font-bold {{ $meta['badge'] }}">{{ $sem ? $sem->nama : ($urutan == 1 ? 'Ganjil' : 'Genap') }}</span>
                                        @if ($sem?->kode_dapodik)
                                            <span class="font-mono text-xs text-gray-500">({{ $sem->kode_dapodik }})</span>
                                        @endif
                                    </span>
                                    @if ($sem)
                                        @if ($sem->status_aktif)
                                            <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-[11px] font-bold text-success-700 border border-success-200/50">Aktif</span>
                                        @else
                                            @can('semester.activate')
                                                @if ($ta->status_aktif)
                                                    <form method="POST" action="{{ route('admin.semester.activate', $sem) }}" x-data @submit.prevent="confirmDialog('Aktifkan Semester?', @js('Aktifkan semester ' . $sem->nama . ' untuk tahun ajaran ' . $ta->nama . '?'), { confirmLabel: 'Ya, Aktifkan' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 hover:underline">Aktifkan Semester</button>
                                                    </form>
                                                @else
                                                    <span class="text-[11px] font-medium text-gray-400 cursor-not-allowed" title="Aktifkan Tahun Ajaran terlebih dahulu untuk mengaktifkan semester">Nonaktif</span>
                                                @endif
                                            @endcan
                                        @endif
                                    @endif
                                </div>
                                <div class="pt-3">
                                    @if ($sem)
                                        <p class="text-xs text-gray-600 flex items-center gap-1.5 font-medium">
                                            <x-icon name="calendar_month" class="h-4 w-4 text-gray-400" />
                                            {{ $sem->tanggal_mulai?->translatedFormat('d M Y') }} &ndash; {{ $sem->tanggal_selesai?->translatedFormat('d M Y') }}
                                        </p>
                                    @else
                                        <p class="text-xs text-gray-400 italic flex items-center gap-1">
                                            <x-icon name="info" class="h-3.5 w-3.5 text-gray-400" />
                                            Semester belum dikonfigurasi. Klik "Atur Semester" untuk mengatur rentang tanggal.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-gray-200 bg-white p-12 text-center text-gray-400 shadow-card space-y-3">
                    <x-icon name="school" class="h-12 w-12 mx-auto text-gray-300" />
                    <p class="text-sm font-semibold text-gray-700">Belum Ada Tahun Ajaran</p>
                    <p class="text-xs text-gray-400 max-w-sm mx-auto">Tambahkan tahun ajaran dan atur semester untuk mengaktifkan kalender akademik portal.</p>
                </div>
            @endforelse
        </div>

        {{-- Modals --}}
        @include('admin.tahun-ajaran._modal-tahun-ajaran')
        @include('admin.tahun-ajaran._modal-semester')
    </div>

    <script>
        function tahunAjaranDashboard() {
            return {
                showModalTahunAjaran: false,
                modalTahunAjaranMode: 'create',
                modalTahunAjaranAction: @js(route('admin.tahun-ajaran.store')),
                formTa: { nama: '', tanggal_mulai: '', tanggal_selesai: '' },
                
                showModalSemester: false,
                selectedTaId: null,
                selectedTaName: '',
                formSem: {
                    ganjil_kode_dapodik: '', ganjil_tanggal_mulai: '', ganjil_tanggal_selesai: '',
                    genap_kode_dapodik: '', genap_tanggal_mulai: '', genap_tanggal_selesai: ''
                },

                openCreateTaModal() {
                    this.modalTahunAjaranMode = 'create';
                    this.modalTahunAjaranAction = @js(route('admin.tahun-ajaran.store'));
                    this.formTa = { nama: '', tanggal_mulai: '', tanggal_selesai: '' };
                    this.showModalTahunAjaran = true;
                },

                openEditTaModal(id, nama, tglMulai, tglSelesai) {
                    this.modalTahunAjaranMode = 'edit';
                    this.modalTahunAjaranAction = `/admin/tahun-ajaran/${id}`;
                    this.formTa = { nama: nama ?? '', tanggal_mulai: tglMulai ?? '', tanggal_selesai: tglSelesai ?? '' };
                    this.showModalTahunAjaran = true;
                },

                openSemesterModal(taId, taName, ganjil, genap) {
                    this.selectedTaId = taId;
                    this.selectedTaName = taName;
                    this.formSem = {
                        ganjil_kode_dapodik: ganjil ? ganjil.kode_dapodik ?? '' : '',
                        ganjil_tanggal_mulai: ganjil && ganjil.tanggal_mulai ? ganjil.tanggal_mulai.substring(0, 10) : '',
                        ganjil_tanggal_selesai: ganjil && ganjil.tanggal_selesai ? ganjil.tanggal_selesai.substring(0, 10) : '',
                        genap_kode_dapodik: genap ? genap.kode_dapodik ?? '' : '',
                        genap_tanggal_mulai: genap && genap.tanggal_mulai ? genap.tanggal_mulai.substring(0, 10) : '',
                        genap_tanggal_selesai: genap && genap.tanggal_selesai ? genap.tanggal_selesai.substring(0, 10) : ''
                    };
                    this.showModalSemester = true;
                }
            };
        }
    </script>
</x-app-layout>
```

- [ ] **Step 2: Remove deprecated `create.blade.php`**

Run: `git rm resources/views/admin/tahun-ajaran/create.blade.php` (or use workspace tool to remove).

- [ ] **Step 3: Run comprehensive verification tests**

Run: `php artisan test --filter=TahunAjaran` and `php artisan test --filter=LembagaDataPeriodikSeederTest`  
Expected: All tests pass without errors.

- [ ] **Step 4: Verify frontend asset compilation**

Run: `npm.cmd run build`  
Expected: Vite build succeeds with 0 errors.

- [ ] **Step 5: Commit complete transformation**

```bash
git add -A resources/views/admin/tahun-ajaran/
git commit -m "feat(akademik): transform tahun ajaran & semester index view into modern informative SPA dashboard"
```

---
## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-02-modul-tahun-ajaran-semester-plan.md`. Two execution options:

1. **Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration
2. **Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
