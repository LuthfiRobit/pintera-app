# Jabatan Tambahan Master CRUD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun portal manajemen CRUD mandiri untuk data master Jabatan Tambahan (`jabatan_tambahan_master`) di panel Admin dengan antarmuka Single Page Application (SPA) yang reaktif menggunakan AJAX/Fetch & Alpine.js serta dilengkapi proteksi integritas relasi ke data Guru.

**Architecture:** Arsitektur menggunakan pola Standard Laravel Controller yang mendukung pengembalian tampilan Blade maupun JSON (saat AJAX fetch), didukung 4 RBAC permissions khusus (`jabatan-tambahan-master.*`). Bagian frontend ber berorientasi *zero-reload* menggunakan satu reactive Alpine.js state object, tab filtering seketika, modal dinamis dengan penanganan error 422 realtime, dan proteksi hapus yang mencegah orphan records atau constraint failures.

**Tech Stack:** PHP 8.3, Laravel 11, SQLite/MySQL, Blade Templating, Alpine.js, Tailwind CSS, Pest/PHPUnit.

## Global Constraints

- **Styling**: Vanilla Tailwind CSS classes; dilarang memakai class utilitas yang tidak terdefinisi (gunakan class standar `scrollbar-none` untuk tab bar).
- **Aset & Ikon**: Gunakan komponen `<x-icon name="..." />` atau elemen SVG langsung; dilarang memakai karakter emoji pada tombol atau indikator visual demi menjaga estetika profesional dan konsistensi dengan pintera.
- **Relational Safety**: Saat menghapus master jabatan, wajib mengecek `$jabatanTambahanMaster->guru()->exists()`. Jika ada relasi, tolak dengan HTTP 422 JSON dan pesan konfirmatif, jangan biarkan melempar SQL Constraint Exception.

---

### Task 1: RBAC Permissions, Backend Controller, Routes, & JSON API Endpoints

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Create: `app/Http/Controllers/Admin/JabatanTambahanMasterController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JabatanTambahanMasterCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\JabatanTambahanMaster` (existing model), `App\Models\Guru` (for pivot relationship check).
- Produces: JSON API endpoints on `/admin/jabatan-tambahan-master` (`index`, `store`, `update`, `destroy`) and permissions (`jabatan-tambahan-master.view|create|edit|delete`).

- [ ] **Step 1: Write the failing test**
Create `tests/Feature/Admin/JabatanTambahanMasterCrudTest.php` with initial behavioral assertions:
```php
<?php

use App\Models\Guru;
use App\Models\JabatanTambahanMaster;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'jabatan-tambahan-master.view',
        'jabatan-tambahan-master.create',
        'jabatan-tambahan-master.edit',
        'jabatan-tambahan-master.delete',
    ]);
});

it('denies access to unauthorized users without view permission', function () {
    $guest = User::factory()->create();
    $this->actingAs($guest)->get(route('admin.jabatan-tambahan-master.index'))->assertForbidden();
});

it('allows authorized admin to store a new master position via JSON', function () {
    $response = $this->actingAs($this->admin)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Koordinator IT Sekolah',
        'kelompok' => 'fungsional',
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['message', 'item' => ['id', 'nama', 'kelompok', 'guru_count']]);

    expect(JabatanTambahanMaster::where('nama', 'Koordinator IT Sekolah')->exists())->toBeTrue();
});

it('rejects duplicate position name via JSON validation', function () {
    JabatanTambahanMaster::create(['nama' => 'Wali Kelas', 'kelompok' => 'fungsional']);

    $response = $this->actingAs($this->admin)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Wali Kelas',
        'kelompok' => 'fungsional',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['nama']);
});

it('allows updating an existing position via JSON', function () {
    $jabatan = JabatanTambahanMaster::create(['nama' => 'Wakasek Lama', 'kelompok' => 'struktural']);

    $response = $this->actingAs($this->admin)->putJson(route('admin.jabatan-tambahan-master.update', $jabatan), [
        'nama' => 'Wakasek Baru',
        'kelompok' => 'struktural',
    ]);

    $response->assertStatus(200)
             ->assertJson(['message' => 'Data jabatan berhasil diperbarui']);

    expect($jabatan->fresh()->nama)->toBe('Wakasek Baru');
});

it('allows deleting an unassigned master position via JSON', function () {
    $jabatan = JabatanTambahanMaster::create(['nama' => 'Jabatan Sementara', 'kelompok' => 'fungsional']);

    $response = $this->actingAs($this->admin)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatan));

    $response->assertStatus(200)
             ->assertJson(['message' => 'Jabatan telah dihapus permanen.']);

    expect(JabatanTambahanMaster::where('id', $jabatan->id)->exists())->toBeFalse();
});

it('prevents deleting a master position that is currently assigned to a guru', function () {
    $jabatan = JabatanTambahanMaster::create(['nama' => 'Wali Kelas Aktif', 'kelompok' => 'fungsional']);
    $guru = Guru::factory()->create();
    $guru->jabatanTambahan()->attach($jabatan->id, ['no_sk' => 'SK-001']);

    $response = $this->actingAs($this->admin)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatan));

    $response->assertStatus(422)
             ->assertJson([
                 'message' => 'Jabatan tidak dapat dihapus karena saat ini masih disandang oleh 1 Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.'
             ]);

    expect(JabatanTambahanMaster::where('id', $jabatan->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**
Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/JabatanTambahanMasterCrudTest.php`
Expected: FAIL with route not defined or 404/permission errors.

- [ ] **Step 3: Write minimal implementation**
1. Add new permissions in `database/seeders/PermissionSeeder.php` inside the `$permissions` array right below `'guru.view', 'guru.create', 'guru.edit',`:
```php
            'jabatan-tambahan-master.view', 'jabatan-tambahan-master.create', 'jabatan-tambahan-master.edit', 'jabatan-tambahan-master.delete',
```

2. Create `app/Http/Controllers/Admin/JabatanTambahanMasterController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\JabatanTambahanMaster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JabatanTambahanMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('jabatan-tambahan-master.view');

        $jabatanList = JabatanTambahanMaster::withCount('guru')->orderBy('kelompok')->orderBy('nama')->get();

        if ($request->wantsJson()) {
            return response()->json(['items' => $jabatanList]);
        }

        return view('admin.jabatan-tambahan-master.index', [
            'jabatanList' => $jabatanList,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jabatan_tambahan_master,nama'],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $item = JabatanTambahanMaster::create($data)->loadCount('guru');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jabatan tambahan berhasil dirilis',
                'item' => $item,
            ], 201);
        }

        return back()->with('success', 'Jabatan tambahan berhasil ditambahkan.');
    }

    public function update(Request $request, JabatanTambahanMaster $jabatanTambahanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jabatan_tambahan_master', 'nama')->ignore($jabatanTambahanMaster->id)],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $jabatanTambahanMaster->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data jabatan berhasil diperbarui',
                'item' => $jabatanTambahanMaster->fresh()->loadCount('guru'),
            ], 200);
        }

        return back()->with('success', 'Jabatan tambahan berhasil diperbarui.');
    }

    public function destroy(Request $request, JabatanTambahanMaster $jabatanTambahanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.delete');

        $guruCount = $jabatanTambahanMaster->guru()->count();
        if ($guruCount > 0) {
            $message = "Jabatan tidak dapat dihapus karena saat ini masih disandang oleh {$guruCount} Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.";
            
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $jabatanTambahanMaster->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jabatan telah dihapus permanen.'], 200);
        }

        return back()->with('success', 'Jabatan telah dihapus permanen.');
    }
}
```

3. Register routes in `routes/admin.php` (import the controller at the top and register resource routes under `admin` prefix):
```php
use App\Http\Controllers\Admin\JabatanTambahanMasterController;

// Under Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'index'])->name('jabatan-tambahan-master.index');
    Route::post('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'store'])->name('jabatan-tambahan-master.store');
    Route::put('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'update'])->name('jabatan-tambahan-master.update');
    Route::delete('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'destroy'])->name('jabatan-tambahan-master.destroy');
```

- [ ] **Step 4: Run test to verify it passes**
Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/JabatanTambahanMasterCrudTest.php`
Expected: PASS all 6 backend tests.

- [ ] **Step 5: Commit**
```bash
git add database/seeders/PermissionSeeder.php app/Http/Controllers/Admin/JabatanTambahanMasterController.php routes/admin.php tests/Feature/Admin/JabatanTambahanMasterCrudTest.php
git commit -m "feat(backend): implement JabatanTambahanMaster CRUD API with RBAC and relational protection"
```

---

### Task 2: Reactive SPA Frontend UI (Blade & Alpine.js with AJAX/Fetch)

**Files:**
- Create: `resources/views/admin/jabatan-tambahan-master/index.blade.php`
- Modify: `tests/Feature/Admin/JabatanTambahanMasterCrudTest.php`

**Interfaces:**
- Consumes: JSON endpoints from Task 1 (`admin.jabatan-tambahan-master.*`).
- Produces: Interactive SPA UI with live statistics, tab filtering, modal forms, and toast notifications.

- [ ] **Step 1: Write the failing test**
Append a new test case to `tests/Feature/Admin/JabatanTambahanMasterCrudTest.php`:
```php
it('renders the reactive SPA portal view cleanly with expected Alpine data bindings and tab bar', function () {
    JabatanTambahanMaster::create(['nama' => 'Wali Kelas', 'kelompok' => 'fungsional']);
    JabatanTambahanMaster::create(['nama' => 'Wakasek Kurikulum', 'kelompok' => 'struktural']);

    $response = $this->actingAs($this->admin)->get(route('admin.jabatan-tambahan-master.index'));

    $response->assertStatus(200)
             ->assertSee('Wali Kelas')
             ->assertSee('Wakasek Kurikulum')
             ->assertSee('Master Jabatan Tambahan')
             ->assertSee('activeFilter')
             ->assertSee('scrollbar-none');
});
```

- [ ] **Step 2: Run test to verify it fails**
Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/JabatanTambahanMasterCrudTest.php --filter="renders_the_reactive_SPA_portal"`
Expected: FAIL with "View [admin.jabatan-tambahan-master.index] not found".

- [ ] **Step 3: Write minimal implementation**
Create `resources/views/admin/jabatan-tambahan-master/index.blade.php`:
```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6 pb-12" x-data="jabatanMasterCRUD(@json($jabatanList))">
        {{-- Floating Toast Notification --}}
        <div x-show="toast.visible" x-transition:enter="transition ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-2 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" 
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 scale-100" 
             x-transition:leave-end="opacity-0 translate-y-2 scale-95" 
             class="fixed bottom-6 right-6 z-50 max-w-md rounded-xl p-4 shadow-xl text-white font-medium flex items-center gap-3 border"
             :class="toast.type === 'success' ? 'bg-emerald-600 border-emerald-700' : 'bg-rose-600 border-rose-700'"
             style="display: none;">
            <div class="p-1 rounded-full bg-white/20">
                <template x-if="toast.type === 'success'">
                    <x-icon name="check" class="w-5 h-5" />
                </template>
                <template x-if="toast.type !== 'success'">
                    <x-icon name="warning_amber" class="w-5 h-5" />
                </template>
            </div>
            <span x-text="toast.message" class="flex-1 text-sm leading-snug"></span>
            <button @click="toast.visible = false" class="text-white/80 hover:text-white transition">
                <x-icon name="close" class="w-4 h-4" />
            </button>
        </div>

        {{-- Hero Header & Reactive Stats --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-400">
                            <x-icon name="badge" class="w-6 h-6" />
                        </span>
                        Master Jabatan Tambahan
                    </h1>
                    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
                        Kelola direktori posisistruktural dan fungsional yang dapat disandang oleh guru.
                    </p>
                </div>

                @can('jabatan-tambahan-master.create')
                <button type="button" @click="openCreateModal()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                    <x-icon name="add" class="h-4 w-4" />
                    <span>Tambah Jabatan</span>
                </button>
                @endcan
            </div>

            {{-- Live Statistics Cards --}}
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-gray-800/80 dark:bg-gray-800/50">
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Jabatan</div>
                    <div class="mt-1.5 text-2xl font-black text-gray-900 dark:text-white" x-text="items.length"></div>
                </div>
                <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-4 dark:border-blue-900/20 dark:bg-blue-900/10">
                    <div class="text-xs font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">Posisi Struktural</div>
                    <div class="mt-1.5 text-2xl font-black text-blue-900 dark:text-blue-300" x-text="countStruktural"></div>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4 dark:border-emerald-900/20 dark:bg-emerald-900/10">
                    <div class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Posisi Fungsional</div>
                    <div class="mt-1.5 text-2xl font-black text-emerald-900 dark:text-emerald-300" x-text="countFungsional"></div>
                </div>
            </div>

            {{-- Instant Client-side Tab Filtering (Using aesthetic scrollbar-none) --}}
            <div class="mt-6 flex border-b border-gray-200 overflow-x-auto text-sm font-semibold text-gray-500 scrollbar-none dark:border-gray-800">
                <button type="button" @click="activeFilter = 'all'" :class="activeFilter === 'all' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>Semua</span>
                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400" x-text="items.length"></span>
                </button>
                <button type="button" @click="activeFilter = 'struktural'" :class="activeFilter === 'struktural' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>Struktural</span>
                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300" x-text="countStruktural"></span>
                </button>
                <button type="button" @click="activeFilter = 'fungsional'" :class="activeFilter === 'fungsional' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>Fungsional</span>
                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300" x-text="countFungsional"></span>
                </button>
            </div>
        </div>

        {{-- Table Section --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto scrollbar-thin">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3.5">Nama Jabatan</th>
                            <th class="px-6 py-3.5">Kelompok</th>
                            <th class="px-6 py-3.5">Status Penggunaan</th>
                            <th class="px-6 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        <template x-for="item in filteredItems" :key="item.id">
                            <tr class="transition hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                <td class="whitespace-nowrap px-6 py-4 font-semibold text-gray-900 dark:text-white" x-text="item.nama"></td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span x-show="item.kelompok === 'struktural'" class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                        Struktural
                                    </span>
                                    <span x-show="item.kelompok === 'fungsional'" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Fungsional
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="inline-flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        <x-icon name="group" class="h-3.5 w-3.5 text-gray-500" />
                                        <span x-text="item.guru_count + ' Guru'"></span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        @can('jabatan-tambahan-master.edit')
                                        <button type="button" @click="openEditModal(item)" title="Edit Jabatan" class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-800 dark:hover:text-blue-400">
                                            <x-icon name="edit" class="h-4 w-4" />
                                        </button>
                                        @endcan

                                        @can('jabatan-tambahan-master.delete')
                                        <button type="button" @click="deleteItem(item.id, item.nama)" title="Hapus Jabatan" class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-rose-600 dark:hover:bg-gray-800 dark:hover:text-rose-400">
                                            <x-icon name="delete" class="h-4 w-4" />
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty State --}}
                        <tr x-show="filteredItems.length === 0">
                            <td colspan="4" class="py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                        <x-icon name="info" class="h-6 w-6 text-gray-400" />
                                    </div>
                                    <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">Tidak ada data jabatan</h3>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Belum ada posisi yang terdaftar pada kategori ini.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Single Dynamic Modal for Add & Edit --}}
        <div x-show="modal.isOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="display: none;">
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="modal.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500/75 transition-opacity backdrop-blur-xs dark:bg-gray-900/80" @click="closeModal()"></div>

                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div x-show="modal.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block transform overflow-hidden rounded-2xl bg-white px-4 pt-5 pb-4 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 sm:align-middle dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white" id="modal-title" x-text="modal.mode === 'add' ? 'Tambah Jabatan Baru' : 'Edit Jabatan Tambahan'"></h3>
                        <button type="button" @click="closeModal()" class="rounded-lg text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition p-1">
                            <x-icon name="close" class="h-5 w-5" />
                        </button>
                    </div>

                    <form @submit.prevent="save()" class="mt-4 space-y-4">
                        <div>
                            <label for="nama_jabatan" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Nama Jabatan <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" x-model="modal.form.nama" id="nama_jabatan" required placeholder="Contoh: Wakil Kepala Sekolah Sarpras" 
                                   class="mt-1 block w-full rounded-xl border-gray-300 px-4 py-2.5 text-sm transition focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                   :class="{'border-rose-500 ring-rose-500 focus:border-rose-500 focus:ring-rose-500': modal.errors.nama}">
                            <template x-if="modal.errors.nama">
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" x-text="modal.errors.nama[0]"></p>
                            </template>
                        </div>

                        <div>
                            <label for="kelompok_jabatan" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Kelompok <span class="text-rose-500">*</span>
                            </label>
                            <select x-model="modal.form.kelompok" id="kelompok_jabatan" required 
                                    class="mt-1 block w-full rounded-xl border-gray-300 px-4 py-2.5 text-sm transition focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="struktural">Struktural</option>
                                <option value="fungsional">Fungsional</option>
                            </select>
                            <template x-if="modal.errors.kelompok">
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" x-text="modal.errors.kelompok[0]"></p>
                            </template>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <button type="button" @click="closeModal()" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                Batal
                            </button>
                            <button type="submit" :disabled="modal.isSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-50">
                                <template x-if="modal.isSubmitting">
                                    <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                                </template>
                                <span x-text="modal.mode === 'add' ? 'Simpan Data' : 'Perbarui Data'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function jabatanMasterCRUD(initialItems) {
            return {
                items: initialItems || [],
                activeFilter: 'all',
                toast: {
                    visible: false,
                    message: '',
                    type: 'success',
                    timer: null
                },
                modal: {
                    isOpen: false,
                    mode: 'add',
                    isSubmitting: false,
                    form: {
                        id: null,
                        nama: '',
                        kelompok: 'fungsional'
                    },
                    errors: {}
                },
                get countStruktural() {
                    return this.items.filter(i => i.kelompok === 'struktural').length;
                },
                get countFungsional() {
                    return this.items.filter(i => i.kelompok === 'fungsional').length;
                },
                get filteredItems() {
                    if (this.activeFilter === 'all') return this.items;
                    return this.items.filter(i => i.kelompok === this.activeFilter);
                },
                showToast(msg, type = 'success') {
                    this.toast.message = msg;
                    this.toast.type = type;
                    this.toast.visible = true;
                    if (this.toast.timer) clearTimeout(this.toast.timer);
                    this.toast.timer = setTimeout(() => {
                        this.toast.visible = false;
                    }, 5000);
                },
                openCreateModal() {
                    this.modal.mode = 'add';
                    this.modal.form = { id: null, nama: '', kelompok: 'fungsional' };
                    this.modal.errors = {};
                    this.modal.isOpen = true;
                },
                openEditModal(item) {
                    this.modal.mode = 'edit';
                    this.modal.form = { id: item.id, nama: item.nama, kelompok: item.kelompok };
                    this.modal.errors = {};
                    this.modal.isOpen = true;
                },
                closeModal() {
                    this.modal.isOpen = false;
                },
                async save() {
                    this.modal.isSubmitting = true;
                    this.modal.errors = {};

                    const isEdit = this.modal.mode === 'edit';
                    const url = isEdit 
                        ? `/admin/jabatan-tambahan-master/${this.modal.form.id}`
                        : `/admin/jabatan-tambahan-master`;
                    const method = isEdit ? 'PUT' : 'POST';

                    try {
                        const response = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify(this.modal.form)
                        });

                        const data = await response.json();

                        if (response.status === 422) {
                            this.modal.errors = data.errors || {};
                            return;
                        }

                        if (!response.ok) {
                            throw new Error(data.message || 'Terjadi kesalahan pada server');
                        }

                        if (isEdit) {
                            const index = this.items.findIndex(i => i.id === this.modal.form.id);
                            if (index !== -1) {
                                this.items[index] = data.item;
                            }
                        } else {
                            this.items.push(data.item);
                            this.items.sort((a, b) => a.nama.localeCompare(b.nama));
                        }

                        this.closeModal();
                        this.showToast(data.message || 'Data berhasil disimpan!', 'success');
                    } catch (error) {
                        this.showToast(error.message, 'error');
                    } finally {
                        this.modal.isSubmitting = false;
                    }
                },
                async deleteItem(id, nama) {
                    if (!confirm(`Apakah Anda yakin ingin menghapus jabatan "${nama}"?`)) {
                        return;
                    }

                    try {
                        const response = await fetch(`/admin/jabatan-tambahan-master/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            if (response.status === 422) {
                                this.showToast(data.message, 'error');
                                return;
                            }
                            throw new Error(data.message || 'Gagal menghapus data.');
                        }

                        this.items = this.items.filter(i => i.id !== id);
                        this.showToast(data.message || 'Jabatan telah dihapus.', 'success');
                    } catch (error) {
                        this.showToast(error.message, 'error');
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
```

- [ ] **Step 4: Run test to verify it passes**
Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/JabatanTambahanMasterCrudTest.php`
Expected: PASS all 7 tests.

- [ ] **Step 5: Commit**
```bash
git add resources/views/admin/jabatan-tambahan-master/index.blade.php tests/Feature/Admin/JabatanTambahanMasterCrudTest.php
git commit -m "feat(ui): implement reactive SPA index view for Jabatan Tambahan Master with Alpine.js AJAX"
```

---

## Self-Review Verification

1. **Spec Coverage**: All requirements (RBAC permissions, JSON controller routes, relational delete shield with HTTP 422, Alpine reactive table & filter, Toast alerts) are directly implemented in Tasks 1 and 2.
2. **No Placeholders**: Exact PHP code, complete Blade template, and full Alpine JavaScript logical implementation provided without any TBDs or omitted blocks.
3. **Type & Naming Consistency**: Route names (`admin.jabatan-tambahan-master.*`), methods, and database columns match perfectly between backend test assertions and Alpine AJAX fetch URLs.
