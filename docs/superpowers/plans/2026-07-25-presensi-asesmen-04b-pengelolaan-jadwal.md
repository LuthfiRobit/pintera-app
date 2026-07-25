# Tahap 4b — Pengelolaan Jadwal (Edit/Hapus/Validasi) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close gaps found while manually testing the shipped Tahap 4 (Jadwal & Jam Pelajaran) feature: Pola Jam and Jam Pelajaran have no edit/delete, a raw MySQL constraint-violation error leaks to the user when adding a duplicate slot, the Jadwal Pelajaran filters aren't scoped by Tahun Ajaran, and `store()` doesn't prevent two logically-impossible situations — a schedule entry mixing entities from different lembaga, and a teacher double-booked into two classes at the same time.

**Architecture:** Seven independent-ish slices, ordered so model-relation prerequisites land before the controllers that need them: (1) day-of-week filtering for Pola Jam's slot-creation form, (2) `PolaJam` edit/delete with a usage guard, (3) an "assign to Kelas" action on the Pola Jam screen, (4) `JamPelajaran` edit/delete with a usage guard and proper duplicate-slot validation, (5) cascading Tahun Ajaran→Semester→Kelas filters on the Jadwal Pelajaran screen, (6) cross-lembaga consistency validation, (7) duplicate-entry and guru-conflict validation.

**Tech Stack:** Laravel 12, Blade, Pest 4.

## Global Constraints

- Same conventions as Tahap 1-4: `casts()` method style, inline validation (no FormRequest classes), `AuthorizesRequests`, current TailAdmin Blade token set. Reference views for this plan: `resources/views/admin/pola-jam/index.blade.php` and `resources/views/admin/jadwal-pelajaran/index.blade.php` (both already on `main`, already in the correct style — mirror their exact card/form/button markup for any new UI in this plan, don't invent new patterns).
- **`PolaJam`, `Kelas`, `Guru`, `MataPelajaran`, `Semester`, `TahunAjaran` all use `BelongsToTenant`** — route-model-binding on any of these (`{polaJam}`, `{kelas}`, etc.) automatically applies `TenantScope`, so a cross-tenant ID 404s for free. Prefer route-model-binding over manual `Model::find($id)` wherever the ID comes from the URL rather than the request body.
- **`JamPelajaran` and `JadwalPelajaran` are NOT tenant-scoped themselves** (no `BelongsToTenant`, no `lembaga_id` column) — only scoped transitively through `pola_jam_id`/`kelas_id`. **This is the exact vulnerability class that produced two separate Critical findings in Tahap 4's own final review.** Any new controller action in this plan that resolves a `JamPelajaran` by ID **must** verify it belongs to the acting tenant by checking its `pola_jam_id` against a tenant-scoped `PolaJam` lookup (or, for route-bound `{jamPelajaran}`, by manually verifying `$jamPelajaran->polaJam` resolves — see Task 4's explicit guard) — never trust a bare `exists:jam_pelajaran,id` or an unguarded route-model-bound `{jamPelajaran}` parameter alone.
- **Destructive cascade risk, already present in the shipped schema**: `pola_jam → jam_pelajaran` is `cascadeOnDelete()`, and `jam_pelajaran → jadwal_pelajaran` is `cascadeOnDelete()` too. Deleting a `PolaJam` or a `JamPelajaran` with no application-level guard would silently wipe dependent rows through the DB with no warning. Tasks 2 and 4 in this plan exist specifically to add that guard **in the controller**, before any `delete()` call — never rely on the DB cascade as the safety mechanism, only as a last-resort integrity backstop.
- **Raw DB errors must never reach the user.** Both `JamPelajaranController::store()` and `JadwalPelajaranController::store()` currently let a unique-constraint violation escape as an unhandled `QueryException`, which Laravel's debug page renders as a raw SQL dump including the DB host/port/name. Every task in this plan that touches a `store()`/`update()` method with a DB-level unique constraint must add an explicit pre-check and return a friendly validation error instead — never let the insert/update be the first thing to notice the conflict.
- Day-of-week convention (already established in Tahap 3b): `Lembaga::$hari_libur_mingguan` uses **Carbon's native `dayOfWeek`** integers, **0=Sunday..6=Saturday** (NOT 0=Monday). `App\Enums\Hari`'s cases are Monday-first with no numeric backing. Any code converting between the two must use the same mapping already established in `resources/views/admin/pengaturan/akademik.blade.php`: `[1=>Senin, 2=>Selasa, 3=>Rabu, 4=>Kamis, 5=>Jumat, 6=>Sabtu, 0=>Minggu]`.
- No permission may be assigned to a role by hand — `permissions:sync` auto-discovers every new `$this->authorize('...')` string. Run it after each task that adds one and confirm the expected new permission appears.

---

### Task 1: `Hari::aktifDari()` + limit Pola Jam's slot-creation dropdown to active days

**Files:**
- Modify: `app/Enums/Hari.php`
- Modify: `app/Http/Controllers/Admin/PolaJamController.php`
- Modify: `resources/views/admin/pola-jam/index.blade.php`
- Test: `tests/Unit/Enums/HariTest.php` (extend existing file)

**Interfaces:**
- Consumes: `Lembaga::$hari_libur_mingguan` (existing, from an earlier phase).
- Produces: `Hari::aktifDari(array $hariLiburMingguan): array` — returns only the `Hari` cases NOT in the given off-days list, in `Hari::cases()`'s natural (Monday-first) order.

**Design note:** the filtering must be computed **per Pola Jam**, using that specific pola's own `lembaga` relation — not a single global "acting lembaga" resolved once in the controller. Reason: a yayasan-scoped user with no active lembaga selected sees `PolaJam` cards from *multiple* lembaga on the same index page (per `TenantScope`'s existing "show everything when no active lembaga" behavior), and each lembaga can have a different `hari_libur_mingguan`. Only the **create-new-slot** dropdown is filtered — the display of already-existing slots (even ones on a now-inactive day) must never be hidden, to avoid making historical/existing data disappear from the UI.

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Enums/HariTest.php`:

```php
it('returns only the active-day cases, excluding the given off-days', function () {
    // Off: Sunday (0) and Friday (5) — matches the pesantren example from Tahap 3b's design discussion
    $aktif = Hari::aktifDari([0, 5]);

    expect(array_column($aktif, 'value'))->toBe(['senin', 'selasa', 'rabu', 'kamis', 'sabtu']);
});

it('returns all 7 cases when no days are off', function () {
    expect(Hari::aktifDari([]))->toHaveCount(7);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Enums/HariTest.php`
Expected: FAIL — `aktifDari` does not exist yet.

- [ ] **Step 3: Add the method to the enum**

In `app/Enums/Hari.php`, add:

```php
public static function aktifDari(array $hariLiburMingguan): array
{
    $petaKeAngka = [
        self::Senin->value => 1,
        self::Selasa->value => 2,
        self::Rabu->value => 3,
        self::Kamis->value => 4,
        self::Jumat->value => 5,
        self::Sabtu->value => 6,
        self::Minggu->value => 0,
    ];

    return array_values(array_filter(
        self::cases(),
        fn (self $hari) => ! in_array($petaKeAngka[$hari->value], $hariLiburMingguan, true)
    ));
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Enums/HariTest.php`
Expected: PASS (all tests in the file, including the 2 new ones).

- [ ] **Step 5: Wire it into the Pola Jam index**

In `app/Http/Controllers/Admin/PolaJamController.php::index()`, eager-load `lembaga` alongside `jamPelajaran`:

```php
'polaJamList' => PolaJam::with(['jamPelajaran', 'lembaga'])->orderBy('nama')->get(),
```

In `resources/views/admin/pola-jam/index.blade.php`, inside the `@foreach ($polaJamList as $pola)` loop, compute the active-day list once near the top of each card:

```blade
@php $hariAktifPola = \App\Enums\Hari::aktifDari($pola->lembaga->hari_libur_mingguan ?? []); @endphp
```

Then change ONLY the slot-creation `<select name="hari">` (the one inside the `<form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" ...>` block) to iterate `$hariAktifPola` instead of `\App\Enums\Hari::cases()`. Leave the day-grouping display loop above it (`@foreach (\App\Enums\Hari::cases() as $hari)` — the one that renders existing slots) untouched, per the design note above.

- [ ] **Step 6: Manual verification**

Since no browser automation is set up in this environment, verify via authenticated `curl` (same approach as prior tahap): log in, view the Pola Jam page for a lembaga with a non-default `hari_libur_mingguan` (e.g. `[0, 5]`), confirm the slot-creation `<select name="hari">` only contains 5 `<option>` elements (Senin, Selasa, Rabu, Kamis, Sabtu) and not Jumat/Minggu.

- [ ] **Step 7: Run full suite and commit**

Run: `php artisan test`
Expected: all passing, no regressions.

```bash
git add app/Enums/Hari.php app/Http/Controllers/Admin/PolaJamController.php resources/views/admin/pola-jam/index.blade.php tests/Unit/Enums/HariTest.php
git commit -m "feat: limit Pola Jam's slot-creation day options to the lembaga's active days"
```

---

### Task 2: `PolaJam` edit/update/destroy with a usage guard

**Files:**
- Modify: `app/Models/PolaJam.php`
- Modify: `app/Models/JamPelajaran.php`
- Modify: `app/Http/Controllers/Admin/PolaJamController.php`
- Create: `resources/views/admin/pola-jam/edit.blade.php`
- Modify: `resources/views/admin/pola-jam/index.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php` (extend existing file)

**Interfaces:**
- Produces: `PolaJam::kelas(): HasMany` (inverse of `Kelas::polaJam()`, Tahap 4 Task 4), `JamPelajaran::jadwalPelajaran(): HasMany` (inverse of `JadwalPelajaran::jamPelajaran()`, Tahap 4 Task 5) — both are new relations neither model had before. Routes `admin.pola-jam.edit/update/destroy`, permissions `pola-jam.edit`, `pola-jam.delete`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/PolaJamCrudTest.php` (reuse the existing `actingAsPolaJamManager()`-style helper in the file, extending its permission list as needed per test):

```php
it('renames a pola jam via update', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga); // extend helper's permissions to include pola-jam.edit, or add a dedicated helper
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Lama']);

    $this->actingAs($manager)->put(route('admin.pola-jam.update', $pola), [
        'nama' => 'Baru',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect($pola->fresh()->nama)->toBe('Baru');
});

it('rejects editing another lembaga\'s pola jam with 404', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $this->actingAs($manager)->put(route('admin.pola-jam.update', $polaLain), [
        'nama' => 'Diubah Paksa',
    ])->assertNotFound();

    expect($polaLain->fresh()->nama)->not->toBe('Diubah Paksa');
});

it('deletes a pola jam that has no kelas assigned and no jam pelajaran with a jadwal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->delete(route('admin.pola-jam.destroy', $pola))
        ->assertRedirect(route('admin.pola-jam.index'));

    expect(PolaJam::find($pola->id))->toBeNull();
});

it('refuses to delete a pola jam that is assigned to a kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);

    $this->actingAs($manager)->delete(route('admin.pola-jam.destroy', $pola))
        ->assertSessionHasErrors();

    expect(PolaJam::find($pola->id))->not->toBeNull();
});

it('refuses to delete a pola jam whose jam pelajaran has a jadwal pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $this->actingAs($manager)->delete(route('admin.pola-jam.destroy', $pola))
        ->assertSessionHasErrors();

    expect(PolaJam::find($pola->id))->not->toBeNull();
});
```

Import whatever additional classes each new test needs at the top of the file (`TahunAjaran`, `Kelas`, `Semester`, `JamPelajaran`, `JadwalPelajaran`, `Guru`) — check what's already imported before adding duplicates.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php`
Expected: FAIL — routes/methods don't exist yet.

- [ ] **Step 3: Add the two new relations**

In `app/Models/PolaJam.php`, add:

```php
public function kelas(): HasMany
{
    return $this->hasMany(Kelas::class);
}
```

In `app/Models/JamPelajaran.php`, add (with the `HasMany` import):

```php
public function jadwalPelajaran(): HasMany
{
    return $this->hasMany(JadwalPelajaran::class);
}
```

- [ ] **Step 4: Add controller methods**

In `app/Http/Controllers/Admin/PolaJamController.php`, add:

```php
public function edit(PolaJam $polaJam): View
{
    $this->authorize('pola-jam.edit');

    return view('admin.pola-jam.edit', ['polaJam' => $polaJam]);
}

public function update(Request $request, PolaJam $polaJam): RedirectResponse
{
    $this->authorize('pola-jam.edit');

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255'],
    ]);

    $polaJam->update($data);

    return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil diperbarui.');
}

public function destroy(PolaJam $polaJam): RedirectResponse
{
    $this->authorize('pola-jam.delete');

    if ($polaJam->kelas()->exists()) {
        return back()->withErrors(['pola_jam' => 'Pola jam ini masih dipakai oleh satu atau lebih kelas — lepaskan dulu sebelum menghapus.']);
    }

    if ($polaJam->jamPelajaran()->whereHas('jadwalPelajaran')->exists()) {
        return back()->withErrors(['pola_jam' => 'Pola jam ini memiliki jam pelajaran yang sudah dipakai di Jadwal Pelajaran — hapus jadwalnya dulu sebelum menghapus pola jam ini.']);
    }

    $polaJam->delete();

    return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dihapus.');
}
```

Because `{polaJam}` is route-model-bound and `PolaJam` uses `BelongsToTenant`, a cross-tenant ID resolves to a 404 automatically before `edit()`/`update()`/`destroy()` ever runs — this is why the cross-tenant test in Step 1 expects `assertNotFound()` with **no manual ownership check needed in the controller body**. Confirm this is actually true (Laravel's implicit route-model binding does apply global scopes) rather than assuming — if the test fails with something other than a clean 404, investigate before adding a manual guard.

- [ ] **Step 5: Add routes**

In `routes/admin.php`, add after the existing `pola-jam` routes:

```php
Route::get('pola-jam/{polaJam}/edit', [PolaJamController::class, 'edit'])->name('pola-jam.edit');
Route::put('pola-jam/{polaJam}', [PolaJamController::class, 'update'])->name('pola-jam.update');
Route::delete('pola-jam/{polaJam}', [PolaJamController::class, 'destroy'])->name('pola-jam.destroy');
```

- [ ] **Step 6: Create the edit view**

Create `resources/views/admin/pola-jam/edit.blade.php`, mirroring `resources/views/admin/pola-jam/create.blade.php`'s structure exactly (breadcrumb header, `rounded-2xl border border-gray-200 bg-white p-5 shadow-card` card, `<x-input-label>`/`<x-text-input>`/`<x-input-error>`), but:
- Title "Edit Pola Jam" instead of "Tambah Pola Jam".
- Form `method="POST"` with a hidden `@method('PUT')` field, `action="{{ route('admin.pola-jam.update', $polaJam) }}"`.
- `<x-text-input>` pre-filled with `value="{{ old('nama', $polaJam->nama) }}"`.

- [ ] **Step 7: Add Edit/Delete actions to the index page**

In `resources/views/admin/pola-jam/index.blade.php`, inside each `$pola` card's header row (the `border-b border-gray-200 px-5 py-4` div currently showing just `{{ $pola->nama }}`), add, gated by permission:

```blade
<div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
    <p class="font-display text-sm font-bold text-gray-900">{{ $pola->nama }}</p>
    <div class="flex items-center gap-3">
        @can('pola-jam.edit')
            <a href="{{ route('admin.pola-jam.edit', $pola) }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">Edit</a>
        @endcan
        @can('pola-jam.delete')
            <form method="POST" action="{{ route('admin.pola-jam.destroy', $pola) }}" onsubmit="return confirm('Hapus pola jam ini?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
            </form>
        @endcan
    </div>
</div>
```

Also render `$errors->first()` somewhere visible near the top of the page (check whether the file already has a generic error banner — Task 6/7's sibling pages, e.g. `resources/views/admin/jadwal-pelajaran/index.blade.php`, may not need one since they didn't have failing redirects before; add one matching the `rounded-lg bg-error-50 p-4 text-sm text-error-700` pattern used elsewhere in this codebase, e.g. `resources/views/admin/pengaturan/akademik.blade.php`) so the delete-guard error message in Step 4 is actually visible to the user, not silently dropped.

- [ ] **Step 8: Sync permissions and run tests**

Run: `php artisan permissions:sync`
Expected: `Created permission: pola-jam.edit`, `Created permission: pola-jam.delete`.

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php`
Expected: PASS (all tests, old and new).

Run: `php artisan test`
Expected: full suite green, no regressions.

- [ ] **Step 9: Commit**

```bash
git add app/Models/PolaJam.php app/Models/JamPelajaran.php app/Http/Controllers/Admin/PolaJamController.php resources/views/admin/pola-jam/edit.blade.php resources/views/admin/pola-jam/index.blade.php routes/admin.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat: add Pola Jam edit/delete with a usage guard"
```

---

### Task 3: "Assign to Kelas" action on the Pola Jam screen

**Files:**
- Modify: `app/Http/Controllers/Admin/PolaJamController.php`
- Modify: `resources/views/admin/pola-jam/index.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php` (extend existing file)

**Interfaces:**
- Consumes: `App\Models\Kelas` (existing, tenant-scoped), `PolaJam::kelas()` (Task 2).
- Produces: route `admin.pola-jam.assign-kelas`, letting an admin set a `Kelas`'s `pola_jam_id` directly from the Pola Jam page instead of only from the Kelas edit form.

**Design note:** this action mutates a `Kelas`, not a `PolaJam` — gate it behind the existing `kelas.edit` permission (do not invent a new permission for what is semantically an edit of `Kelas`). Both route params are model-bound (`{polaJam}` and `{kelas}`), so both are automatically tenant-scoped by `BelongsToTenant`/`TenantScope` — no manual ownership check needed, same reasoning as Task 2 Step 4.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/PolaJamCrudTest.php`:

```php
it('assigns a pola jam to a kelas from the pola jam screen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    foreach (['pola-jam.view', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_pola_jam_assign', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['pola-jam.view', 'kelas.edit']);
    $manager->assignRole($role);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $this->actingAs($manager)->put(route('admin.pola-jam.assign-kelas', ['polaJam' => $pola, 'kelas' => $kelas]))
        ->assertRedirect(route('admin.pola-jam.index'));

    expect($kelas->fresh()->pola_jam_id)->toBe($pola->id);
});

it('rejects assigning a pola jam to another lembaga\'s kelas with 404', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    foreach (['pola-jam.view', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_pola_jam_assign_2', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['pola-jam.view', 'kelas.edit']);
    $manager->assignRole($role);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($manager)->put(route('admin.pola-jam.assign-kelas', ['polaJam' => $pola, 'kelas' => $kelasLain]))
        ->assertNotFound();

    expect($kelasLain->fresh()->pola_jam_id)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php`
Expected: FAIL — route doesn't exist yet.

- [ ] **Step 3: Add the controller method**

In `app/Http/Controllers/Admin/PolaJamController.php`, add:

```php
public function assignKelas(PolaJam $polaJam, Kelas $kelas): RedirectResponse
{
    $this->authorize('kelas.edit');

    $kelas->update(['pola_jam_id' => $polaJam->id]);

    return redirect()->route('admin.pola-jam.index')->with('status', "Pola jam berhasil ditautkan ke kelas {$kelas->nama}.");
}
```

Add `use App\Models\Kelas;` to the controller's imports.

- [ ] **Step 4: Add the route**

In `routes/admin.php`:

```php
Route::put('pola-jam/{polaJam}/assign-kelas/{kelas}', [PolaJamController::class, 'assignKelas'])->name('pola-jam.assign-kelas');
```

- [ ] **Step 5: Add the UI**

In `resources/views/admin/pola-jam/index.blade.php`, inside each `$pola` card, add a small inline form (below the slot-creation form, or in the header area next to Edit/Delete — pick whichever reads cleaner once Task 2's Edit/Delete links are in place) letting the admin pick a `Kelas` from a dropdown and submit to assign it. The controller needs a list of candidate `Kelas` — add `'kelasList' => Kelas::orderBy('nama')->get(),` to `PolaJamController::index()`'s view data, gated behind `@can('kelas.edit')` in the Blade so it's invisible to users who can't use it:

```blade
@can('kelas.edit')
    <form method="POST" action="{{ route('admin.pola-jam.assign-kelas', ['polaJam' => $pola, 'kelas' => '__KELAS__']) }}" class="flex flex-wrap items-center gap-2 border-t border-gray-100 px-5 py-3" x-data="{ kelasId: '' }" @submit.prevent="if (kelasId) $el.action = $el.action.replace('__KELAS__', kelasId); $el.submit()">
        @csrf
        @method('PUT')
        <span class="text-sm text-gray-600">Tautkan ke kelas:</span>
        <select x-model="kelasId" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Kelas —</option>
            @foreach ($kelasList as $kelasOpsi)
                <option value="{{ $kelasOpsi->id }}" @selected($kelasOpsi->pola_jam_id === $pola->id)>{{ $kelasOpsi->nama }}{{ $kelasOpsi->pola_jam_id && $kelasOpsi->pola_jam_id !== $pola->id ? ' (sudah pakai pola lain)' : '' }}</option>
            @endforeach
        </select>
        <x-primary-button type="submit">Tautkan</x-primary-button>
    </form>
@endcan
```

This is the one place in this plan that reaches for a tiny bit of Alpine (`x-data`/`@submit.prevent`) purely to substitute the selected `kelas_id` into the form's `action` URL before submitting, since the route needs `{kelas}` as a path segment, not a body field — this is a minimal, self-contained use, not a switch to the JSON/Alpine inline-CRUD pattern from Tahap 3b. If this feels awkward during implementation, an acceptable simpler alternative is a plain `<select onchange="this.form.action = this.form.action.replace('__KELAS__', this.value)">` with no Alpine at all — either is fine, prefer whichever is less code.

- [ ] **Step 6: Sync permissions, run tests, commit**

Run: `php artisan permissions:sync` — expect no NEW permission (this task reuses the existing `kelas.edit`, `pola-jam.view`).

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php` then the full suite `php artisan test`.

```bash
git add app/Http/Controllers/Admin/PolaJamController.php resources/views/admin/pola-jam/index.blade.php routes/admin.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat: add assign-to-kelas action on the Pola Jam screen"
```

---

### Task 4: `JamPelajaran` edit/update/destroy with duplicate-slot validation and a usage guard

**Files:**
- Modify: `app/Http/Controllers/Admin/JamPelajaranController.php`
- Create: `resources/views/admin/jam-pelajaran/edit.blade.php`
- Modify: `resources/views/admin/pola-jam/index.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JamPelajaranCrudTest.php` (new file — the existing `PolaJamCrudTest.php` covers `JamPelajaranController::store()` for historical reasons; this new file covers `edit`/`update`/`destroy` on the same controller. Do not duplicate `store()`'s existing coverage.)

**Interfaces:**
- Consumes: `JamPelajaran::jadwalPelajaran()` (Task 2).
- Produces: routes `admin.jam-pelajaran.edit/update/destroy`, permissions `jam-pelajaran.edit`, `jam-pelajaran.delete`.

**Security-critical — read before writing any code.** `JamPelajaran` is **not** tenant-scoped (see Global Constraints). A route-bound `{jamPelajaran}` parameter therefore resolves to ANY `JamPelajaran` row in the whole system, regardless of which lembaga the acting user belongs to — implicit route-model binding on this specific model does **not** give you a free tenant check the way it does for `PolaJam`/`Kelas`/etc. Every one of `edit()`, `update()`, `destroy()` **must** manually verify the resolved `$jamPelajaran->pola_jam_id` belongs to the acting user's own tenant before doing anything else — resolve it via `PolaJam::find($jamPelajaran->pola_jam_id)` (which IS tenant-scoped) and `abort(404)` when null, mirroring the exact pattern already used in `JadwalPelajaranController::store()`'s `jam_pelajaran_id` check.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/JamPelajaranCrudTest.php`:

```php
<?php

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsJamPelajaranManager(Lembaga $lembaga, array $permissions = ['jam-pelajaran.edit', 'jam-pelajaran.delete']): User
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_jam_pelajaran_'.$lembaga->id, 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->syncPermissions($permissions);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('updates a jam pelajaran slot', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'label' => 'Lama']);

    $this->actingAs($manager)->put(route('admin.jam-pelajaran.update', $jam), [
        'label' => 'Baru', 'jam_mulai' => '08:00', 'jam_selesai' => '08:35', 'is_pelajaran' => '1',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect($jam->fresh()->label)->toBe('Baru');
});

it('rejects an update that would collide with another slot on the same pola/hari/urutan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1]);
    $jamKedua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 2]);

    $this->actingAs($manager)->put(route('admin.jam-pelajaran.update', $jamKedua), [
        'label' => 'Coba Tabrak', 'jam_mulai' => '08:00', 'jam_selesai' => '08:35', 'is_pelajaran' => '1', 'urutan' => 1, 'hari' => 'senin',
    ])->assertSessionHasErrors();

    expect($jamKedua->fresh()->urutan)->toBe(2);
});

it('rejects editing another lembaga\'s jam pelajaran with 404', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id]);

    $this->actingAs($manager)->put(route('admin.jam-pelajaran.update', $jamLain), [
        'label' => 'Diubah Paksa', 'jam_mulai' => '08:00', 'jam_selesai' => '08:35', 'is_pelajaran' => '1',
    ])->assertNotFound();
});

it('deletes a jam pelajaran with no jadwal pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);

    $this->actingAs($manager)->delete(route('admin.jam-pelajaran.destroy', $jam))
        ->assertRedirect(route('admin.pola-jam.index'));

    expect(JamPelajaran::find($jam->id))->toBeNull();
});

it('refuses to delete a jam pelajaran that has a jadwal pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $this->actingAs($manager)->delete(route('admin.jam-pelajaran.destroy', $jam))
        ->assertSessionHasErrors();

    expect(JamPelajaran::find($jam->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JamPelajaranCrudTest.php`
Expected: FAIL — routes/methods don't exist yet.

- [ ] **Step 3: Add a shared duplicate-slot check, then the controller methods**

In `app/Http/Controllers/Admin/JamPelajaranController.php`, add a private helper and use it from BOTH `store()` (fixing the raw-SQL-error bug Task's own Global Constraints called out) and the new `update()`:

```php
private function tabrakanSlot(int $polaJamId, string $hari, int $urutan, ?int $kecualiId = null): bool
{
    return JamPelajaran::where('pola_jam_id', $polaJamId)
        ->where('hari', $hari)
        ->where('urutan', $urutan)
        ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
        ->exists();
}
```

Update `store()` to check this **before** `JamPelajaran::create($data)`:

```php
if ($this->tabrakanSlot($data['pola_jam_id'], $data['hari'], $data['urutan'])) {
    return back()->withErrors(['urutan' => 'Urutan ini sudah dipakai pada hari yang sama di pola jam ini.'])->withInput();
}
```

Add `edit()`, `update()`, `destroy()`:

```php
public function edit(JamPelajaran $jamPelajaran): View
{
    $this->authorize('jam-pelajaran.edit');

    if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
        abort(404);
    }

    return view('admin.jam-pelajaran.edit', ['jamPelajaran' => $jamPelajaran]);
}

public function update(Request $request, JamPelajaran $jamPelajaran): RedirectResponse
{
    $this->authorize('jam-pelajaran.edit');

    if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
        abort(404);
    }

    $data = $request->validate([
        'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
        'urutan' => ['required', 'integer', 'min:1'],
        'label' => ['required', 'string', 'max:255'],
        'jam_mulai' => ['required', 'date_format:H:i'],
        'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
        'is_pelajaran' => ['required', 'boolean'],
    ]);

    if ($this->tabrakanSlot($jamPelajaran->pola_jam_id, $data['hari'], $data['urutan'], $jamPelajaran->id)) {
        return back()->withErrors(['urutan' => 'Urutan ini sudah dipakai pada hari yang sama di pola jam ini.'])->withInput();
    }

    $jamPelajaran->update($data);

    return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil diperbarui.');
}

public function destroy(JamPelajaran $jamPelajaran): RedirectResponse
{
    $this->authorize('jam-pelajaran.delete');

    if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
        abort(404);
    }

    if ($jamPelajaran->jadwalPelajaran()->exists()) {
        return back()->withErrors(['jam_pelajaran' => 'Slot ini masih dipakai di Jadwal Pelajaran — hapus jadwalnya dulu sebelum menghapus slot ini.']);
    }

    $jamPelajaran->delete();

    return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil dihapus.');
}
```

Add `use App\Models\PolaJam;` and `use Illuminate\View\View;` to the imports if not already present (check the current file first).

- [ ] **Step 4: Add routes**

In `routes/admin.php`:

```php
Route::get('jam-pelajaran/{jamPelajaran}/edit', [JamPelajaranController::class, 'edit'])->name('jam-pelajaran.edit');
Route::put('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'update'])->name('jam-pelajaran.update');
Route::delete('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'destroy'])->name('jam-pelajaran.destroy');
```

- [ ] **Step 5: Create the edit view**

Create `resources/views/admin/jam-pelajaran/edit.blade.php` — a simple standalone page (breadcrumb header, single `rounded-2xl border border-gray-200 bg-white p-5 shadow-card` card) with the same fields as the inline add-slot form (`hari` select, `urutan`, `label`, `jam_mulai`, `jam_selesai`, `is_pelajaran` select), pre-filled from `$jamPelajaran`, posting `PUT` to `admin.jam-pelajaran.update`. Note: unlike the create form, this edit form does NOT need `pola_jam_id` as an input (it's immutable — a slot doesn't move between Pola Jam via edit) and does NOT need the Hari-Aktif filtering from Task 1 (editing an existing slot on a now-inactive day should still be possible, e.g. to fix its label).

- [ ] **Step 6: Add Edit/Delete actions to each slot in the Pola Jam index**

In `resources/views/admin/pola-jam/index.blade.php`, inside the `@foreach ($slotHariIni as $slot)` loop, add Edit/Delete links next to each slot's display line, gated by permission — same `<a>` + `<form onsubmit="confirm(...)">` pattern as Task 2 Step 7's Pola Jam-level actions.

- [ ] **Step 7: Sync permissions, run tests, commit**

Run: `php artisan permissions:sync`
Expected: `Created permission: jam-pelajaran.edit`, `Created permission: jam-pelajaran.delete`.

Run: `php artisan test tests/Feature/Admin/JamPelajaranCrudTest.php tests/Feature/Admin/PolaJamCrudTest.php` (the latter covers `store()`'s now-fixed duplicate-slot validation — re-run it to confirm no regression), then the full suite `php artisan test`.

```bash
git add app/Http/Controllers/Admin/JamPelajaranController.php resources/views/admin/jam-pelajaran/edit.blade.php resources/views/admin/pola-jam/index.blade.php routes/admin.php tests/Feature/Admin/JamPelajaranCrudTest.php
git commit -m "feat: add Jam Pelajaran edit/delete, fix raw SQL error on duplicate slot"
```

---

### Task 5: Cascading Tahun Ajaran → Semester → Kelas filters on Jadwal Pelajaran

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Modify: `resources/views/admin/jadwal-pelajaran/index.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (extend existing file)

**Interfaces:**
- Consumes: `App\Models\TahunAjaran` (existing, tenant-scoped), `Kelas::$tahun_ajaran_id`, `Semester::$tahun_ajaran_id` (both existing).
- Produces: `index()` now accepts and uses a `tahun_ajaran_id` query param; `kelasList`/`semesterList` are filtered by it instead of showing every kelas/semester across all years.

**Design note:** when `tahun_ajaran_id` is absent from the query string, default to the acting lembaga's active `TahunAjaran` (`status_aktif = true`) if one exists — this makes the common case ("show me this year's schedule") the zero-click default, matching the UX principle established in this session's design discussion. If no active `TahunAjaran` exists for the acting lembaga (or the acting user is yayasan-scoped viewing "all lembaga" with no single obvious lembaga), fall back to no default (empty selects, matching current behavior).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:

```php
it('only lists semester and kelas belonging to the selected tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taBaru->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
    $response->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasBaru->id) && ! $list->contains('id', $kelasLama->id));
});

it('defaults to the active tahun ajaran when none is selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelasAktif = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
    $response->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasAktif->id));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: FAIL — current `index()` doesn't filter by tahun ajaran at all.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/Admin/JadwalPelajaranController.php::index()`:

```php
public function index(Request $request): View
{
    $this->authorize('jadwal-pelajaran.kelola');

    $tahunAjaranId = $request->query('tahun_ajaran_id');
    if (! $tahunAjaranId) {
        $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
    }

    $kelasId = $request->query('kelas_id');
    $semesterId = $request->query('semester_id');

    return view('admin.jadwal-pelajaran.index', [
        'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
        'tahunAjaranId' => $tahunAjaranId,
        'kelasList' => $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect(),
        'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
        'jadwalList' => $kelasId && $semesterId
            ? JadwalPelajaran::with(['jamPelajaran', 'mataPelajaran', 'guru'])
                ->where('kelas_id', $kelasId)->where('semester_id', $semesterId)->get()
            : collect(),
        'kelasId' => $kelasId,
        'semesterId' => $semesterId,
    ]);
}
```

Add `use App\Models\TahunAjaran;` to the imports.

- [ ] **Step 4: Update the view**

In `resources/views/admin/jadwal-pelajaran/index.blade.php`, add a Tahun Ajaran `<select>` before the existing Kelas/Semester selects, and make all three selects auto-submit on change (so picking a Tahun Ajaran immediately reloads the page with narrowed Semester/Kelas options, without a separate "Tampilkan" click for that step):

```blade
<div>
    <x-input-label value="Tahun Ajaran" />
    <select name="tahun_ajaran_id" onchange="this.form.submit()" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">— Pilih Tahun Ajaran —</option>
        @foreach ($tahunAjaranList as $tahunAjaran)
            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
        @endforeach
    </select>
</div>
```

Keep the existing Kelas/Semester selects as they are (still submitted via the same form, still require the explicit "Tampilkan" click to load `jadwalList` — only the Tahun Ajaran change needs to auto-submit, since changing it invalidates the other two dropdowns' option lists).

- [ ] **Step 5: Run tests, then the full suite, and commit**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`, then `php artisan test`.

```bash
git add app/Http/Controllers/Admin/JadwalPelajaranController.php resources/views/admin/jadwal-pelajaran/index.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat: cascade Jadwal Pelajaran filters by Tahun Ajaran"
```

---

### Task 6: Cross-lembaga consistency validation on Jadwal Pelajaran

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (extend existing file)

**Interfaces:** none new — this task only adds a check inside the existing `store()`.

**Why this is needed (distinct from the already-fixed IDOR):** the Critical findings fixed earlier in Tahap 4 stopped a MALICIOUS lembaga-A user from referencing lembaga-B's data. This task addresses a different problem: a LEGITIMATE yayasan-scoped user with no active lembaga selected can see and reference entities across *every* lembaga at once (by design — that's the existing, intentional "view all" behavior for that role state). Nothing currently stops them from accidentally submitting a `kelas_id` from Lembaga A paired with a `guru_id` from Lembaga B in the same request — a data-integrity bug, not a security one.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:

```php
it('rejects a jadwal that mixes entities from different lembaga even for a yayasan-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $kelasA->update(['pola_jam_id' => $polaA->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]); // different lembaga than everything else

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_mix_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);
    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($role);
    // No active_lembaga_id in session — this yayasan-scoped user can see both lembaga A and B.

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => $jamA->id,
        'guru_id' => $guruB->id, // mismatched lembaga
        'semester_id' => $semesterA->id,
    ])->assertSessionHasErrors();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --filter="mixes entities"`
Expected: FAIL — currently no consistency check, so this request would succeed.

- [ ] **Step 3: Add the check**

In `app/Http/Controllers/Admin/JadwalPelajaranController.php::store()`, after all the individual existence/tenant checks (`$kelas`, `$guru`, `$semester`, `$mataPelajaran` if present) and before the `$jamPelajaran` lookup (which already implicitly ties to `$kelas->pola_jam_id`, so it doesn't need this check — but `guru`/`semester`/`mataPelajaran` do), add:

```php
if ($guru->lembaga_id !== $kelas->lembaga_id) {
    return back()->withErrors(['guru_id' => 'Guru harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
}

if ($semester->lembaga_id !== $kelas->lembaga_id) {
    return back()->withErrors(['semester_id' => 'Semester harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
}

if (isset($mataPelajaran) && $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
    return back()->withErrors(['mata_pelajaran_id' => 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
}
```

Confirm `Semester` actually has a `lembaga_id` column/attribute before relying on it — check `app/Models/Semester.php` and its migration; Tahap 4's implementer confirmed it auto-fills `lembaga_id` from `tahun_ajaran_id` via a `booted()` hook, so it should be directly comparable, but verify rather than assume.

- [ ] **Step 4: Run tests, full suite, commit**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`, then `php artisan test` (confirm the existing same-lembaga success-path tests still pass — this check must not reject legitimate same-lembaga submissions).

```bash
git add app/Http/Controllers/Admin/JadwalPelajaranController.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat: reject cross-lembaga entity mixing in Jadwal Pelajaran"
```

---

### Task 7: Duplicate-entry and guru-conflict validation on Jadwal Pelajaran

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (extend existing file)

**Interfaces:** none new — both checks live inside the existing `store()`.

**Two distinct problems, same task:**
1. `jadwal_pelajaran` has a DB unique constraint `(kelas_id, jam_pelajaran_id, semester_id)` that `store()` never pre-checks — same class of raw-SQL-leak bug as Task 4 fixed for `jam_pelajaran`.
2. Nothing stops the SAME guru from being assigned to TWO DIFFERENT classes at the same `jam_pelajaran_id` + `semester_id` — physically impossible (one teacher can't be in two places at once), and not caught by the unique constraint above (which is scoped per-kelas, not per-guru).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:

```php
it('shows a friendly error instead of a raw SQL error on a duplicate kelas/jam/semester entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ])->assertSessionHasErrors()->assertStatus(302); // never a 500

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});

it('rejects double-booking a guru into two classes at the same jam and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasDua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelasSatu->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasDua->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ])->assertSessionHasErrors('guru_id');

    expect(JadwalPelajaran::where('kelas_id', $kelasDua->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --filter="duplicate|double-booking"`
Expected: FAIL — the first hits a raw `QueryException` (500, not a clean redirect-with-errors); the second currently succeeds when it shouldn't.

- [ ] **Step 3: Add both checks**

In `app/Http/Controllers/Admin/JadwalPelajaranController.php::store()`, after Task 6's consistency checks and after the `$jamPelajaran` resolution, before `JadwalPelajaran::create($data)`:

```php
$duplikat = JadwalPelajaran::where('kelas_id', $data['kelas_id'])
    ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
    ->where('semester_id', $data['semester_id'])
    ->exists();
if ($duplikat) {
    return back()->withErrors(['jam_pelajaran_id' => 'Kelas ini sudah punya jadwal pada slot ini di semester yang sama.'])->withInput();
}

$guruBentrok = JadwalPelajaran::where('guru_id', $data['guru_id'])
    ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
    ->where('semester_id', $data['semester_id'])
    ->exists();
if ($guruBentrok) {
    return back()->withErrors(['guru_id' => 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.'])->withInput();
}
```

- [ ] **Step 4: Run tests, full suite, commit**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`, then `php artisan test` (confirm the pre-existing "creates a jadwal pelajaran entry" success-path test — which by construction doesn't collide with anything — still passes).

```bash
git add app/Http/Controllers/Admin/JadwalPelajaranController.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat: validate duplicate entries and guru scheduling conflicts in Jadwal Pelajaran"
```

---

## Plan Self-Review Notes

- **Judgment call — separate edit pages for `PolaJam` and `JamPelajaran`, not inline/Alpine editing.** The Pola Jam screen (Tahap 4 Task 6) is a plain server-rendered form page, not the JSON/Alpine inline-CRUD pattern from Tahap 3b's Pengaturan Akademik page. This plan keeps that same plain-form paradigm for consistency, even though inline editing might read nicer for a single time-slot — introducing a third UI interaction pattern into one already-large addendum was judged not worth it. Task 3's tiny bit of Alpine (substituting `{kelas}` into a form's `action` URL) is the one narrow exception, needed only because the route takes the kelas ID as a path segment.
- **Judgment call — "assign to Kelas" gated by `kelas.edit`, not a new `pola-jam.assign` permission.** The action mutates a `Kelas` row, not a `PolaJam` row — permission should match the resource actually being written, not the page the button happens to live on.
- **Judgment call — default Tahun Ajaran only when exactly one active one exists.** No attempt is made to guess a "best" Tahun Ajaran for a yayasan-scoped user with no active lembaga selected (where `TahunAjaran::where('status_aktif', true)` could match multiple rows across different lembaga) — the query naturally returns null/ambiguous in that case via `->value('id')` returning the first match, which is a reasonable, low-risk fallback (worst case: defaults to some lembaga's active year instead of none) but not perfectly scoped. Flagging so the implementer/reviewer is aware this is a known minor imprecision, not an oversight.
- **Every new mutating action in Tasks 2-4 either relies on `BelongsToTenant`'s automatic route-model-binding scope (safe, verified in Global Constraints) or manually re-derives tenant ownership through a `PolaJam::find()` lookup for the two models that aren't tenant-scoped themselves (`JamPelajaran`).** This is the third time this project has had to reason carefully about this exact boundary — if a future reviewer finds a spot in this plan where that reasoning doesn't actually hold up once real code is written, treat it as a blocking finding, not a nice-to-have.
