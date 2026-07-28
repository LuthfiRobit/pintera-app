# Pola Jam Checkbox Multi-Hari Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Change the "Hari" input on the "Tambah Slot Jam Pelajaran" form (inside `admin/pola-jam/index.blade.php`) from a single-select dropdown to a checkbox group, so one submit creates the same slot (same urutan/jam_mulai/jam_selesai/label/is_pelajaran) across every checked day in one request instead of requiring one submit per day.

**Architecture:** A single task touching one Blade view and one controller method. `JamPelajaranController::store()` changes from creating exactly one `JamPelajaran` per request to looping over a validated `hari` array, creating one row per day that doesn't collide with an existing `(pola_jam_id, hari, urutan)` combination, and reporting which days succeeded vs. were skipped in the flash message.

**Tech Stack:** Laravel 12, Blade, Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-28-pola-jam-checkbox-multi-hari-design.md` — read this for the full rationale and the agreed collision-handling behavior.

## Global Constraints

- Only the create ("Tambah Slot") flow changes. `JamPelajaranController::edit()`/`update()`/`destroy()`, the Edit view (`admin/jam-pelajaran/edit.blade.php`), and the collision rule itself (`(pola_jam_id, hari, urutan)` must stay unique) are unchanged.
- No tenant-scoping/security behavior changes — `pola_jam_id` resolution (`PolaJam::find()` + `abort(404)`) stays exactly as-is.
- Follow the existing TailAdmin checkbox visual pattern already used in the same file for "Tautkan ke Kelas" (`h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500`, `flex items-center gap-2` label).
- Flash messages use the existing mechanisms already on this page: `session('status')` for success (green toast) and Laravel's validation error bag (`$errors->first()`, red toast) for failure — no new UI component needed.

---

### Task 1: Checkbox multi-hari on the Tambah Slot form

**Files:**
- Modify: `app/Http/Controllers/Admin/JamPelajaranController.php:17-43` (the `store()` method)
- Modify: `resources/views/admin/pola-jam/index.blade.php:93-123` (the "Tambah Slot" form's Hari field and surrounding grid)
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: `App\Enums\Hari` (existing, has `label()` and the built-in `from()`/`value` on a backed enum), `App\Models\JamPelajaran`, `App\Models\PolaJam` — all already used by this controller.
- Produces: no new public interface — `POST admin.jam-pelajaran.store` still redirects to `admin.pola-jam.index` on any success and uses `back()` on total failure, same as before. The request's `hari` field changes shape from a string to an array (`hari[]` from the checkbox group) — this is a breaking change to the form's own contract, but nothing else in the codebase submits to this route with the old scalar shape (verified: `admin/pola-jam/index.blade.php` is the only form posting to `admin.jam-pelajaran.store`).

- [ ] **Step 1: Write the failing tests**

Two existing tests in `tests/Feature/Admin/PolaJamCrudTest.php` still send `'hari' => 'senin'` (scalar) — update both to the new array shape. Replace:

```php
it('adds a jam pelajaran slot to an existing pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => 'senin',
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => '0',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('label', 'Upacara')->exists())->toBeTrue();
});
```

with:

```php
it('adds a jam pelajaran slot to an existing pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => ['senin'],
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => '0',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('label', 'Upacara')->exists())->toBeTrue();
});
```

Replace:

```php
it('rejects adding a jam pelajaran slot to another lembaga\'s pola jam', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsPolaJamManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $polaB->id,
        'hari' => 'senin',
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => '0',
    ])->assertNotFound();

    expect(JamPelajaran::where('pola_jam_id', $polaB->id)->where('label', 'Upacara')->exists())->toBeFalse();
});
```

with:

```php
it('rejects adding a jam pelajaran slot to another lembaga\'s pola jam', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsPolaJamManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $polaB->id,
        'hari' => ['senin'],
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => '0',
    ])->assertNotFound();

    expect(JamPelajaran::where('pola_jam_id', $polaB->id)->where('label', 'Upacara')->exists())->toBeFalse();
});
```

Then add 4 new tests, appended right after the two above (still inside `tests/Feature/Admin/PolaJamCrudTest.php`):

```php
it('adds a jam pelajaran slot to multiple hari at once from one submit', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => ['senin', 'rabu'],
        'urutan' => 1,
        'label' => 'Jam ke-1',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => '1',
    ]);

    $response->assertRedirect(route('admin.pola-jam.index'));
    $response->assertSessionHas('status', 'Slot berhasil ditambahkan untuk Senin dan Rabu.');

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('hari', 'senin')->where('label', 'Jam ke-1')->exists())->toBeTrue();
    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('hari', 'rabu')->where('label', 'Jam ke-1')->exists())->toBeTrue();
    expect(JamPelajaran::where('pola_jam_id', $pola->id)->count())->toBe(2);
});

it('skips a hari that already has a slot at the same urutan and reports it in the status message', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'selasa', 'urutan' => 1]);

    $response = $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => ['senin', 'selasa'],
        'urutan' => 1,
        'label' => 'Jam ke-1',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => '1',
    ]);

    $response->assertRedirect(route('admin.pola-jam.index'));
    $response->assertSessionHas('status', 'Slot berhasil ditambahkan untuk Senin. Selasa dilewati karena urutan ini sudah dipakai.');

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('hari', 'senin')->where('label', 'Jam ke-1')->exists())->toBeTrue();
    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('hari', 'selasa')->count())->toBe(1);
});

it('rejects the whole batch with an error when every checked hari already has a slot at that urutan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'selasa', 'urutan' => 1]);

    $response = $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => ['senin', 'selasa'],
        'urutan' => 1,
        'label' => 'Jam ke-1 Duplikat',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => '1',
    ]);

    $response->assertSessionHasErrors('hari');
    expect(JamPelajaran::where('label', 'Jam ke-1 Duplikat')->exists())->toBeFalse();
    expect(JamPelajaran::where('pola_jam_id', $pola->id)->count())->toBe(2);
});

it('rejects submitting the slot form with no hari checked', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => [],
        'urutan' => 1,
        'label' => 'Jam ke-1',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => '1',
    ])->assertSessionHasErrors('hari');

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php`
Expected: FAIL — the two updated tests fail because `hari` is now an array but the controller still expects a string (`in:senin,...` rejects an array, producing a validation error where a redirect was expected); the 4 new tests fail because the batch/message/skip behavior doesn't exist yet.

- [ ] **Step 3: Update the controller**

Replace `app/Http/Controllers/Admin/JamPelajaranController.php`'s `store()` method:

```php
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jam-pelajaran.create');

        $data = $request->validate([
            'pola_jam_id' => ['required', 'integer'],
            'hari' => ['required', 'array', 'min:1'],
            'hari.*' => ['in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'urutan' => ['required', 'integer', 'min:1'],
            'label' => ['required', 'string', 'max:255'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'is_pelajaran' => ['required', 'boolean'],
        ]);

        $polaJam = PolaJam::find($data['pola_jam_id']);
        if (! $polaJam) {
            abort(404);
        }

        $berhasil = [];
        $dilewati = [];

        foreach ($data['hari'] as $hari) {
            if ($this->tabrakanSlot($data['pola_jam_id'], $hari, $data['urutan'])) {
                $dilewati[] = $hari;
                continue;
            }

            JamPelajaran::create([...$data, 'hari' => $hari]);
            $berhasil[] = $hari;
        }

        if (empty($berhasil)) {
            return back()->withErrors([
                'hari' => 'Semua hari yang dipilih (' . $this->formatDaftarHari($data['hari']) . ') sudah punya slot di urutan ini — tidak ada yang ditambahkan.',
            ])->withInput();
        }

        $status = 'Slot berhasil ditambahkan untuk ' . $this->formatDaftarHari($berhasil) . '.';
        if (! empty($dilewati)) {
            $status .= ' ' . $this->formatDaftarHari($dilewati) . ' dilewati karena urutan ini sudah dipakai.';
        }

        return redirect()->route('admin.pola-jam.index')->with('status', $status);
    }
```

Add a new private helper method to the same class, right after `tabrakanSlot()`:

```php
    private function formatDaftarHari(array $nilaiHari): string
    {
        $label = collect($nilaiHari)->map(fn ($h) => Hari::from($h)->label())->all();

        if (count($label) === 1) {
            return $label[0];
        }

        $terakhir = array_pop($label);

        return implode(', ', $label) . ' dan ' . $terakhir;
    }
```

Add `use App\Enums\Hari;` to the top of the file's `use` block (alongside the existing `use App\Models\JamPelajaran;` / `use App\Models\PolaJam;`).

- [ ] **Step 4: Update the view**

In `resources/views/admin/pola-jam/index.blade.php`, replace the block from `{{-- Baris 1: Hari, Urutan, Jam Mulai, Jam Selesai, Label --}}` through the closing `</div>` of that grid (currently lines 93-123):

```blade
                            {{-- Baris 1: Hari, Urutan, Jam Mulai, Jam Selesai, Label --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-start">
                                <div class="lg:col-span-2">
                                    <x-input-label value="Hari" class="mb-1 text-sm text-gray-700" />
                                    <select name="hari" class="block w-full rounded-lg border-gray-200 py-2 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                        @foreach ($hariAktifPola as $hari)
                                            <option value="{{ $hari->value }}">{{ $hari->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="lg:col-span-2">
                                    <x-input-label value="Urutan" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="number" name="urutan" placeholder="Ke-" min="1" class="block w-full py-2 text-sm shadow-sm" />
                                </div>

                                <div class="lg:col-span-2">
                                    <x-input-label value="Jam Mulai" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="time" name="jam_mulai" class="block w-full py-2 font-mono text-sm shadow-sm" />
                                </div>

                                <div class="lg:col-span-2">
                                    <x-input-label value="Jam Selesai" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="time" name="jam_selesai" class="block w-full py-2 font-mono text-sm shadow-sm" />
                                </div>

                                <div class="lg:col-span-4">
                                    <x-input-label value="Label Slot" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="text" name="label" placeholder="mis. Jam ke-1 / Istirahat" class="block w-full py-2 text-sm shadow-sm" />
                                </div>
                            </div>
```

with:

```blade
                            {{-- Baris 1: Hari (checkbox, bisa pilih beberapa sekaligus) --}}
                            <div>
                                <x-input-label value="Hari" class="mb-2 text-sm text-gray-700" />
                                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                    @foreach ($hariAktifPola as $hari)
                                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 transition-colors hover:text-gray-900 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                name="hari[]"
                                                value="{{ $hari->value }}"
                                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                            >
                                            <span>{{ $hari->label() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Baris 2: Urutan, Jam Mulai, Jam Selesai, Label --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-start">
                                <div class="lg:col-span-3">
                                    <x-input-label value="Urutan" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="number" name="urutan" placeholder="Ke-" min="1" class="block w-full py-2 text-sm shadow-sm" />
                                </div>

                                <div class="lg:col-span-3">
                                    <x-input-label value="Jam Mulai" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="time" name="jam_mulai" class="block w-full py-2 font-mono text-sm shadow-sm" />
                                </div>

                                <div class="lg:col-span-3">
                                    <x-input-label value="Jam Selesai" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="time" name="jam_selesai" class="block w-full py-2 font-mono text-sm shadow-sm" />
                                </div>

                                <div class="lg:col-span-3">
                                    <x-input-label value="Label Slot" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="text" name="label" placeholder="mis. Jam ke-1 / Istirahat" class="block w-full py-2 text-sm shadow-sm" />
                                </div>
                            </div>
```

(The old "Baris 1"/"Baris 2" comment numbering below this block — the "Jenis Sesi & Tombol Tambah" row that currently follows — does not need renumbering; leave it as-is even though it will now visually be the 3rd row. Renaming it is cosmetic only and not required.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php`
Expected: PASS (all tests, including the 4 new ones and the 2 updated ones)

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: all tests pass — confirms nothing else in the codebase posts to `admin.jam-pelajaran.store` with the old scalar `hari` shape.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/JamPelajaranController.php resources/views/admin/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat: allow selecting multiple hari at once when adding a jam pelajaran slot"
```

---

## Plan Self-Review Notes

- **Spec coverage**: every section of the spec (UI change, validation change, batch-create loop, success/partial/failure messaging, edge cases, testing) maps to a concrete step above.
- **Type consistency**: `formatDaftarHari(array $nilaiHari): string` is used identically in both the failure branch (`$data['hari']`, the full checked list) and the success branch (`$berhasil`/`$dilewati`, subsets of it) — same input shape (array of `Hari` enum string values) in every call site.
- **No placeholders**: migration/controller/view code blocks are complete and literal; no "add validation" or "TBD" left for the implementer to invent.
