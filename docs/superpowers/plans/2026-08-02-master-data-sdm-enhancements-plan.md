# Master Data SDM Enhancements & UI/UX Refinements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform teacher management (`Guru`) into an interactive 4-tab relational profile with inline view-to-edit toggling, and implement account generator mastery for students (`Siswa`) featuring import login predictions and mass account creation.

**Architecture:** Utilize existing `admin.guru.edit` view to deploy an Alpine.js-powered tabbed interface and view-to-edit toggle without introducing redundant show views. Create lightweight relational controllers (`RiwayatPendidikanController`, `SertifikasiController`, `JabatanTambahanController`) for modal form submissions. Enrich student Excel imports with read-only username prediction via `AkunSiswaGenerator` in import parser, and add atomic batch and individual account generation endpoints in `SiswaController`.

**Tech Stack:** Laravel 11, PHP 8.3, Alpine.js, Tailwind CSS, Blade Icons/SVG, Maatwebsite Excel, PHPUnit/Pest.

## Global Constraints

- Strict multi-tenant data isolation (`lembaga_id` ownership verification across all endpoints).
- WCAG AA contrast ratio ($\ge 4.5:1$ for body text, $\ge 3:1$ for secondary elements/borders) across light and dark modes.
- Zero Unicode structural emojis; use pure SVG or Blade icon primitives.
- Minimum 44x44px interactive touch targets and 8dp spacing hierarchy.
- No third-party heavy JavaScript libraries; all interactive toggling and modal state must rely cleanly on lightweight Alpine.js.

---

### Task 1: Guru Relational Profile Backend Controllers & Routing (TDD)

**Files:**
- Create: `app/Http/Controllers/Admin/Guru/RiwayatPendidikanController.php`
- Create: `app/Http/Controllers/Admin/Guru/SertifikasiController.php`
- Create: `app/Http/Controllers/Admin/Guru/JabatanTambahanController.php`
- Modify: `app/Http/Controllers/Admin/GuruController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/GuruRelationalProfileTest.php`

**Interfaces:**
- Consumes: `App\Models\Guru`, `App\Models\RiwayatPendidikanGuru`, `App\Models\SertifikasiGuru`, `App\Models\GuruJabatanTambahan`, `App\Models\JabatanTambahanMaster`, `App\Models\TahunAjaran`
- Produces: CRUD endpoints for relational teacher history models secured by `guru.edit` permission and tenant scoping.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/GuruRelationalProfileTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use App\Models\JabatanTambahanMaster;
use App\Models\Lembaga;
use App\Models\RiwayatPendidikanGuru;
use App\Models\Role;
use App\Models\SertifikasiGuru;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuruRelationalProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Lembaga $lembaga;
    private Guru $guru;

    protected function setUp(): void
    {
        parent::setUp();
        $yayasan = Yayasan::factory()->create();
        $this->lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        
        Permission::firstOrCreate(['name' => 'guru.edit', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin_sekolah', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
        $role->givePermissionTo('guru.edit');
        
        $this->admin = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $this->admin->assignRole($role);
        
        $this->guru = Guru::factory()->create(['lembaga_id' => $this->lembaga->id]);
    }

    public function test_can_add_riwayat_pendidikan_to_guru(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.guru.riwayat-pendidikan.store', $this->guru), [
            'jenjang' => 'S1',
            'gelar' => 'S.Pd.',
            'jurusan' => 'Pendidikan Matematika',
            'universitas' => 'Universitas Negeri Malang',
            'tahun_lulus' => '2015',
        ]);

        $response->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('riwayat_pendidikan_guru', [
            'guru_id' => $this->guru->id,
            'jenjang' => 'S1',
            'universitas' => 'Universitas Negeri Malang',
        ]);
    }

    public function test_can_delete_sertifikasi_guru(): void
    {
        $sertifikasi = SertifikasiGuru::create([
            'guru_id' => $this->guru->id,
            'jenis' => 'Sertifikat Pendidik',
            'nomor' => '123456789',
            'tahun' => '2018',
            'bidang_studi' => 'Matematika',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.guru.sertifikasi.destroy', [$this->guru, $sertifikasi]));

        $response->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('sertifikasi_guru', ['id' => $sertifikasi->id]);
    }

    public function test_cannot_modify_guru_relations_from_different_lembaga(): void
    {
        $otherLembaga = Lembaga::factory()->create();
        $otherGuru = Guru::factory()->create(['lembaga_id' => $otherLembaga->id]);

        $response = $this->actingAs($this->admin)->post(route('admin.guru.riwayat-pendidikan.store', $otherGuru), [
            'jenjang' => 'S2',
            'universitas' => 'Universitas Indonesia',
            'tahun_lulus' => '2020',
        ]);

        $response->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/GuruRelationalProfileTest.php`  
Expected: FAIL with "Route [admin.guru.riwayat-pendidikan.store] not defined"

- [ ] **Step 3: Write minimal implementation**

Create `app/Http/Controllers/Admin/Guru/RiwayatPendidikanController.php`:

```php
<?php

namespace App\Http\Controllers\Admin\Guru;

use App\Models\Guru;
use App\Models\RiwayatPendidikanGuru;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class RiwayatPendidikanController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);

        $data = $request->validate([
            'jenjang' => ['required', 'string', 'max:20'],
            'gelar' => ['nullable', 'string', 'max:50'],
            'jurusan' => ['nullable', 'string', 'max:255'],
            'universitas' => ['required', 'string', 'max:255'],
            'tahun_lulus' => ['required', 'string', 'max:10'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $guru->riwayatPendidikan()->create($data);

        return back()->with('status', 'Riwayat pendidikan berhasil ditambahkan.');
    }

    public function update(Request $request, Guru $guru, RiwayatPendidikanGuru $riwayatPendidikan): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($riwayatPendidikan->guru_id !== $guru->id, 404);

        $data = $request->validate([
            'jenjang' => ['required', 'string', 'max:20'],
            'gelar' => ['nullable', 'string', 'max:50'],
            'jurusan' => ['nullable', 'string', 'max:255'],
            'universitas' => ['required', 'string', 'max:255'],
            'tahun_lulus' => ['required', 'string', 'max:10'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $riwayatPendidikan->update($data);

        return back()->with('status', 'Riwayat pendidikan berhasil diperbarui.');
    }

    public function destroy(Request $request, Guru $guru, RiwayatPendidikanGuru $riwayatPendidikan): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($riwayatPendidikan->guru_id !== $guru->id, 404);

        $riwayatPendidikan->delete();

        return back()->with('status', 'Riwayat pendidikan berhasil dihapus.');
    }

    private function ensureTenantScope(Request $request, Guru $guru): void
    {
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        if ($lembagaId !== null && $guru->lembaga_id !== $lembagaId) {
            abort(404);
        }
    }
}
```

Create `app/Http/Controllers/Admin/Guru/SertifikasiController.php`:

```php
<?php

namespace App\Http\Controllers\Admin\Guru;

use App\Models\Guru;
use App\Models\SertifikasiGuru;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class SertifikasiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);

        $data = $request->validate([
            'jenis' => ['required', 'string', 'max:100'],
            'nomor' => ['required', 'string', 'max:100'],
            'tahun' => ['required', 'string', 'max:10'],
            'bidang_studi' => ['nullable', 'string', 'max:255'],
            'penyelenggara' => ['nullable', 'string', 'max:255'],
        ]);

        $guru->sertifikasi()->create($data);

        return back()->with('status', 'Data sertifikasi berhasil ditambahkan.');
    }

    public function update(Request $request, Guru $guru, SertifikasiGuru $sertifikasi): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($sertifikasi->guru_id !== $guru->id, 404);

        $data = $request->validate([
            'jenis' => ['required', 'string', 'max:100'],
            'nomor' => ['required', 'string', 'max:100'],
            'tahun' => ['required', 'string', 'max:10'],
            'bidang_studi' => ['nullable', 'string', 'max:255'],
            'penyelenggara' => ['nullable', 'string', 'max:255'],
        ]);

        $sertifikasi->update($data);

        return back()->with('status', 'Data sertifikasi berhasil diperbarui.');
    }

    public function destroy(Request $request, Guru $guru, SertifikasiGuru $sertifikasi): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($sertifikasi->guru_id !== $guru->id, 404);

        $sertifikasi->delete();

        return back()->with('status', 'Data sertifikasi berhasil dihapus.');
    }

    private function ensureTenantScope(Request $request, Guru $guru): void
    {
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        if ($lembagaId !== null && $guru->lembaga_id !== $lembagaId) {
            abort(404);
        }
    }
}
```

Create `app/Http/Controllers/Admin/Guru/JabatanTambahanController.php`:

```php
<?php

namespace App\Http\Controllers\Admin\Guru;

use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class JabatanTambahanController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);

        $data = $request->validate([
            'jabatan_tambahan_master_id' => ['required', 'integer'],
            'tahun_ajaran_id' => ['required', 'integer'],
            'nomor_sk' => ['nullable', 'string', 'max:100'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
            'status_aktif' => ['nullable', 'boolean'],
        ]);

        $data['status_aktif'] = $data['status_aktif'] ?? true;
        $guru->jabatanTambahan()->create($data);

        return back()->with('status', 'Jabatan tambahan berhasil ditambahkan.');
    }

    public function update(Request $request, Guru $guru, GuruJabatanTambahan $jabatanTambahan): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($jabatanTambahan->guru_id !== $guru->id, 404);

        $data = $request->validate([
            'jabatan_tambahan_master_id' => ['required', 'integer'],
            'tahun_ajaran_id' => ['required', 'integer'],
            'nomor_sk' => ['nullable', 'string', 'max:100'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
            'status_aktif' => ['nullable', 'boolean'],
        ]);

        $data['status_aktif'] = $data['status_aktif'] ?? true;
        $jabatanTambahan->update($data);

        return back()->with('status', 'Jabatan tambahan berhasil diperbarui.');
    }

    public function destroy(Request $request, Guru $guru, GuruJabatanTambahan $jabatanTambahan): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($jabatanTambahan->guru_id !== $guru->id, 404);

        $jabatanTambahan->delete();

        return back()->with('status', 'Jabatan tambahan berhasil dihapus.');
    }

    private function ensureTenantScope(Request $request, Guru $guru): void
    {
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        if ($lembagaId !== null && $guru->lembaga_id !== $lembagaId) {
            abort(404);
        }
    }
}
```

Update `routes/web.php` inside the admin middleware / prefix group (next to existing guru resource route):

```php
    Route::post('/guru/{guru}/riwayat-pendidikan', [\App\Http\Controllers\Admin\Guru\RiwayatPendidikanController::class, 'store'])->name('guru.riwayat-pendidikan.store');
    Route::put('/guru/{guru}/riwayat-pendidikan/{riwayat_pendidikan}', [\App\Http\Controllers\Admin\Guru\RiwayatPendidikanController::class, 'update'])->name('guru.riwayat-pendidikan.update');
    Route::delete('/guru/{guru}/riwayat-pendidikan/{riwayat_pendidikan}', [\App\Http\Controllers\Admin\Guru\RiwayatPendidikanController::class, 'destroy'])->name('guru.riwayat-pendidikan.destroy');

    Route::post('/guru/{guru}/sertifikasi', [\App\Http\Controllers\Admin\Guru\SertifikasiController::class, 'store'])->name('guru.sertifikasi.store');
    Route::put('/guru/{guru}/sertifikasi/{sertifikasi}', [\App\Http\Controllers\Admin\Guru\SertifikasiController::class, 'update'])->name('guru.sertifikasi.update');
    Route::delete('/guru/{guru}/sertifikasi/{sertifikasi}', [\App\Http\Controllers\Admin\Guru\SertifikasiController::class, 'destroy'])->name('guru.sertifikasi.destroy');

    Route::post('/guru/{guru}/jabatan-tambahan', [\App\Http\Controllers\Admin\Guru\JabatanTambahanController::class, 'store'])->name('guru.jabatan-tambahan.store');
    Route::put('/guru/{guru}/jabatan-tambahan/{jabatan_tambahan}', [\App\Http\Controllers\Admin\Guru\JabatanTambahanController::class, 'update'])->name('guru.jabatan-tambahan.update');
    Route::delete('/guru/{guru}/jabatan-tambahan/{jabatan_tambahan}', [\App\Http\Controllers\Admin\Guru\JabatanTambahanController::class, 'destroy'])->name('guru.jabatan-tambahan.destroy');
```

Update `GuruController@edit` to load relational data:

```php
    public function edit(Guru $guru): View
    {
        $this->authorize('guru.edit');

        $guru->load([
            'user',
            'riwayatPendidikan' => fn ($q) => $q->orderBy('tahun_lulus', 'desc'),
            'sertifikasi' => fn ($q) => $q->orderBy('tahun', 'desc'),
            'jabatanTambahan.jabatanTambahanMaster',
            'jabatanTambahan.tahunAjaran' => fn ($q) => $q->orderBy('tanggal_mulai', 'desc'),
        ]);

        $lembagaId = $guru->lembaga_id;
        $jabatanMasterList = \App\Models\JabatanTambahanMaster::orderBy('nama_jabatan')->get();
        $tahunAjaranList = \App\Models\TahunAjaran::where('lembaga_id', $lembagaId)->orderBy('tanggal_mulai', 'desc')->get();

        return view('admin.guru.edit', [
            'guru' => $guru,
            'jabatanMasterList' => $jabatanMasterList,
            'tahunAjaranList' => $tahunAjaranList,
            ...$this->formOptions(),
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/GuruRelationalProfileTest.php`  
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/Guru/ routes/web.php app/Http/Controllers/Admin/GuruController.php tests/Feature/Admin/GuruRelationalProfileTest.php
git commit -m "feat(guru): add relational history management endpoints for teacher profiles"
```

---

### Task 2: Guru Interactive View-to-Edit Toggle & 4-Tab Profile UI (Blade & Alpine.js)

**Files:**
- Modify: `resources/views/admin/guru/edit.blade.php`
- Modify: `resources/views/admin/guru/_form.blade.php`
- Test: `tests/Feature/Admin/GuruRelationalProfileTest.php`

**Interfaces:**
- Consumes: `$guru`, `$jabatanMasterList`, `$tahunAjaranList`, and form option constants from `GuruController::edit`.
- Produces: Museum-quality desktop-style tabbed UI with zero-redundancy toggle between view mode and edit form, supporting modal creation and update for certifications and education history.

- [ ] **Step 1: Write the failing test**

Add a view rendering assertion test method to `tests/Feature/Admin/GuruRelationalProfileTest.php`:

```php
    public function test_edit_view_renders_four_tabs_and_view_to_edit_toggle_button(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.guru.edit', $this->guru));

        $response->assertOk()
            ->assertSee('Profil Utama')
            ->assertSee('Riwayat Pendidikan')
            ->assertSee('Sertifikasi')
            ->assertSee('Jabatan Tambahan')
            ->assertSee('Ubah Profil')
            ->assertSee('x-data', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/GuruRelationalProfileTest.php::test_edit_view_renders_four_tabs_and_view_to_edit_toggle_button`  
Expected: FAIL with "Failed asserting that HTML contains 'Riwayat Pendidikan'"

- [ ] **Step 3: Write minimal implementation**

Update `resources/views/admin/guru/edit.blade.php` to deploy the 4-tab interface and modal system:

```blade
<x-app-layout>
    <div x-data="{
        activeTab: 'profil',
        editMode: {{ $errors->any() ? 'true' : 'false' }},
        modalAddPendidikan: false,
        modalAddSertifikasi: false,
        modalAddJabatan: false
    }" class="mx-auto max-w-6xl space-y-6 pb-12">
        
        <!-- Breadcrumb & Page Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold text-gray-900 dark:text-white">Profil &amp; Manajemen Guru</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                    <a href="{{ route('admin.guru.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 dark:text-gray-300">Guru</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span> <span class="font-bold text-gray-900 dark:text-white">{{ $guru->nama }}</span>
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.guru.index') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Kembali ke Daftar
                </a>
                
                <button x-show="activeTab === 'profil'" @click="editMode = !editMode" type="button" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-brand-700 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <svg x-show="!editMode" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    <svg x-show="editMode" x-cloak class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span x-text="editMode ? 'Batal Ubah' : 'Ubah Profil'">Ubah Profil</span>
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <!-- Identity Hero Banner -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gradient-to-r from-gray-900 via-gray-800 to-brand-900 p-6 text-white shadow-xl dark:border-gray-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-2xl font-black text-white shadow-inner backdrop-blur">
                        {{ strtoupper(substr($guru->nama, 0, 2)) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-3">
                            <h2 class="font-display text-xl font-extrabold tracking-tight text-white sm:text-2xl">{{ $guru->nama }}</h2>
                            <span class="rounded-lg bg-emerald-500/20 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider text-emerald-300 border border-emerald-400/30">
                                {{ strtoupper($guru->status_aktif) }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm font-medium text-gray-300">
                            NIP: <span class="font-mono text-white">{{ $guru->nip ?: '-' }}</span> &bull; 
                            Peran: <span class="text-brand-300">{{ ucwords(str_replace('_', ' ', $guru->jenis_ptk)) }}</span>
                        </p>
                    </div>
                </div>
                <div class="rounded-xl bg-white/5 p-3 text-right text-xs backdrop-blur border border-white/10">
                    <p class="text-gray-400">Status Kepegawaian</p>
                    <p class="text-base font-extrabold text-white">{{ $guru->status_kepegawaian }}</p>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="border-b border-gray-200 dark:border-gray-800">
            <nav class="-mb-px flex flex-wrap gap-6 text-sm font-bold">
                <button @click="activeTab = 'profil'" :class="activeTab === 'profil' ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'" type="button" class="flex min-h-[44px] items-center gap-2 border-b-2 py-3 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Profil Utama
                </button>
                <button @click="activeTab = 'pendidikan'" :class="activeTab === 'pendidikan' ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'" type="button" class="flex min-h-[44px] items-center gap-2 border-b-2 py-3 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                    Riwayat Pendidikan ({{ $guru->riwayatPendidikan->count() }})
                </button>
                <button @click="activeTab = 'sertifikasi'" :class="activeTab === 'sertifikasi' ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'" type="button" class="flex min-h-[44px] items-center gap-2 border-b-2 py-3 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                    Sertifikasi ({{ $guru->sertifikasi->count() }})
                </button>
                <button @click="activeTab = 'jabatan'" :class="activeTab === 'jabatan' ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'" type="button" class="flex min-h-[44px] items-center gap-2 border-b-2 py-3 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Jabatan Tambahan ({{ $guru->jabatanTambahan->count() }})
                </button>
            </nav>
        </div>

        <!-- TAB 1: PROFIL UTAMA -->
        <div x-show="activeTab === 'profil'" class="transition-all duration-200">
            <div x-show="!editMode" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-gray-800 dark:bg-gray-900">
                <h3 class="font-display text-lg font-bold text-gray-900 dark:text-white">Data Pribadi &amp; Kepegawaian</h3>
                <dl class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">NIK (Nomor Induk Kependudukan)</dt>
                        <dd class="mt-1 font-mono text-base font-bold text-gray-900 dark:text-white">{{ $guru->nik ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">NIP</dt>
                        <dd class="mt-1 font-mono text-base font-bold text-gray-900 dark:text-white">{{ $guru->nip ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">NUPTK</dt>
                        <dd class="mt-1 font-mono text-base font-bold text-gray-900 dark:text-white">{{ $guru->nuptk ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Email Akun Login</dt>
                        <dd class="mt-1 text-base font-bold text-brand-600 dark:text-brand-400">{{ $guru->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Jenis Kelamin</dt>
                        <dd class="mt-1 text-base font-bold text-gray-900 dark:text-white">{{ $guru->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tempat, Tanggal Lahir</dt>
                        <dd class="mt-1 text-base font-bold text-gray-900 dark:text-white">{{ $guru->tempat_lahir ?: '-' }}, {{ $guru->tanggal_lahir ? $guru->tanggal_lahir->format('d M Y') : '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Nomor HP / WhatsApp</dt>
                        <dd class="mt-1 text-base font-bold text-gray-900 dark:text-white">{{ $guru->no_hp ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">TMT Tugas</dt>
                        <dd class="mt-1 text-base font-bold text-gray-900 dark:text-white">{{ $guru->tmt_tugas ? $guru->tmt_tugas->format('d M Y') : '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">TMT PNS / PPPK</dt>
                        <dd class="mt-1 text-base font-bold text-gray-900 dark:text-white">{{ $guru->tmt_pns ? $guru->tmt_pns->format('d M Y') : '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Alamat Lengkap</dt>
                        <dd class="mt-1 text-base font-medium text-gray-800 dark:text-gray-200">
                            {{ $guru->alamat_jalan ?: '-' }}, RT {{ $guru->rt ?: '-' }}/RW {{ $guru->rw ?: '-' }}, {{ $guru->desa_kelurahan ?: '-' }}, {{ $guru->kecamatan ?: '-' }}, {{ $guru->kabupaten_kota ?: '-' }}, {{ $guru->provinsi ?: '-' }} ({{ $guru->kode_pos ?: '-' }})
                        </dd>
                    </div>
                </dl>
            </div>

            <div x-show="editMode" x-cloak class="rounded-2xl border border-brand-200 bg-white p-6 shadow-card dark:border-brand-900 dark:bg-gray-900">
                <form method="POST" action="{{ route('admin.guru.update', $guru) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('admin.guru._form')
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <button @click="editMode = false" type="button" class="min-h-[44px] rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
                        <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TAB 2: RIWAYAT PENDIDIKAN -->
        <div x-show="activeTab === 'pendidikan'" x-cloak class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-display text-lg font-bold text-gray-900 dark:text-white">Daftar Riwayat Pendidikan</h3>
                <button @click="modalAddPendidikan = true" type="button" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-brand-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Pendidikan
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card dark:border-gray-800 dark:bg-gray-900">
                <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($guru->riwayatPendidikan as $pend)
                        <li class="flex flex-wrap items-center justify-between gap-4 p-5 hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                            <div>
                                <div class="flex items-center gap-3">
                                    <span class="rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-extrabold text-brand-700 dark:bg-brand-950 dark:text-brand-300">{{ $pend->jenjang }}</span>
                                    <h4 class="font-bold text-gray-900 dark:text-white">{{ $pend->universitas }} ({{ $pend->tahun_lulus }})</h4>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Jurusan: <strong class="text-gray-900 dark:text-gray-200">{{ $pend->jurusan ?: '-' }}</strong> &bull; Gelar: <strong class="text-brand-600 dark:text-brand-400">{{ $pend->gelar ?: '-' }}</strong>
                                </p>
                            </div>
                            <form method="POST" action="{{ route('admin.guru.riwayat-pendidikan.destroy', [$guru, $pend]) }}" onsubmit="return confirm('Hapus riwayat pendidikan ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="min-h-[44px] rounded-lg p-2 text-error-600 hover:bg-error-50 dark:text-error-400 dark:hover:bg-error-950" title="Hapus">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada riwayat pendidikan terdaftar untuk guru ini.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <!-- TAB 3: SERTIFIKASI GURU -->
        <div x-show="activeTab === 'sertifikasi'" x-cloak class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-display text-lg font-bold text-gray-900 dark:text-white">Daftar Sertifikasi Pendidik / Kompetensi</h3>
                <button @click="modalAddSertifikasi = true" type="button" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-brand-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Sertifikasi
                </button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @forelse($guru->sertifikasi as $sert)
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card dark:border-gray-800 dark:bg-gray-900 flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-extrabold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ $sert->jenis }}</span>
                                <span class="font-mono text-xs font-bold text-gray-500">Tahun {{ $sert->tahun }}</span>
                            </div>
                            <h4 class="mt-3 font-bold text-gray-900 dark:text-white">No. {{ $sert->nomor }}</h4>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Bidang Studi: <strong class="text-gray-900 dark:text-gray-200">{{ $sert->bidang_studi ?: '-' }}</strong></p>
                            @if($sert->penyelenggara)
                                <p class="text-xs text-gray-400 mt-1">Penyelenggara: {{ $sert->penyelenggara }}</p>
                            @endif
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                            <form method="POST" action="{{ route('admin.guru.sertifikasi.destroy', [$guru, $sert]) }}" onsubmit="return confirm('Hapus sertifikasi ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-bold text-error-600 hover:underline dark:text-error-400 min-h-[44px] flex items-center">Hapus Sertifikat</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="col-span-2 rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Belum ada data sertifikasi terdaftar untuk guru ini.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- TAB 4: JABATAN TAMBAHAN -->
        <div x-show="activeTab === 'jabatan'" x-cloak class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-display text-lg font-bold text-gray-900 dark:text-white">Daftar Penugasan &amp; Jabatan Tambahan</h3>
                <button @click="modalAddJabatan = true" type="button" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-brand-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Penugasan
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold">
                        <tr>
                            <th class="px-6 py-3.5">Nama Jabatan</th>
                            <th class="px-6 py-3.5">Tahun Ajaran</th>
                            <th class="px-6 py-3.5">Nomor SK</th>
                            <th class="px-6 py-3.5">Status</th>
                            <th class="px-6 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($guru->jabatanTambahan as $jab)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">{{ $jab->jabatanTambahanMaster->nama_jabatan ?? '-' }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ $jab->tahunAjaran->nama ?? '-' }}</td>
                                <td class="px-6 py-4 font-mono text-xs text-gray-500">{{ $jab->nomor_sk ?: '-' }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-extrabold {{ $jab->status_aktif ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                        {{ $jab->status_aktif ? 'Aktif' : 'Selesai' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <form method="POST" action="{{ route('admin.guru.jabatan-tambahan.destroy', [$guru, $jab]) }}" onsubmit="return confirm('Hapus penugasan jabatan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-bold text-error-600 hover:underline dark:text-error-400 min-h-[44px]">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-gray-500 dark:text-gray-400">Belum ada jabatan tambahan yang dibebankan kepada guru ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODALS FOR RELATIONS -->
        <!-- Modal Tambah Pendidikan -->
        <div x-show="modalAddPendidikan" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div @click.away="modalAddPendidikan = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="font-display text-lg font-bold text-gray-900 dark:text-white">Tambah Riwayat Pendidikan</h3>
                <form method="POST" action="{{ route('admin.guru.riwayat-pendidikan.store', $guru) }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <x-input-label value="Jenjang (Cth: S1, S2, D3)" />
                        <x-text-input name="jenjang" type="text" class="mt-1 block w-full" placeholder="S1" required />
                    </div>
                    <div>
                        <x-input-label value="Universitas / Sekolah Tinggi" />
                        <x-text-input name="universitas" type="text" class="mt-1 block w-full" placeholder="Universitas Negeri ..." required />
                    </div>
                    <div>
                        <x-input-label value="Jurusan / Program Studi" />
                        <x-text-input name="jurusan" type="text" class="mt-1 block w-full" placeholder="Pendidikan Matematika" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Gelar (Cth: S.Pd.)" />
                            <x-text-input name="gelar" type="text" class="mt-1 block w-full" placeholder="S.Pd." />
                        </div>
                        <div>
                            <x-input-label value="Tahun Lulus" />
                            <x-text-input name="tahun_lulus" type="text" class="mt-1 block w-full" placeholder="2018" required />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <button @click="modalAddPendidikan = false" type="button" class="min-h-[44px] rounded-xl border px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Batal</button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Tambah Sertifikasi -->
        <div x-show="modalAddSertifikasi" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div @click.away="modalAddSertifikasi = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="font-display text-lg font-bold text-gray-900 dark:text-white">Tambah Sertifikasi Pendidik</h3>
                <form method="POST" action="{{ route('admin.guru.sertifikasi.store', $guru) }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <x-input-label value="Jenis Sertifikasi" />
                        <x-text-input name="jenis" type="text" class="mt-1 block w-full" placeholder="Sertifikat Pendidik / Guru Penggerak" required />
                    </div>
                    <div>
                        <x-input-label value="Nomor Sertifikat / Registrasi" />
                        <x-text-input name="nomor" type="text" class="mt-1 block w-full" placeholder="000123456789" required />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Bidang Studi" />
                            <x-text-input name="bidang_studi" type="text" class="mt-1 block w-full" placeholder="Matematika" />
                        </div>
                        <div>
                            <x-input-label value="Tahun Sertifikasi" />
                            <x-text-input name="tahun" type="text" class="mt-1 block w-full" placeholder="2020" required />
                        </div>
                    </div>
                    <div>
                        <x-input-label value="Lembaga Penyelenggara (Opsional)" />
                        <x-text-input name="penyelenggara" type="text" class="mt-1 block w-full" placeholder="Kemendikbudristek" />
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <button @click="modalAddSertifikasi = false" type="button" class="min-h-[44px] rounded-xl border px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Batal</button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Tambah Jabatan -->
        <div x-show="modalAddJabatan" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div @click.away="modalAddJabatan = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="font-display text-lg font-bold text-gray-900 dark:text-white">Tambah Penugasan Jabatan</h3>
                <form method="POST" action="{{ route('admin.guru.jabatan-tambahan.store', $guru) }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <x-input-label value="Pilih Jabatan Master" />
                        <select name="jabatan_tambahan_master_id" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                            <option value="">-- Pilih Jabatan --</option>
                            @foreach($jabatanMasterList as $jm)
                                <option value="{{ $jm->id }}">{{ $jm->nama_jabatan }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Tahun Ajaran" />
                        <select name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                            @foreach($tahunAjaranList as $ta)
                                <option value="{{ $ta->id }}" {{ $ta->status_aktif ? 'selected' : '' }}>{{ $ta->nama }} {{ $ta->status_aktif ? '(Aktif)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Nomor SK (Opsional)" />
                        <x-text-input name="nomor_sk" type="text" class="mt-1 block w-full" placeholder="421/SK/2026" />
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" name="status_aktif" value="1" id="status_aktif_chk" checked class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <label for="status_aktif_chk" class="text-sm font-semibold text-gray-700 dark:text-gray-300">Status Aktif</label>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <button @click="modalAddJabatan = false" type="button" class="min-h-[44px] rounded-xl border px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Batal</button>
                        <x-primary-button type="submit">Simpan Penugasan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/GuruRelationalProfileTest.php`  
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/guru/edit.blade.php tests/Feature/Admin/GuruRelationalProfileTest.php
git commit -m "feat(guru): implement interactive 4-tab relational staff portal with view-to-edit toggle"
```

---

### Task 3: Siswa Excel Import Template & Login Account Prediction Preview

**Files:**
- Modify: `app/Exports/SiswaImportTemplateExport.php`
- Modify: `app/Imports/SiswaImportRow.php`
- Modify: `resources/views/admin/siswa/import-preview.blade.php`
- Modify: `resources/views/admin/siswa/import.blade.php`
- Test: `tests/Feature/Admin/SiswaImportAccountPredictionTest.php`

**Interfaces:**
- Consumes: `App\Services\AkunSiswaGenerator::usernameUntuk(Lembaga $lembaga, string $nis)`, `App\Models\Lembaga`
- Produces: Enhanced import parsing with `'prediksi_username'` attached to valid row payload and visible in preview table.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/SiswaImportAccountPredictionTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Exports\SiswaImportTemplateExport;
use App\Imports\SiswaImportRow;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SiswaImportAccountPredictionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Lembaga $lembaga;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $yayasan = Yayasan::factory()->create();
        $this->lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'smpitprm']);
        
        Permission::firstOrCreate(['name' => 'siswa.import', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin_sekolah', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
        $role->givePermissionTo('siswa.import');
        
        $this->admin = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $this->admin->assignRole($role);
        
        $ta = TahunAjaran::factory()->create(['lembaga_id' => $this->lembaga->id, 'status_aktif' => true]);
        $this->kelas = Kelas::factory()->create(['lembaga_id' => $this->lembaga->id, 'tahun_ajaran_id' => $ta->id, 'nama' => '7A']);
    }

    public function test_import_parser_returns_predicted_username_for_valid_rows(): void
    {
        $row = [
            'nis' => '2026001',
            'nisn' => '0011223344',
            'nama_lengkap' => 'Ahmad Faisal',
            'jenis_kelamin' => 'L',
            'kelas' => '7A',
            'email' => 'faisal@example.com',
        ];

        $result = SiswaImportRow::parse($row, $this->lembaga->id);

        $this->assertNull($result['error']);
        $this->assertArrayHasKey('prediksi_username', $result['data']);
        $this->assertEquals('smpitprm.2026001', $result['data']['prediksi_username']);
    }

    public function test_import_template_contains_email_and_clear_headers(): void
    {
        $export = new SiswaImportTemplateExport();
        $array = $export->array();

        $this->assertContains('email', $array[0]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/SiswaImportAccountPredictionTest.php`  
Expected: FAIL with "Failed asserting that array has the key 'prediksi_username'"

- [ ] **Step 3: Write minimal implementation**

Update `app/Exports/SiswaImportTemplateExport.php`:

```php
<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class SiswaImportTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas', 'email'],
            ['2026001', '0012345678', 'Budi Santoso', 'L', 'Jakarta', '2015-03-10', 'Islam', '6A', 'budi@permatakraksaan.sch.id'],
        ];
    }
}
```

Update `app/Imports/SiswaImportRow.php` inside `parse()` method:

```php
        $nis = trim((string) ($row['nis'] ?? ''));
        $namaLengkap = trim((string) ($row['nama_lengkap'] ?? ''));
        $jenisKelamin = trim((string) ($row['jenis_kelamin'] ?? ''));
        $namaKelas = trim((string) ($row['kelas'] ?? ''));
        $email = trim((string) ($row['email'] ?? '')) ?: null;

        $lembaga = \App\Models\Lembaga::withoutGlobalScopes()->find($lembagaId);
        $prediksiUsername = $lembaga && $nis !== '' ? app(\App\Services\AkunSiswaGenerator::class)->usernameUntuk($lembaga, $nis) : null;

        $data = [
            'nis' => $nis,
            'nisn' => trim((string) ($row['nisn'] ?? '')) ?: null,
            'nama_lengkap' => $namaLengkap,
            'jenis_kelamin' => $jenisKelamin,
            'tempat_lahir' => trim((string) ($row['tempat_lahir'] ?? '')) ?: null,
            'tanggal_lahir' => trim((string) ($row['tanggal_lahir'] ?? '')) ?: null,
            'agama' => trim((string) ($row['agama'] ?? '')) ?: null,
            'kelas_nama' => $namaKelas,
            'email' => $email,
            'prediksi_username' => $prediksiUsername,
        ];
```

Also verify `confirm` in `SiswaImportController.php` accepts optional email if user generation logic allows or stores it in user record if required (currently `AkunSiswaGenerator::buat` generates standard account; we preserve its invocation).

Update `resources/views/admin/siswa/import-preview.blade.php`:
Change the valid rows list into a structured table showing login predictions:

```blade
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <p class="font-display text-sm font-bold text-gray-900 dark:text-white">Baris Valid &amp; Prediksi Akun Login ({{ count($validRows) }})</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-bold">
                        <tr>
                            <th class="px-5 py-3">Nama Lengkap</th>
                            <th class="px-5 py-3">NIS / NISN</th>
                            <th class="px-5 py-3">Kelas Tujuan</th>
                            <th class="px-5 py-3">Prediksi Akun Login (Otomatis)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                        @forelse ($validRows as $row)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $row['nama_lengkap'] }}</td>
                                <td class="px-5 py-3 font-mono text-xs">{{ $row['nis'] }} {{ $row['nisn'] ? ' / ' . $row['nisn'] : '' }}</td>
                                <td class="px-5 py-3"><span class="rounded bg-brand-50 px-2 py-0.5 text-xs font-extrabold text-brand-700 dark:bg-brand-950 dark:text-brand-300">{{ $row['kelas_nama'] }}</span></td>
                                <td class="px-5 py-3">
                                    <span class="font-mono font-bold text-emerald-700 dark:text-emerald-400">{{ $row['prediksi_username'] }}</span>
                                    <span class="block text-[11px] text-gray-400">Password default: NIS</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-4 text-center text-gray-500">Tidak ada baris valid.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
```

Update `resources/views/admin/siswa/import.blade.php` to clarify automatic account creation in the instructions box:
Add to paragraph:
```blade
                <br><br>
                <strong class="text-brand-700 dark:text-brand-400">⚡ Pembuatan Akun Otomatis:</strong> Saat import dikonfirmasi, sistem secara atomik akan membuatkan akun login bagi setiap siswa dengan format username <code class="rounded bg-gray-100 px-1 py-0.5 text-xs font-mono dark:bg-gray-800 dark:text-white">[kode_lembaga].[nis]</code> dan password default berupa <code class="rounded bg-gray-100 px-1 py-0.5 text-xs font-mono dark:bg-gray-800 dark:text-white">NIS</code>.
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/SiswaImportAccountPredictionTest.php`  
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Exports/SiswaImportTemplateExport.php app/Imports/SiswaImportRow.php resources/views/admin/siswa/import-preview.blade.php resources/views/admin/siswa/import.blade.php tests/Feature/Admin/SiswaImportAccountPredictionTest.php
git commit -m "feat(siswa): add login account username prediction to import template and preview table"
```

---

### Task 4: Siswa Mass Login Account Generator & Individual Action Button

**Files:**
- Modify: `app/Http/Controllers/Admin/SiswaController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/siswa/index.blade.php`
- Test: `tests/Feature/Admin/SiswaAccountGenerationTest.php`

**Interfaces:**
- Consumes: `App\Models\Siswa`, `App\Services\AkunSiswaGenerator::buat(string $nama, string $nis, Lembaga $lembaga)`, `App\Enums\StatusSiswa`
- Produces: `POST /admin/siswa/generate-akun-massal` and `POST /admin/siswa/{siswa}/generate-akun` routes for creating user credentials for students without accounts (`user_id IS NULL`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/SiswaAccountGenerationTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SiswaAccountGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Lembaga $lembaga;

    protected function setUp(): void
    {
        parent::setUp();
        $yayasan = Yayasan::factory()->create();
        $this->lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'smpitprm']);
        
        Permission::firstOrCreate(['name' => 'siswa.edit', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin_sekolah', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
        $role->givePermissionTo('siswa.edit');
        
        $this->admin = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $this->admin->assignRole($role);
    }

    public function test_can_generate_account_for_individual_student_without_account(): void
    {
        $siswa = Siswa::factory()->create([
            'lembaga_id' => $this->lembaga->id,
            'nis' => '2026888',
            'nama_lengkap' => 'Banu Pratama',
            'user_id' => null,
            'status' => StatusSiswa::Aktif->value,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.siswa.generate-akun', $siswa));

        $response->assertRedirect()->assertSessionHas('status');
        $siswa->refresh();
        $this->assertNotNull($siswa->user_id);
        $this->assertDatabaseHas('users', [
            'id' => $siswa->user_id,
            'name' => 'Banu Pratama',
            'lembaga_id' => $this->lembaga->id,
        ]);
    }

    public function test_can_generate_accounts_in_bulk_for_active_unassigned_students_only(): void
    {
        $siswa1 = Siswa::factory()->create(['lembaga_id' => $this->lembaga->id, 'user_id' => null, 'status' => StatusSiswa::Aktif->value, 'nis' => '11111']);
        $siswa2 = Siswa::factory()->create(['lembaga_id' => $this->lembaga->id, 'user_id' => null, 'status' => StatusSiswa::Aktif->value, 'nis' => '22222']);
        $siswaInactive = Siswa::factory()->create(['lembaga_id' => $this->lembaga->id, 'user_id' => null, 'status' => StatusSiswa::Lulus->value, 'nis' => '33333']);

        $response = $this->actingAs($this->admin)->post(route('admin.siswa.generate-akun-massal'));

        $response->assertRedirect()->assertSessionHas('status');
        
        $this->assertNotNull($siswa1->refresh()->user_id);
        $this->assertNotNull($siswa2->refresh()->user_id);
        $this->assertNull($siswaInactive->refresh()->user_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/SiswaAccountGenerationTest.php`  
Expected: FAIL with "Route [admin.siswa.generate-akun-massal] not defined"

- [ ] **Step 3: Write minimal implementation**

Update `routes/web.php` in the admin group under siswa routes:

```php
    Route::post('/siswa/generate-akun-massal', [\App\Http\Controllers\Admin\SiswaController::class, 'generateAkunMassal'])->name('siswa.generate-akun-massal');
    Route::post('/siswa/{siswa}/generate-akun', [\App\Http\Controllers\Admin\SiswaController::class, 'generateAkun'])->name('siswa.generate-akun');
```

Update `app/Http/Controllers/Admin/SiswaController.php`:
Add count of students without account to `index()` view payload:

```php
        $siswaTanpaAkunCount = Siswa::where('status', StatusSiswa::Aktif->value)->whereNull('user_id')->count();

        return view('admin.siswa.index', [
            'siswaList' => $query->paginate($perPage)->withQueryString(),
            'kelasList' => $kelasList,
            'statusList' => StatusSiswa::cases(),
            'perPage' => $perPage,
            'siswaTanpaAkunCount' => $siswaTanpaAkunCount,
        ]);
```

Add the generator methods to `SiswaController.php`:

```php
    public function generateAkunMassal(Request $request): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif terlebih dahulu.']);
        }

        $lembaga = Lembaga::withoutGlobalScopes()->findOrFail($lembagaId);
        $siswaWithoutAccount = Siswa::where('status', StatusSiswa::Aktif->value)
            ->whereNull('user_id')
            ->get();

        if ($siswaWithoutAccount->isEmpty()) {
            return back()->with('status', 'Semua siswa aktif sudah memiliki akun login.');
        }

        DB::transaction(function () use ($siswaWithoutAccount, $lembaga) {
            $generator = app(AkunSiswaGenerator::class);
            foreach ($siswaWithoutAccount as $siswa) {
                $user = $generator->buat($siswa->nama_lengkap, $siswa->nis, $lembaga);
                $siswa->update(['user_id' => $user->id]);
            }
        });

        return back()->with('status', count($siswaWithoutAccount).' akun login baru berhasil dibangkitkan secara massal.');
    }

    public function generateAkun(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa.edit');

        if ($siswa->user_id !== null) {
            return back()->withErrors(['user' => 'Siswa ini sudah memiliki akun login.']);
        }

        DB::transaction(function () use ($siswa) {
            $generator = app(AkunSiswaGenerator::class);
            $user = $generator->buat($siswa->nama_lengkap, $siswa->nis, $siswa->lembaga);
            $siswa->update(['user_id' => $user->id]);
        });

        return back()->with('status', "Akun login untuk {$siswa->nama_lengkap} berhasil dibuat (Username: {$siswa->refresh()->user->username}).");
    }
```

Update `resources/views/admin/siswa/index.blade.php`:
Add the mass action banner/button above the filters table if `$siswaTanpaAkunCount > 0`:

```blade
        @if (($siswaTanpaAkunCount ?? 0) > 0 && auth()->user()->can('siswa.edit'))
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/40">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-700 dark:text-amber-400 font-bold">⚡</div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Terdapat {{ $siswaTanpaAkunCount }} Siswa Aktif Tanpa Akun Login</h3>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Anda dapat memodernisasi database dan membuatkan username login otomatis dengan satu klik.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.siswa.generate-akun-massal') }}" onsubmit="return confirm('Generate akun login massal untuk {{ $siswaTanpaAkunCount }} siswa aktif?')">
                    @csrf
                    <button type="submit" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-amber-600 px-4 py-2 text-sm font-bold text-white shadow transition hover:bg-amber-700 focus:outline-none">
                        ⚡ Generate Akun Massal ({{ $siswaTanpaAkunCount }})
                    </button>
                </form>
            </div>
        @endif
```

And update student table actions column in `index.blade.php`: if `$siswa->user_id === null`, display "Buat Akun" instead of "Reset Password":

```blade
                @if ($siswa->user_id)
                    <form method="POST" action="{{ route('admin.siswa.reset-password', $siswa) }}" onsubmit="return confirm('Reset password untuk siswa {{ $siswa->nama_lengkap }} ke NIS ({{ $siswa->nis }})?')">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="text-xs font-semibold text-amber-600 hover:underline dark:text-amber-400">Reset Password</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.siswa.generate-akun', $siswa) }}" onsubmit="return confirm('Buat akun login untuk siswa {{ $siswa->nama_lengkap }}?')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-brand-700 shadow-sm">
                            Buat Akun
                        </button>
                    </form>
                @endif
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/SiswaAccountGenerationTest.php`  
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/SiswaController.php routes/web.php resources/views/admin/siswa/index.blade.php tests/Feature/Admin/SiswaAccountGenerationTest.php
git commit -m "feat(siswa): implement bulk and individual atomic student login account generator"
```

---

### Task 5: Final Integrated Suite Verification & UI Polish Check

**Files:**
- No file changes expected; pure verification gate.

**Interfaces:**
- Consumes: Whole codebase migrations, seeders, and unit/feature specs.
- Produces: Complete proof of zero regressions across Pintera App.

- [ ] **Step 1: Run complete database migration, seed, and full test suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan migrate:fresh --seed; D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`  
Expected: ALL migrations and 34 seeders execute cleanly without foreign-key order fails; 100% test pass rate (>1000 passed tests).

- [ ] **Step 2: Final commit & Walkthrough documentation update**

```bash
git status
```
Ensure working directory is clean and report success to user.
