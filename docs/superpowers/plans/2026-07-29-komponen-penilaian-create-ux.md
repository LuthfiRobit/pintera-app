# Komponen Penilaian Create Page UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Tahun Ajaran selector to the Komponen Penilaian "Tambah TP" create page that cascades into the Semester dropdown (fixing the same year-ambiguity bug already fixed on the index and edit pages), and make all three dropdowns Tom Select.

**Architecture:** One task. `create()` gains a `tahun_ajaran_id`-aware query (defaulting to the active tahun ajaran, falling back to `old()` input after a validation failure), the view adds a Tahun Ajaran select feeding a new small JS module that reuses the ALREADY-SHIPPED `admin.komponen-penilaian.opsi` endpoint (built in the prior index-rework package) to repopulate the Semester select — no new backend routes.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tom Select, Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-29-komponen-penilaian-create-ux-design.md` — read this for full rationale.

## Global Constraints

- `store()` is NOT modified — the `tahun_ajaran_id` field is UI-only (there is no such column on `KomponenPenilaian`), and existing cross-lembaga validation (`mataPelajaran->lembaga_id !== $semester->lembaga_id`) already fully secures the real `semester_id`/`mata_pelajaran_id` that gets stored.
- No new routes — `admin.komponen-penilaian.opsi` already exists (returns `{semesterList: [{id, nama}]}` for a given `tahun_ajaran_id`, already tenant-safe via `TahunAjaran::find()` + `abort_if`).
- New JS follows this codebase's established convention: a factory function in `resources/js/<name>.js`, registered via `Alpine.data('<name>', <factory>)` in `resources/js/app.js`, Tom Select instantiated via `new TomSelect(el, {...})` inside an `initXSelect(el)` method called from Blade via `x-init`.
- Do not touch `index()`/`opsi()`/`edit()`/`update()`/`destroy()` or their views — those are already shipped and reviewed.

---

### Task 1: Tahun Ajaran cascading on the create page

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php` (`create()` only)
- Modify: `resources/views/admin/komponen-penilaian/create.blade.php`
- Create: `resources/js/komponen-penilaian-create.js`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Consumes: `admin.komponen-penilaian.opsi` (existing, GET, JSON `{semesterList: [{id, nama}]}`), `App\Models\TahunAjaran` (existing).
- Produces: `create()`'s view now also receives `tahunAjaranList`, `tahunAjaranId`, and `semesterList` (scoped to `tahunAjaranId`) — no change to what `store()` consumes or produces.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (at the end of the file):

```php
it('defaults to the active tahun ajaran on the create page when none is selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
});

it('only offers semester options belonging to the selected tahun ajaran on the create page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
});

it('shows the tahun ajaran select wired with Tom Select on the create page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'));

    $response->assertSee('komponenPenilaianCreateForm(', false);
    $response->assertSee('name="tahun_ajaran_id"', false);
});

it('preserves the selected tahun ajaran and semester after a validation failure on store', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->from(route('admin.komponen-penilaian.create'))->post(route('admin.komponen-penilaian.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'semester_id' => $semester->id,
        'mata_pelajaran_id' => $mapel->id,
    ])->assertSessionHasErrors('deskripsi');

    $followUp = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'));

    $followUp->assertSee('value="' . $tahunAjaran->id . '" selected', false);
    $followUp->assertSee('value="' . $semester->id . '" selected', false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: FAIL — `assertViewHas('tahunAjaranId', ...)`/`assertViewHas('semesterList', ...)` fail because `create()` doesn't pass those keys yet, and the markup/redisplay tests fail because the view has no Tahun Ajaran select yet.

- [ ] **Step 3: Update `create()`**

Replace `KomponenPenilaianController::create()`:

```php
    public function create(Request $request): View
    {
        $this->authorize('komponen-penilaian.kelola');

        $tahunAjaranId = old('tahun_ajaran_id', $request->query('tahun_ajaran_id'));
        if (! $tahunAjaranId) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        return view('admin.komponen-penilaian.create', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
        ]);
    }
```

- [ ] **Step 4: Rewrite the create view's field grid**

In `resources/views/admin/komponen-penilaian/create.blade.php`, replace the `<form>` opening tag:

```blade
            <form method="POST" action="{{ route('admin.komponen-penilaian.store') }}" class="p-6 space-y-6">
```

with:

```blade
            <form method="POST" action="{{ route('admin.komponen-penilaian.store') }}" class="p-6 space-y-6" x-data="komponenPenilaianCreateForm({ tahunAjaranId: @js($tahunAjaranId), opsiUrl: @js(route('admin.komponen-penilaian.opsi')) })">
```

Then replace this block (the `<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">` field pair):

```blade
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Mata Pelajaran *" />
                        <select 
                            name="mata_pelajaran_id" 
                            required
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Mata Pelajaran —</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id') == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Semester *" />
                        <select 
                            name="semester_id" 
                            required
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
                    </div>
                </div>
```

with:

```blade
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label value="Tahun Ajaran *" />
                        <select
                            name="tahun_ajaran_id"
                            required
                            x-ref="tahunAjaranSelect"
                            x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Tahun Ajaran —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Semester *" />
                        <select
                            name="semester_id"
                            required
                            x-ref="semesterSelect"
                            x-init="initSemesterSelect($refs.semesterSelect)"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Mata Pelajaran *" />
                        <select
                            name="mata_pelajaran_id"
                            required
                            x-ref="mataPelajaranSelect"
                            x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Mata Pelajaran —</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id') == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1" />
                    </div>
                </div>
```

- [ ] **Step 5: Create the JS module**

Create `resources/js/komponen-penilaian-create.js`:

```js
import TomSelect from 'tom-select';

export function komponenPenilaianCreateForm(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        opsiUrl: config.opsiUrl,
        semesterTomSelect: null,

        initTahunAjaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari tahun ajaran...',
                onChange: (value) => {
                    this.tahunAjaranId = value;
                    this.gantiTahunAjaran(value);
                },
            });
        },

        initSemesterSelect(el) {
            this.semesterTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari semester...',
            });

            if (!this.tahunAjaranId) {
                this.semesterTomSelect.disable();
            }
        },

        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
            });
        },

        async gantiTahunAjaran(tahunAjaranId) {
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.clearOptions();

            if (!tahunAjaranId) {
                this.semesterTomSelect?.disable();
                return;
            }

            this.semesterTomSelect?.enable();

            try {
                const url = new URL(this.opsiUrl, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', tahunAjaranId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
                } else {
                    json.semesterList.forEach((semester) => {
                        this.semesterTomSelect.addOption({ value: String(semester.id), text: semester.nama });
                    });
                    this.semesterTomSelect.refreshOptions(false);
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
            }
        },
    };
}
```

- [ ] **Step 6: Register the component in app.js**

In `resources/js/app.js`, add the import alongside the other Alpine component imports (right after `import { komponenPenilaianEditForm } from './komponen-penilaian-edit';`):

```js
import { komponenPenilaianCreateForm } from './komponen-penilaian-create';
```

And add the registration alongside the other `Alpine.data(...)` calls (right after `Alpine.data('komponenPenilaianEditForm', komponenPenilaianEditForm);`):

```js
Alpine.data('komponenPenilaianCreateForm', komponenPenilaianCreateForm);
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: PASS — all tests, including the 4 new ones.

- [ ] **Step 8: Build assets**

Run: `npm run build`
Expected: builds successfully with no errors.

- [ ] **Step 9: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php resources/views/admin/komponen-penilaian/create.blade.php resources/js/komponen-penilaian-create.js resources/js/app.js tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "feat: add tahun ajaran cascading and tom select to Komponen Penilaian create page"
```

---

## Plan Self-Review Notes

- **Spec coverage**: all 5 requirements map to this task — Tahun Ajaran select defaulting to active TA (Step 3-4), cascading Semester via the existing `opsi()` endpoint (Step 5), Mata Pelajaran Tom Select (Step 4-5), `old()`-based redisplay preservation (Step 3, tested in Step 1's 4th test), `store()` left untouched (verified: no changes to `store()` anywhere in this plan).
- **No placeholders**: every code block is complete and literal.
- **Type consistency**: `komponenPenilaianCreateForm(config)` matches the calling convention (`x-data="komponenPenilaianCreateForm({ tahunAjaranId: ..., opsiUrl: ... })"`) exactly, and mirrors the same `gantiTahunAjaran`/Tom Select method-naming pattern already used in `komponen-penilaian-filter.js` and `jadwal-pelajaran-filter.js` for consistency, without duplicating their list-reload logic (which doesn't apply to a single-submit create form).
