# Pembatasan Jalur per Gelombang PPDB Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin optionally restrict which `jalur_ppdb` are offered to registrants for a specific `gelombang_ppdb`, instead of every active jalur in the tahun ajaran always being available on every gelombang.

**Architecture:** New many-to-many pivot table `gelombang_jalur` with no extra columns — its row count for a given gelombang IS the on/off switch (zero rows = unrestricted, matching today's behavior exactly; one or more rows = restricted to just those jalur). `PortalController::index()` (public SPMB entry) reads the pivot to decide whether to filter. `GelombangPpdbController` (admin) gains a `jalur_ids[]` checkbox input, validated against the gelombang's own tahun ajaran, synced to the pivot on save.

**Tech Stack:** Laravel 12 Eloquent `belongsToMany`, existing Pest test suite, existing Blade component set (`x-icon`, `x-badge`, `x-input-label`).

## Global Constraints

- Zero pivot rows for a gelombang = unrestricted (all `status_aktif = true` jalur in that tahun ajaran available) — this is the exact behavior that exists today; a request that never touches `jalur_ids` must produce identical results to before this plan.
- `jalur_ppdb.status_aktif = false` always hides a jalur from the public flow, regardless of pivot assignment — the kill-switch is never bypassed by a restriction.
- `jalur_ids` submitted values must be validated against the specific gelombang's own `tahun_ajaran_id` (not just "any jalur_ppdb row") to block cross-tenant tampering.
- No changes to `resources/views/admin/jalur-ppdb/**`, `app/Http/Controllers/Admin/SpmbPendaftaranController.php`, or `seleksi_ppdb` — explicitly out of scope per the spec.
- Every task ends with a separate commit.

---

## Task 1: Pivot table + model relations

**Files:**
- Create: `database/migrations/2026_07_18_100000_create_gelombang_jalur_table.php`
- Modify: `app/Models/GelombangPpdb.php`
- Modify: `app/Models/JalurPpdb.php`
- Test: `tests/Feature/GelombangJalurRestrictionTest.php` (new file, houses every test for this whole feature)

**Interfaces:**
- Produces: `GelombangPpdb::jalur(): BelongsToMany` (pivot `gelombang_jalur`, FK `gelombang_ppdb_id`/`jalur_ppdb_id`) and `JalurPpdb::gelombang(): BelongsToMany` (the inverse). Every later task attaches/detaches/queries through `$gelombang->jalur()`.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gelombang_jalur', function (Blueprint $table) {
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('jalur_ppdb_id')->constrained('jalur_ppdb')->cascadeOnDelete();
            $table->primary(['gelombang_ppdb_id', 'jalur_ppdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gelombang_jalur');
    }
};
```

Save this to `database/migrations/2026_07_18_100000_create_gelombang_jalur_table.php`.

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `2026_07_18_100000_create_gelombang_jalur_table ................ DONE` in the output, no errors.

- [ ] **Step 3: Add the `jalur()` relation to `GelombangPpdb`**

In `app/Models/GelombangPpdb.php`, add the import and method:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

(add alongside the existing `BelongsTo`/`HasMany` imports), then add this method after `tahunAjaran()`:

```php
    public function jalur(): BelongsToMany
    {
        return $this->belongsToMany(JalurPpdb::class, 'gelombang_jalur', 'gelombang_ppdb_id', 'jalur_ppdb_id');
    }
```

- [ ] **Step 4: Add the `gelombang()` relation to `JalurPpdb`**

In `app/Models/JalurPpdb.php`, add the same `BelongsToMany` import, then add this method after `tahunAjaran()`:

```php
    public function gelombang(): BelongsToMany
    {
        return $this->belongsToMany(GelombangPpdb::class, 'gelombang_jalur', 'jalur_ppdb_id', 'gelombang_ppdb_id');
    }
```

- [ ] **Step 5: Write the failing test for the relation**

Create `tests/Feature/GelombangJalurRestrictionTest.php`:

```php
<?php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function buatGelombangDenganDuaJalur(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalurReguler = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $jalurPrestasi = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);

    return [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang];
}

it('lets a gelombang be attached to specific jalur via the pivot', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();

    $gelombang->jalur()->attach($jalurReguler->id);

    expect($gelombang->jalur()->pluck('jalur_ppdb.id')->all())->toBe([$jalurReguler->id]);
    expect($jalurReguler->gelombang()->pluck('gelombang_ppdb.id')->all())->toBe([$gelombang->id]);
    expect($jalurPrestasi->gelombang()->count())->toBe(0);
});

it('has zero pivot rows for a gelombang by default (unrestricted)', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();

    expect($gelombang->jalur()->exists())->toBeFalse();
});
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: `2 passed`. (This step is GREEN-only, not RED-then-GREEN — the relation methods from Steps 3-4 already exist by the time this test file is written, since a migration+relation pair has no meaningful intermediate failing state to demonstrate.)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_18_100000_create_gelombang_jalur_table.php app/Models/GelombangPpdb.php app/Models/JalurPpdb.php tests/Feature/GelombangJalurRestrictionTest.php
git commit -m "feat: add gelombang_jalur pivot table and BelongsToMany relations"
```

---

## Task 2: Public registration flow respects the restriction

**Files:**
- Modify: `app/Http/Controllers/Spmb/PortalController.php:27-30`
- Test: `tests/Feature/GelombangJalurRestrictionTest.php` (append)

**Interfaces:**
- Consumes: `GelombangPpdb::jalur(): BelongsToMany` from Task 1.
- Produces: no new public interface — this task only changes `PortalController::index()`'s internal query. Task 4/5 don't depend on this task's internals, only on Task 1's relation.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/GelombangJalurRestrictionTest.php`:

```php
it('shows every active jalur to the public when the gelombang is unrestricted', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee('Reguler')
        ->assertSee('Prestasi');
});

it('shows only the assigned jalur to the public when the gelombang is restricted', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $gelombang->jalur()->attach($jalurReguler->id);

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee('Reguler')
        ->assertDontSee('Prestasi');
});

it('never shows an inactive jalur to the public even if explicitly assigned to the gelombang', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $jalurPrestasi->update(['status_aktif' => false]);
    $gelombang->jalur()->attach([$jalurReguler->id, $jalurPrestasi->id]);

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee('Reguler')
        ->assertDontSee('Prestasi');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: the two new "restricted" tests FAIL (both "Prestasi" and "Reguler" currently show regardless of pivot state, since `PortalController` doesn't consult the pivot yet). The "unrestricted" test and the inactive-jalur test may already pass by coincidence — that's fine, the point is the restricted-visibility behavior doesn't exist yet.

- [ ] **Step 3: Update `PortalController::index()`**

In `app/Http/Controllers/Spmb/PortalController.php`, replace:

```php
        $jalurList = JalurPpdb::where('tahun_ajaran_id', $gelombang->tahun_ajaran_id)
            ->where('status_aktif', true)
            ->orderBy('nama')
            ->get();
```

with:

```php
        $dibatasi = $gelombang->jalur()->exists();

        $jalurList = JalurPpdb::where('tahun_ajaran_id', $gelombang->tahun_ajaran_id)
            ->where('status_aktif', true)
            ->when($dibatasi, fn ($q) => $q->whereHas('gelombang', fn ($q2) => $q2->whereKey($gelombang->id)))
            ->orderBy('nama')
            ->get();
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: `5 passed` (the 2 from Task 1 plus these 3).

- [ ] **Step 5: Run the full SPMB portal entry suite to check for regressions**

Run: `php artisan test --filter=PortalEntryTest`
Expected: all pre-existing tests in that file still pass — they never attach anything to `$gelombang->jalur()`, so they all stay in the unrestricted branch, identical to before this task.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Spmb/PortalController.php tests/Feature/GelombangJalurRestrictionTest.php
git commit -m "feat: filter public jalur list by gelombang restriction when one is set"
```

---

## Task 3: Admin validation + pivot sync on save

**Files:**
- Modify: `app/Http/Controllers/Admin/GelombangPpdbController.php`
- Test: `tests/Feature/GelombangJalurRestrictionTest.php` (append)

**Interfaces:**
- Consumes: `GelombangPpdb::jalur(): BelongsToMany` from Task 1.
- Produces: `admin.gelombang-ppdb.store` and `admin.gelombang-ppdb.update` now accept an optional `jalur_ids` array field. Task 4 (the UI) renders checkboxes named `jalur_ids[]` that POST/PUT into this.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/GelombangJalurRestrictionTest.php`:

```php
it('rejects a jalur_id that belongs to a different tahun ajaran', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunLain = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    $jalurTahunLain = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLain->id, 'nama' => 'Reguler Lama']);

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 2',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 30,
        'jalur_ids' => [$jalurTahunLain->id],
    ])->assertSessionHasErrors('jalur_ids.0');

    expect(GelombangPpdb::where('nama', 'Gelombang 2')->exists())->toBeFalse();
});

it('syncs jalur_ids to the pivot on create', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombangLama] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 2',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 30,
        'jalur_ids' => [$jalurReguler->id],
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    $gelombangBaru = GelombangPpdb::where('nama', 'Gelombang 2')->firstOrFail();
    expect($gelombangBaru->jalur()->pluck('jalur_ppdb.id')->all())->toBe([$jalurReguler->id]);
});

it('creates an unrestricted gelombang when jalur_ids is omitted entirely', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombangLama] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 2',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 30,
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    $gelombangBaru = GelombangPpdb::where('nama', 'Gelombang 2')->firstOrFail();
    expect($gelombangBaru->jalur()->exists())->toBeFalse();
});

it('clears the pivot back to unrestricted when an update omits jalur_ids after it was previously restricted', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $gelombang->jalur()->attach($jalurReguler->id);
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->put(route('admin.gelombang-ppdb.update', $gelombang), [
        'nama' => $gelombang->nama,
        'tanggal_buka' => $gelombang->tanggal_buka->format('Y-m-d'),
        'tanggal_tutup' => $gelombang->tanggal_tutup->format('Y-m-d'),
        'kuota' => $gelombang->kuota,
        // jalur_ids omitted entirely, simulating every checkbox left unchecked
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    expect($gelombang->jalur()->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: the 4 new tests FAIL — `jalur_ids` isn't validated or persisted anywhere yet, so the cross-tahun-ajaran rejection never happens and the pivot never gets synced.

- [ ] **Step 3: Update `GelombangPpdbController::validated()`**

In `app/Http/Controllers/Admin/GelombangPpdbController.php`, add the import:

```php
use App\Models\JalurPpdb;
```

(already imported — verify it's present; it is, from the existing `use App\Models\JalurPpdb;` line). Replace the `validated()` method:

```php
    private function validated(Request $request, int $tahunAjaranId, ?GelombangPpdb $current = null): array
    {
        return $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('gelombang_ppdb', 'nama')
                    ->where(fn ($query) => $query->where('tahun_ajaran_id', $tahunAjaranId))
                    ->ignore($current?->id),
            ],
            'tanggal_buka' => ['required', 'date'],
            'tanggal_tutup' => ['required', 'date', 'after:tanggal_buka'],
            'kuota' => ['required', 'integer', 'min:1'],
            'jalur_ids' => ['nullable', 'array'],
            'jalur_ids.*' => [
                'integer',
                Rule::exists('jalur_ppdb', 'id')->where('tahun_ajaran_id', $tahunAjaranId),
            ],
        ]);
    }
```

- [ ] **Step 4: Sync the pivot in `store()`**

Replace the `store()` method:

```php
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('gelombang-ppdb.create');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah gelombang.'])->withInput();
        }

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->firstOrFail();
        $data = $this->validated($request, $tahunAjaranAktif->id);
        $jalurIds = $data['jalur_ids'] ?? [];
        unset($data['jalur_ids']);
        $data['tahun_ajaran_id'] = $tahunAjaranAktif->id;
        // BelongsToTenant only auto-fills lembaga_id when the acting user's
        // widestScopeLevel() === 'lembaga'. A yayasan-scoped actor with
        // manage-ppdb (via yayasan_super_admin) would otherwise leave this
        // NOT NULL column unset. The resolved active TahunAjaran's own
        // lembaga_id is authoritative for both scopes, so set it explicitly.
        $data['lembaga_id'] = $tahunAjaranAktif->lembaga_id;

        $gelombang = GelombangPpdb::create($data);
        $gelombang->jalur()->sync($jalurIds);

        return redirect()->route('admin.gelombang-ppdb.index')->with('status', 'Gelombang berhasil ditambahkan.');
    }
```

- [ ] **Step 5: Sync the pivot in `update()`**

Replace the `update()` method:

```php
    public function update(Request $request, GelombangPpdb $gelombangPpdb): RedirectResponse
    {
        $this->authorize('gelombang-ppdb.edit');

        $data = $this->validated($request, $gelombangPpdb->tahun_ajaran_id, $gelombangPpdb);
        $jalurIds = $data['jalur_ids'] ?? [];
        unset($data['jalur_ids']);

        $gelombangPpdb->update($data);
        $gelombangPpdb->jalur()->sync($jalurIds);

        return redirect()->route('admin.gelombang-ppdb.index')->with('status', 'Gelombang berhasil diperbarui.');
    }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: `9 passed`.

- [ ] **Step 7: Run the full GelombangPpdbTest suite to check for regressions**

Run: `php artisan test --filter=GelombangPpdbTest`
Expected: all pre-existing tests pass — none of them send `jalur_ids`, so `$jalurIds` resolves to `[]` and `sync([])` on a gelombang with no prior pivot rows is a no-op.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/GelombangPpdbController.php tests/Feature/GelombangJalurRestrictionTest.php
git commit -m "feat: validate and sync jalur_ids on gelombang create/update"
```

---

## Task 4: Admin form UI — "Batasi Jalur (Opsional)" checkboxes

**Files:**
- Modify: `app/Http/Controllers/Admin/GelombangPpdbController.php` (`create()` and `edit()` methods)
- Modify: `resources/views/admin/gelombang-ppdb/create.blade.php`
- Modify: `resources/views/admin/gelombang-ppdb/edit.blade.php`
- Test: `tests/Feature/GelombangJalurRestrictionTest.php` (append)

**Interfaces:**
- Consumes: `jalur_ids[]` field name from Task 3 (must match exactly, checkboxes POST/PUT into it).
- Produces: nothing further downstream — this is the last task that changes behavior; Task 5 only touches the index view.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/GelombangJalurRestrictionTest.php`:

```php
it('shows a checkbox per active jalur on the create form, none pre-checked', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.create'))
        ->assertOk()
        ->assertSee('Batasi Jalur')
        ->assertSee('Reguler')
        ->assertSee('Prestasi')
        ->assertSee('value="'.$jalurReguler->id.'"', false)
        ->assertDontSee('checked', false);
});

it('pre-checks only the jalur already assigned on the edit form', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $gelombang->jalur()->attach($jalurReguler->id);
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.gelombang-ppdb.edit', $gelombang));

    $response->assertOk();
    // The Reguler checkbox carries `checked`, Prestasi's does not — assert by
    // finding each input's own tag rather than a page-wide checked count,
    // since both inputs share surrounding markup.
    preg_match_all('/<input type="checkbox" name="jalur_ids\[\]" value="(\d+)"[^>]*>/', $response->getContent(), $matches, PREG_SET_ORDER);
    $checkedIds = collect($matches)->filter(fn ($m) => str_contains($m[0], 'checked'))->map(fn ($m) => (int) $m[1])->values()->all();

    expect($checkedIds)->toBe([$jalurReguler->id]);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: both new tests FAIL — the create/edit views don't render any "Batasi Jalur" section yet, and the controllers don't pass `jalurAktif`/`jalurTerpilih` to the views.

- [ ] **Step 3: Pass jalur data from `create()`**

In `app/Http/Controllers/Admin/GelombangPpdbController.php`, replace the `create()` method:

```php
    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('gelombang-ppdb.create');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return redirect()->route('admin.gelombang-ppdb.index')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah gelombang.']);
        }

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->firstOrFail();

        return view('admin.gelombang-ppdb.create', [
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'jalurAktif' => JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->where('status_aktif', true)->orderBy('nama')->get(),
        ]);
    }
```

- [ ] **Step 4: Pass jalur data from `edit()`**

Replace the `edit()` method:

```php
    public function edit(GelombangPpdb $gelombangPpdb): View
    {
        $this->authorize('gelombang-ppdb.edit');

        return view('admin.gelombang-ppdb.edit', [
            'gelombang' => $gelombangPpdb,
            'jalurAktif' => JalurPpdb::where('tahun_ajaran_id', $gelombangPpdb->tahun_ajaran_id)->where('status_aktif', true)->orderBy('nama')->get(),
            'jalurTerpilih' => $gelombangPpdb->jalur()->pluck('jalur_ppdb.id')->all(),
        ]);
    }
```

- [ ] **Step 5: Add the checkbox section to `create.blade.php`**

In `resources/views/admin/gelombang-ppdb/create.blade.php`, insert this new card between the closing `</div>` of the "Detail Gelombang" card (the one right after the `kuota` field's closing `</div>`, i.e. right before the outer `</div>` that closes `grid grid-cols-1...`) and the `<div class="mt-4 flex items-center gap-3">` submit-button row. The file currently reads (relevant excerpt):

```blade
                    <div>
                        <x-input-label value="Kuota" />
                        <x-text-input type="number" name="kuota" value="{{ old('kuota') }}" placeholder="Contoh: 40" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('kuota')" class="mt-1.5" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
```

Change it to:

```blade
                    <div>
                        <x-input-label value="Kuota" />
                        <x-text-input type="number" name="kuota" value="{{ old('kuota') }}" placeholder="Contoh: 40" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('kuota')" class="mt-1.5" />
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="signpost" class="h-[15px] w-[15px] text-gray-400" />
                    Batasi Jalur (Opsional)
                </p>
                <p class="mb-4 text-xs text-gray-500">Kosongkan semua supaya semua jalur aktif tersedia untuk gelombang ini. Centang jalur tertentu untuk membatasi hanya jalur itu yang bisa dipilih calon murid.</p>

                @if ($jalurAktif->isEmpty())
                    <p class="text-sm text-gray-500">
                        Belum ada jalur aktif di tahun ajaran ini. Tambahkan dulu di halaman
                        <a href="{{ route('admin.jalur-ppdb.index') }}" class="font-semibold text-brand-600 hover:underline">Jalur PPDB</a>.
                    </p>
                @else
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($jalurAktif as $jalur)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="jalur_ids[]" value="{{ $jalur->id }}" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(in_array($jalur->id, old('jalur_ids', [])))>
                                {{ $jalur->nama }}
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-4 flex items-center gap-3">
```

- [ ] **Step 6: Add the same checkbox section to `edit.blade.php`**

In `resources/views/admin/gelombang-ppdb/edit.blade.php`, the file currently reads (relevant excerpt):

```blade
                    <div>
                        <x-input-label value="Kuota" />
                        <x-text-input type="number" name="kuota" value="{{ old('kuota', $gelombang->kuota) }}" placeholder="Contoh: 40" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('kuota')" class="mt-1.5" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
```

Change it to:

```blade
                    <div>
                        <x-input-label value="Kuota" />
                        <x-text-input type="number" name="kuota" value="{{ old('kuota', $gelombang->kuota) }}" placeholder="Contoh: 40" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('kuota')" class="mt-1.5" />
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="signpost" class="h-[15px] w-[15px] text-gray-400" />
                    Batasi Jalur (Opsional)
                </p>
                <p class="mb-4 text-xs text-gray-500">Kosongkan semua supaya semua jalur aktif tersedia untuk gelombang ini. Centang jalur tertentu untuk membatasi hanya jalur itu yang bisa dipilih calon murid.</p>

                @if ($jalurAktif->isEmpty())
                    <p class="text-sm text-gray-500">
                        Belum ada jalur aktif di tahun ajaran ini. Tambahkan dulu di halaman
                        <a href="{{ route('admin.jalur-ppdb.index') }}" class="font-semibold text-brand-600 hover:underline">Jalur PPDB</a>.
                    </p>
                @else
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($jalurAktif as $jalur)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="jalur_ids[]" value="{{ $jalur->id }}" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(in_array($jalur->id, old('jalur_ids', $jalurTerpilih)))>
                                {{ $jalur->nama }}
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-4 flex items-center gap-3">
```

Note the one difference from the create view: `old('jalur_ids', [])` becomes `old('jalur_ids', $jalurTerpilih)` — edit pre-checks whatever's currently assigned, create never pre-checks anything.

- [ ] **Step 7: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: `Blade templates cached successfully.` then `Compiled views cleared successfully.` — no syntax errors.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: `11 passed`.

- [ ] **Step 9: Run the full GelombangPpdbTest suite to check for regressions**

Run: `php artisan test --filter=GelombangPpdbTest`
Expected: all pre-existing tests pass unchanged.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Admin/GelombangPpdbController.php resources/views/admin/gelombang-ppdb/create.blade.php resources/views/admin/gelombang-ppdb/edit.blade.php tests/Feature/GelombangJalurRestrictionTest.php
git commit -m "feat: add jalur restriction checkboxes to the gelombang create/edit forms"
```

---

## Task 5: Restriction-status badge on the index table

**Files:**
- Modify: `app/Http/Controllers/Admin/GelombangPpdbController.php:index()`
- Modify: `resources/views/admin/gelombang-ppdb/index.blade.php`
- Test: `tests/Feature/GelombangJalurRestrictionTest.php` (append)

**Interfaces:**
- Consumes: `GelombangPpdb::jalur()` from Task 1 via `withCount('jalur')` (produces a `jalur_count` attribute Laravel adds automatically to each model in the collection).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/GelombangJalurRestrictionTest.php`:

```php
it('shows a "Semua Jalur" badge for an unrestricted gelombang on the index', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertSee('Semua Jalur');
});

it('shows a "N Jalur Dibatasi" badge for a restricted gelombang on the index', function () {
    foreach (['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit']);

    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $gelombang->jalur()->attach($jalurReguler->id);
    $user = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertSee('1 Jalur Dibatasi');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: both new tests FAIL — the index table has no badge column yet.

- [ ] **Step 3: Eager-load the jalur count in `index()`**

In `app/Http/Controllers/Admin/GelombangPpdbController.php`, in the `index()` method, change:

```php
        $query = $tahunAjaranTerpilih
            ? GelombangPpdb::with('lembaga')->where('tahun_ajaran_id', $tahunAjaranTerpilih->id)
            : GelombangPpdb::whereRaw('1 = 0');
```

to:

```php
        $query = $tahunAjaranTerpilih
            ? GelombangPpdb::with('lembaga')->withCount('jalur')->where('tahun_ajaran_id', $tahunAjaranTerpilih->id)
            : GelombangPpdb::whereRaw('1 = 0');
```

- [ ] **Step 4: Add the badge to the index table**

In `resources/views/admin/gelombang-ppdb/index.blade.php`, add a new `<th>Jalur</th>` header and cell. Change:

```blade
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">Nama</th>
                                <th class="px-5 py-3">Tanggal Buka</th>
                                <th class="px-5 py-3">Tanggal Tutup</th>
                                <th class="px-5 py-3">Kuota</th>
                            </tr>
```

to:

```blade
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">Nama</th>
                                <th class="px-5 py-3">Tanggal Buka</th>
                                <th class="px-5 py-3">Tanggal Tutup</th>
                                <th class="px-5 py-3">Kuota</th>
                                <th class="px-5 py-3">Jalur</th>
                            </tr>
```

And change:

```blade
                                    <td class="px-5 py-3.5 font-mono text-gray-600">{{ $gelombang->kuota }}</td>
                                </tr>
```

to:

```blade
                                    <td class="px-5 py-3.5 font-mono text-gray-600">{{ $gelombang->kuota }}</td>
                                    <td class="px-5 py-3.5">
                                        @if ($gelombang->jalur_count > 0)
                                            <x-badge tone="brass">{{ $gelombang->jalur_count }} Jalur Dibatasi</x-badge>
                                        @else
                                            <x-badge tone="slate">Semua Jalur</x-badge>
                                        @endif
                                    </td>
                                </tr>
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: `13 passed`.

- [ ] **Step 6: Run the full GelombangPpdbTest suite to check for regressions**

Run: `php artisan test --filter=GelombangPpdbTest`
Expected: all pre-existing tests pass unchanged — none of them assert on the exact column count of the index table.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/GelombangPpdbController.php resources/views/admin/gelombang-ppdb/index.blade.php tests/Feature/GelombangJalurRestrictionTest.php
git commit -m "feat: show a restriction-status badge per gelombang on the index table"
```

---

## Task 6: Demo seeder for the restriction

**Files:**
- Create: `database/seeders/GelombangJalurSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/GelombangJalurRestrictionTest.php` (append)

**Interfaces:**
- Consumes: `GelombangPpdb::jalur(): BelongsToMany` from Task 1, and the `GelombangPpdbSeeder`/`JalurPpdbSeeder` demo fixtures (`Lembaga` npsn `20223344` = SMP, tahun ajaran with `status_aktif = true`, gelombang named `Gelombang 1`, jalur named `Reguler`/`Prestasi`/`Afirmasi`) already registered in `DatabaseSeeder`.
- Produces: nothing consumed by later tasks — this seeds local/demo data only, so a fresh `php artisan migrate:fresh --seed` shows the restriction feature already in action without manual setup.

`DatabaseSeeder` currently calls `GelombangPpdbSeeder::class` **before** `JalurPpdbSeeder::class` (see `database/seeders/DatabaseSeeder.php:33-34`), so gelombang rows exist before jalur rows do. This new seeder must run after both, so it needs its own entry appended at the end of the `$this->call([...])` list rather than being folded into either existing seeder.

The demo scenario: restrict SMP's currently-open `Gelombang 1` (in its active tahun ajaran) to `Reguler` + `Prestasi`, excluding `Afirmasi`. Because this gelombang is the one seeded to always be "open relative to today" (see `GelombangPpdbSeeder::seedGelombang` — `now()->subDays(5)` / `now()->addMonths(2)`), the restriction is immediately visible on the live public `/spmb/{slug}` entry right after seeding, with no manual date tinkering needed. SMA's gelombang and SMP's old (lama) tahun ajaran gelombang are left untouched (unrestricted), so the index shows both badge states side by side.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/GelombangJalurRestrictionTest.php`:

```php
it('seeds a restricted demo gelombang for SMP alongside unrestricted ones', function () {
    $this->seed();

    $smp = \App\Models\Lembaga::where('npsn', '20223344')->firstOrFail();
    $smpAktif = \App\Models\TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->firstOrFail();
    $gelombang1 = GelombangPpdb::where('lembaga_id', $smp->id)
        ->where('tahun_ajaran_id', $smpAktif->id)
        ->where('nama', 'Gelombang 1')
        ->firstOrFail();

    expect($gelombang1->jalur()->pluck('jalur_ppdb.nama')->sort()->values()->all())->toBe(['Prestasi', 'Reguler']);

    $sma = \App\Models\Lembaga::where('npsn', '20223355')->firstOrFail();
    $smaAktif = \App\Models\TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();
    $smaGelombang1 = GelombangPpdb::where('lembaga_id', $sma->id)
        ->where('tahun_ajaran_id', $smaAktif->id)
        ->where('nama', 'Gelombang 1')
        ->firstOrFail();

    expect($smaGelombang1->jalur()->exists())->toBeFalse();
})->skip(fn () => ! class_exists(\Database\Seeders\GelombangJalurSeeder::class), 'GelombangJalurSeeder not created yet');
```

The `->skip()` guard exists only so this test file stays parseable before Step 3 creates the seeder class; remove the `->skip(...)` call in Step 4 once the class exists.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter="seeds a restricted demo gelombang"`
Expected: `1 skipped` (the class doesn't exist yet, so the guard trips).

- [ ] **Step 3: Create the seeder**

```php
<?php
// database/seeders/GelombangJalurSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class GelombangJalurSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $smpAktif = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->firstOrFail();

        $gelombang1 = GelombangPpdb::where('lembaga_id', $smp->id)
            ->where('tahun_ajaran_id', $smpAktif->id)
            ->where('nama', 'Gelombang 1')
            ->firstOrFail();

        $jalurDiizinkan = JalurPpdb::where('lembaga_id', $smp->id)
            ->where('tahun_ajaran_id', $smpAktif->id)
            ->whereIn('nama', ['Reguler', 'Prestasi'])
            ->pluck('id');

        $gelombang1->jalur()->sync($jalurDiizinkan);
    }
}
```

Save this to `database/seeders/GelombangJalurSeeder.php`.

- [ ] **Step 4: Remove the skip guard and register the seeder**

In `tests/Feature/GelombangJalurRestrictionTest.php`, remove the trailing `->skip(fn () => ! class_exists(\Database\Seeders\GelombangJalurSeeder::class), 'GelombangJalurSeeder not created yet');` from the test written in Step 1, leaving the closing `});` as the last line of that test.

In `database/seeders/DatabaseSeeder.php`, add the new seeder at the end of the `$this->call([...])` list:

```php
            AkunPendaftarSeeder::class,
            GelombangJalurSeeder::class,
        ]);
```

(replacing the previous closing `AkunPendaftarSeeder::class,\n        ]);`).

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter="seeds a restricted demo gelombang"`
Expected: `1 passed`.

- [ ] **Step 6: Run the full GelombangJalurRestrictionTest suite to check for regressions**

Run: `php artisan test --filter=GelombangJalurRestrictionTest`
Expected: `14 passed`.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/GelombangJalurSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/GelombangJalurRestrictionTest.php
git commit -m "feat: seed a restricted demo gelombang to showcase the jalur restriction"
```

---

## Task 7: Final verification

**Files:** (no new files — pure verification)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: every test passes, 0 failures — including `GelombangJalurRestrictionTest` (14), `GelombangPpdbTest`, and `PortalEntryTest` alongside the full pre-existing suite.

- [ ] **Step 2: Rebuild frontend assets**

Run: `npm run build`
Expected: clean build, no warnings about missing content.

- [ ] **Step 3: Re-seed the local database and manually verify the full flow**

Run: `php artisan migrate:fresh --seed` (local dev database only — confirm this is not pointed at any shared/production database before running).

With `composer dev` running: log in as `superadmin@sistem.test` / `password`, switch to the SMP lembaga (npsn `20223344`) via the topbar switcher, go to Gelombang PPDB index, and confirm `Gelombang 1` already shows a "2 Jalur Dibatasi" badge (from the Task 6 seeder) while `Gelombang 2` shows "Semua Jalur". Open `Gelombang 1`'s edit form and confirm the `Reguler` and `Prestasi` checkboxes are pre-checked and `Afirmasi` is not. Then visit the public `/spmb/{slug}` entry for that same SMP lembaga and confirm only `Reguler` and `Prestasi` are offered as jalur, not `Afirmasi`. Finally, uncheck both boxes on the edit form, save, and confirm the badge reverts to "Semua Jalur" and the public entry now offers all three jalur again.

- [ ] **Step 4: Commit any final cleanup**

If Step 3 surfaces no issues, there's nothing to commit here — this task is verification-only. If it does surface an issue, fix it, re-run Steps 1-2, and commit the fix with a message describing what was wrong.
