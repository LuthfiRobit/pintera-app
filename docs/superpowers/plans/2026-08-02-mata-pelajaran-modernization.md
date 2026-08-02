# Mata Pelajaran Modernization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modernize Modul Mata Pelajaran (Data Induk) with 3 Compact Horizontal KPI Statistic Tiles, Mobile-First AJAX Tom Select filtering without full-page reload, and Tom Select standard formatting on separate multi-input forms (`>3 inputs`).

**Architecture:** Adopt existing Pintera AJAX + Tom Select architecture (modeled after `Komponen Penilaian`). Extract table into a dedicated partial (`_daftar.blade.php`) served directly by `MataPelajaranController@index` when queried via AJAX. Bind frontend filters and form dropdowns using standalone Alpine JavaScript modules compiled via Vite.

**Tech Stack:** Laravel 11, Blade, Alpine.js, Tom Select, Tailwind CSS / Pintera Design Tokens, Vite, Pest / PHPUnit.

## Global Constraints
- **Form Input Threshold**: Forms with > 3 inputs must use dedicated separate page routes (`/create` and `/edit` via `_form.blade.php`).
- **Select Option Standard**: Every `<select>` dropdown must be enhanced using **Tom Select** (`tom-select`).
- **Responsive Layout**: Filter Card must follow Mobile-First design (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`) separated from Table Card.
- **Statistic Cards**: Use compact `<x-stat-tile>` elements positioned directly above the filter card.
- **Tenant Isolation**: All queries and KPI calculations must respect active lembaga tenant scopes.

---

### Task 1: Backend KPIs & AJAX Partial Response Support

**Files:**
- Modify: `tests/Feature/Admin/MataPelajaranCrudTest.php`
- Modify: `app/Http/Controllers/Admin/MataPelajaranController.php`

**Interfaces:**
- Consumes: `App\Models\MataPelajaran`, `App\Enums\TipeMataPelajaran`
- Produces: View variables (`$totalMapel`, `$countKurikulum`, `$countAspek`), AJAX partial rendering of `admin.mata-pelajaran._daftar`.

- [ ] **Step 1: Write the failing feature tests for KPI indicators and AJAX filtering**

Append the following automated tests to `tests/Feature/Admin/MataPelajaranCrudTest.php`:

```php
it('calculates executive KPI statistics accurately in index view', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'SD-01',
        'nama' => 'Matematika SD',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);
    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'PAUD-01',
        'nama' => 'Motorik Halus',
        'no_urut' => 2,
        'tipe' => TipeMataPelajaran::AspekPerkembangan->value,
        'kelompok' => null,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));
    $response->assertOk();
    $response->assertViewHas('totalMapel', 2);
    $response->assertViewHas('countKurikulum', 1);
    $response->assertViewHas('countAspek', 1);
});

it('returns only table partial view when requested via AJAX XMLHttpRequest', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'AJAX-01',
        'nama' => 'Mapel AJAX',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index', ['search' => 'AJAX']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();
    $response->assertViewIs('admin.mata-pelajaran._daftar');
    $response->assertSee('Mapel AJAX');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php`
Expected: FAIL with "View [admin.mata-pelajaran._daftar] not found" and/or undefined variables `$totalMapel`.

- [ ] **Step 3: Write minimal implementation in MataPelajaranController**

In `app/Http/Controllers/Admin/MataPelajaranController.php`, modify the `index` method:

```php
    public function index(Request $request): View
    {
        $this->authorize('mata-pelajaran.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = MataPelajaran::orderBy('no_urut')->orderBy('nama');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('kode', 'like', '%' . $search . '%');
            });
        }

        if ($tipe = $request->input('tipe')) {
            $query->where('tipe', $tipe);
        }

        if ($kelompok = $request->input('kelompok')) {
            $query->where('kelompok', $kelompok);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.mata-pelajaran._daftar', [
                'mataPelajaranList' => $paginated,
                'perPage'           => $perPage,
            ]);
        }

        return view('admin.mata-pelajaran.index', [
            'mataPelajaranList' => $paginated,
            'tipeList'          => TipeMataPelajaran::cases(),
            'kelompokList'      => KelompokMataPelajaran::cases(),
            'statusList'        => StatusMataPelajaran::cases(),
            'perPage'           => $perPage,
            'totalMapel'        => MataPelajaran::count(),
            'countKurikulum'    => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),
            'countAspek'        => MataPelajaran::where('tipe', TipeMataPelajaran::AspekPerkembangan->value)->count(),
        ]);
    }
```

- [ ] **Step 4: Create temporary draft `_daftar.blade.php` just to satisfy test or proceed directly to Task 2 to build full view, and run tests**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php`

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Admin/MataPelajaranCrudTest.php app/Http/Controllers/Admin/MataPelajaranController.php
git commit -m "feat(akademik): add kpi calculation and ajax partial rendering support to MataPelajaranController"
```

---

### Task 2: UI View Splitting & Modernization

**Files:**
- Create: `resources/views/admin/mata-pelajaran/_daftar.blade.php`
- Modify: `resources/views/admin/mata-pelajaran/index.blade.php`
- Modify: `resources/views/admin/mata-pelajaran/_form.blade.php`
- Test: `tests/Feature/Admin/MataPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `$mataPelajaranList`, `$totalMapel`, `$countKurikulum`, `$countAspek`, Alpine `mataPelajaranFilter`, Alpine `mataPelajaranForm`.
- Produces: Complete responsive UI with TomSelect hook directives (`x-ref`, `x-init`).

- [ ] **Step 1: Create table snippet file `_daftar.blade.php`**

Write `resources/views/admin/mata-pelajaran/_daftar.blade.php`:
```blade
<div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
    {{-- Minimalist Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Mata Pelajaran</p>
        <div class="flex items-center gap-2">
            <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
            <select
                id="per_page"
                x-model="perPage"
                @change="muatUlangDaftar()"
                class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500"
            >
                @foreach ([10, 20, 25, 50] as $n)
                    <option value="{{ $n }}" @selected(($perPage ?? 20) == $n)>{{ $n }} / hal</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
                    <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 text-center w-28">Aksi</th>
                    <th class="px-4 py-3 text-center w-20">No. Rapor</th>
                    <th class="px-4 py-3 w-32">Kode</th>
                    <th class="px-4 py-3">Nama Mata Pelajaran</th>
                    <th class="px-4 py-3">Tipe</th>
                    <th class="px-4 py-3">Kelompok</th>
                    <th class="px-5 py-3 text-center w-28">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($mataPelajaranList as $mapel)
                    <tr class="transition-colors hover:bg-gray-50/60">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3.5 text-center group-hover:bg-gray-50/60">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('admin.mata-pelajaran.edit', $mapel) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 hover:text-brand-600">
                                    Edit
                                </a>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-center font-mono text-xs font-bold text-gray-600">
                            {{ $mapel->no_urut }}
                        </td>
                        <td class="px-4 py-3.5 font-mono text-xs font-semibold text-brand-600">
                            {{ $mapel->kode }}
                        </td>
                        <td class="px-4 py-3.5 font-medium text-gray-900">
                            {{ $mapel->nama }}
                        </td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">
                            {{ $mapel->tipe->label() }}
                        </td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">
                            {{ $mapel->kelompok?->label() ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if ($mapel->status === \App\Enums\StatusMataPelajaran::Aktif)
                                <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700">Aktif</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colSpan="7" class="px-5 py-12 text-center text-gray-500">
                            <p class="text-sm">Belum ada mata pelajaran yang didaftarkan.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($mataPelajaranList->hasPages())
        <div class="border-t border-gray-200 px-5 py-3">
            {{ $mataPelajaranList->links('pagination.tailadmin') }}
        </div>
    @endif
</div>
```

- [ ] **Step 2: Modify index view `index.blade.php`**

Replace `resources/views/admin/mata-pelajaran/index.blade.php` with:
```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Mata Pelajaran</h1>
                <p class="mt-0.5 text-xs text-gray-500">Kelola daftar mata pelajaran dan aspek perkembangan untuk kurikulum lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Mata Pelajaran</b>
            </p>
        </div>

        {{-- KPI Compact Horizontal Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-stat-tile label="Total Mata Pelajaran" value="{{ $totalMapel ?? 0 }}" icon="menu_book" color="blue" class="p-4" />
            <x-stat-tile label="Kurikulum Mapel (SD-SMK)" value="{{ $countKurikulum ?? 0 }}" icon="school" color="green" class="p-4" />
            <x-stat-tile label="Aspek Perkembangan (PAUD/TK)" value="{{ $countAspek ?? 0 }}" icon="extension" color="amber" class="p-4" />
        </div>

        {{-- Interactive Filter & AJAX Table Container --}}
        <div
            class="space-y-4"
            x-data="mataPelajaranFilter({
                search: @js(request('search', '')),
                tipe: @js(request('tipe', '')),
                kelompok: @js(request('kelompok', '')),
                status: @js(request('status', '')),
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.mata-pelajaran.index')),
            })"
        >
            {{-- Filter Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data
                    </p>
                    <x-link-button href="{{ route('admin.mata-pelajaran.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Mata Pelajaran
                    </x-link-button>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Cari Kata Kunci</label>
                        <div class="relative">
                            <x-icon name="search" class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                x-model="search"
                                @input.debounce.400ms="muatUlangDaftar()"
                                placeholder="Nama atau Kode mapel..."
                                class="w-full rounded-lg border-gray-200 py-1.5 pl-8 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500"
                            >
                        </div>
                    </div>

                    {{-- Filter Tipe --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tipe Kurikulum</label>
                        <select x-ref="tipeSelect" x-init="initFilterSelect($refs.tipeSelect, 'tipe')" class="w-full rounded-lg border-gray-200 text-sm text-gray-700">
                            <option value="">Semua Tipe</option>
                            @foreach ($tipeList as $item)
                                <option value="{{ $item->value }}" @selected(request('tipe') === $item->value)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Kelompok --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kelompok Mapel</label>
                        <select x-ref="kelompokSelect" x-init="initFilterSelect($refs.kelompokSelect, 'kelompok')" class="w-full rounded-lg border-gray-200 text-sm text-gray-700">
                            <option value="">Semua Kelompok</option>
                            @foreach ($kelompokList as $item)
                                <option value="{{ $item->value }}" @selected(request('kelompok') === $item->value)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Status --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Status Keaktifan</label>
                        <select x-ref="statusSelect" x-init="initFilterSelect($refs.statusSelect, 'status')" class="w-full rounded-lg border-gray-200 text-sm text-gray-700">
                            <option value="">Semua Status</option>
                            @foreach ($statusList as $item)
                                <option value="{{ $item->value }}" @selected(request('status') === $item->value)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Table Wrapper --}}
            <div x-ref="daftarMataPelajaran">
                @include('admin.mata-pelajaran._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Modify form partial `_form.blade.php` to integrate Tom Select**

In `resources/views/admin/mata-pelajaran/_form.blade.php`, replace the outer container wrapper and dropdown select inputs to include `x-data="mataPelajaranForm()"` and `x-init="initSelect($el)"`:

```blade
@php
    $mataPelajaran = $mataPelajaran ?? null;
    $val = fn (string $field, $default = '') => old($field, $mataPelajaran?->$field instanceof \BackedEnum ? $mataPelajaran->$field->value : ($mataPelajaran?->$field ?? $default));
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" x-data="mataPelajaranForm()">
    {{-- Card Header --}}
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="description" class="h-4 w-4 text-brand-500" />
            Identitas & Klasifikasi Mata Pelajaran
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Sesuaikan kode, nama, nomor urut rapor, serta kelompok mata pelajaran berdasar standar Kemdikdasmen/Kemenag.</p>
    </div>

    {{-- Form Body (12-Column Grid) --}}
    <div class="p-6 space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            {{-- Kode --}}
            <div class="sm:col-span-3">
                <x-input-label value="Kode Mapel (EMIS/Dapodik)" />
                <x-text-input type="text" name="kode" value="{{ $val('kode') }}" placeholder="Misal: MTK-01, PAI-01" class="mt-1.5 w-full uppercase transition duration-150" />
                <x-input-error :messages="$errors->get('kode')" class="mt-1.5" />
            </div>

            {{-- Nama --}}
            <div class="sm:col-span-6">
                <x-input-label value="Nama Mata Pelajaran" />
                <x-text-input type="text" name="nama" value="{{ $val('nama') }}" placeholder="Contoh: Matematika, Fikih, Nilai Agama..." class="mt-1.5 w-full transition duration-150" />
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>

            {{-- No Urut --}}
            <div class="sm:col-span-3">
                <x-input-label value="No. Urut Rapor" />
                <x-text-input type="number" min="1" max="999" name="no_urut" value="{{ $val('no_urut', '1') }}" class="mt-1.5 w-full transition duration-150" />
                <x-input-error :messages="$errors->get('no_urut')" class="mt-1.5" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            {{-- Tipe --}}
            <div class="sm:col-span-4">
                <x-input-label value="Tipe Kurikulum" />
                <select name="tipe" x-ref="tipeInput" x-init="initSelect($refs.tipeInput)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="mapel" @selected($val('tipe', 'mapel') === 'mapel')>Mata Pelajaran (SD - SMK)</option>
                    <option value="aspek_perkembangan" @selected($val('tipe') === 'aspek_perkembangan')>Aspek Perkembangan (PAUD / TK)</option>
                </select>
                <x-input-error :messages="$errors->get('tipe')" class="mt-1.5" />
            </div>

            {{-- Kelompok --}}
            <div class="sm:col-span-5">
                <x-input-label value="Kelompok Mata Pelajaran" />
                <select name="kelompok" x-ref="kelompokInput" x-init="initSelect($refs.kelompokInput)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="" @selected($val('kelompok') === '')>-- Tanpa Kelompok / Aspek PAUD --</option>
                    @foreach (\App\Enums\KelompokMataPelajaran::cases() as $k)
                        <option value="{{ $k->value }}" @selected($val('kelompok') === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('kelompok')" class="mt-1.5" />
            </div>

            {{-- Status --}}
            <div class="sm:col-span-3">
                <x-input-label value="Status Keaktifan" />
                <select name="status" x-ref="statusInput" x-init="initSelect($refs.statusInput)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    @foreach (\App\Enums\StatusMataPelajaran::cases() as $s)
                        <option value="{{ $s->value }}" @selected($val('status', 'aktif') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Card Footer Action Bar --}}
    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.mata-pelajaran.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors duration-200 hover:bg-gray-200/50 hover:text-gray-900">
            Batal
        </a>
        <x-primary-button type="submit" class="shadow-sm transition-all duration-200 active:scale-[0.98]">
            {{ $submitText ?? 'Simpan Data' }}
        </x-primary-button>
    </div>
</div>
```

- [ ] **Step 4: Run test to verify views render cleanly**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php`
Expected: ALL PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/mata-pelajaran/
git commit -m "refactor(akademik): modernize mata-pelajaran index and create/edit forms with Tom Select hooks and compact KPIs"
```

---

### Task 3: JavaScript Modules & Vite Bundling

**Files:**
- Create: `resources/js/mata-pelajaran-filter.js`
- Create: `resources/js/mata-pelajaran-form.js`
- Modify: `resources/js/app.js`

**Interfaces:**
- Consumes: Tom Select module (`tom-select`), AJAX HTTP endpoints on `indexUrlBase`.
- Produces: Registered Alpine constructors (`mataPelajaranFilter`, `mataPelajaranForm`).

- [ ] **Step 1: Write filter JavaScript module `mata-pelajaran-filter.js`**

Create `resources/js/mata-pelajaran-filter.js`:
```javascript
import TomSelect from 'tom-select';

export function mataPelajaranFilter(config) {
    return {
        search: config.search ?? '',
        tipe: config.tipe ?? '',
        kelompok: config.kelompok ?? '',
        status: config.status ?? '',
        perPage: config.perPage ?? 20,
        indexUrlBase: config.indexUrlBase,
        tomSelects: {},

        initFilterSelect(el, fieldName) {
            this.tomSelects[fieldName] = new TomSelect(el, {
                maxItems: 1,
                create: false,
                allowEmptyOption: true,
                onChange: (value) => {
                    this[fieldName] = value;
                    this.muatUlangDaftar();
                },
            });
        },

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.tipe) url.searchParams.set('tipe', this.tipe);
                if (this.kelompok) url.searchParams.set('kelompok', this.kelompok);
                if (this.status) url.searchParams.set('status', this.status);
                if (this.perPage !== 20) url.searchParams.set('per_page', this.perPage);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar mata pelajaran.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl(url);
                this.$refs.daftarMataPelajaran.innerHTML = html;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar mata pelajaran.');
            }
        },

        perbaruiUrl(url) {
            window.history.pushState({}, '', url);
        },
    };
}
```

- [ ] **Step 2: Write form JavaScript module `mata-pelajaran-form.js`**

Create `resources/js/mata-pelajaran-form.js`:
```javascript
import TomSelect from 'tom-select';

export function mataPelajaranForm() {
    return {
        initSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                allowEmptyOption: true,
            });
        },
    };
}
```

- [ ] **Step 3: Register JavaScript modules in `app.js`**

In `resources/js/app.js`, import and register both constructors:
```javascript
import { mataPelajaranFilter } from './mata-pelajaran-filter';
import { mataPelajaranForm } from './mata-pelajaran-form';
...
Alpine.data('mataPelajaranFilter', mataPelajaranFilter);
Alpine.data('mataPelajaranForm', mataPelajaranForm);
```

- [ ] **Step 4: Build asset bundles and verify overall test suite**

Run: `npm run build`
Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php`
Expected: ALL PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/mata-pelajaran-filter.js resources/js/mata-pelajaran-form.js resources/js/app.js
git commit -m "feat(akademik): register alpine tom select modules for mata-pelajaran index and forms"
```

---
