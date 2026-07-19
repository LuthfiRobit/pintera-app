# Redesign Jenis Tes & Jenis Tagihan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the critical `tagihan_item` cascade-delete bug on Jenis Tagihan, and redesign both Jenis Tes and Jenis Tagihan into a single-page TailAdmin datatable with an inline add/edit form (no separate create/edit pages, no page reloads).

**Architecture:** Backend: a `tagihanItem()` relation + FK-restrict migration + two-tier `destroy()` guard close the bug; both controllers gain `wantsJson()` JSON branches, `nama` uniqueness validation, and (for Jenis Tes) a new `update()` method + `jenis-tes.edit` permission. Frontend: two new self-contained Alpine components (`jenis-tes-table.js`, `jenis-tagihan-table.js`) hold both the item list and the inline form's state, toggling between add/edit mode — no shared abstraction, matching this codebase's existing convention of separate-but-similar components (`dokumen-syarat-list.js`, `formulir-field-list.js`, `seleksi-list.js`).

**Tech Stack:** Laravel 12, Eloquent, Pest 4, Tailwind CSS 3, Alpine.js 3.4.2, Vite.

## Global Constraints

- All new/changed controller responses branch on `$request->wantsJson()`, following `RoleController`'s existing dual JSON/redirect pattern.
- `<x-badge>` is server-rendered and cannot bind to Alpine state — any badge inside an `x-for` loop is a plain `<span>` reproducing the exact tone classes from `resources/views/components/badge.blade.php` (`brass` → `bg-brand-50 text-brand-600`, `blue` → `bg-blue-100 text-blue-700`, `slate` → `bg-gray-100 text-gray-600`).
- Delete confirmations use the existing global `confirmDialog(title, message)` (from `resources/js/confirm-dialog-store.js`), never the native `confirm()`.
- Toasts use the existing `Alpine.store('toast').push(type, message)`.
- No new shared/generic Alpine factory — two separate table components, per established convention.
- No pagination on either new datatable (row counts are small per lembaga).
- Windows/Git-Bash environment: prefix PHP/Composer/npm commands with `export PATH="/c/laragon/bin/php/php-8.3:/c/laragon/bin/composer:$PATH"` (adjust to the actual local PHP/Composer bin path if this differs) when a plain `php`/`composer` invocation isn't found on PATH inside the Bash tool.

---

### Task 1: Confirm clean baseline test suite

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`

Expected: All tests pass (this is the codebase's baseline before any change in this plan). If anything is already red, stop and report it — do not proceed until the baseline is clean.

- [ ] **Step 2: Run the frontend build**

Run: `npm run build`

Expected: Build completes with no errors (baseline for Vite/Tailwind compilation).

---

### Task 2: `JenisTagihan.tagihanItem()` relation + `tagihan_item` FK restrict migration

**Files:**
- Modify: `app/Models/JenisTagihan.php`
- Create: `database/migrations/2026_07_19_150000_restrict_jenis_tagihan_delete_on_tagihan_item.php`
- Test: `tests/Feature/Admin/JenisTagihanTest.php`

**Interfaces:**
- Produces: `JenisTagihan::tagihanItem(): HasMany<TagihanItem>` — used by Task 3's `destroy()` guard.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Admin/JenisTagihanTest.php`. First add this import near the top with the other `use` statements:

```php
use App\Models\TagihanItem;
```

Then append this test at the end of the file:

```php
it('exposes a tagihanItem relation counting real billing rows for a jenis tagihan', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    TagihanItem::factory()->create(['jenis_tagihan_id' => $jenisTagihan->id]);

    expect($jenisTagihan->tagihanItem()->count())->toBe(1);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter "exposes a tagihanItem relation"`

Expected: FAIL with `Call to undefined method App\Models\JenisTagihan::tagihanItem()`.

- [ ] **Step 3: Add the relation to the model**

In `app/Models/JenisTagihan.php`, add the import and method:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

(This import already exists in the file — confirm it's present, do not duplicate.)

```php
public function tagihanItem(): HasMany
{
    return $this->hasMany(TagihanItem::class);
}
```

Add this method directly below the existing `nominalJalur()` method.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter "exposes a tagihanItem relation"`

Expected: PASS.

- [ ] **Step 5: Write the FK restrict migration**

Create `database/migrations/2026_07_19_150000_restrict_jenis_tagihan_delete_on_tagihan_item.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_item', function (Blueprint $table) {
            $table->dropForeign(['jenis_tagihan_id']);
            $table->foreign('jenis_tagihan_id')->references('id')->on('jenis_tagihan')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_item', function (Blueprint $table) {
            $table->dropForeign(['jenis_tagihan_id']);
            $table->foreign('jenis_tagihan_id')->references('id')->on('jenis_tagihan')->cascadeOnDelete();
        });
    }
};
```

- [ ] **Step 6: Write a regression test confirming normal read/write still works after the FK change**

Append to `tests/Feature/Admin/JenisTagihanTest.php`:

```php
it('still allows creating and reading tagihan_item rows normally after the FK is changed to restrict', function () {
    $item = TagihanItem::factory()->create();

    expect(TagihanItem::find($item->id))->not->toBeNull();
    expect($item->jenisTagihan)->not->toBeNull();
});
```

- [ ] **Step 7: Run the full JenisTagihanTest.php file to verify everything passes**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php`

Expected: All tests PASS, including the two new ones.

- [ ] **Step 8: Commit**

```bash
git add app/Models/JenisTagihan.php database/migrations/2026_07_19_150000_restrict_jenis_tagihan_delete_on_tagihan_item.php tests/Feature/Admin/JenisTagihanTest.php
git commit -m "fix: restrict tagihan_item FK on jenis_tagihan delete instead of cascading"
```

---

### Task 3: `JenisTagihanController::destroy()` two-tier guard + JSON support (closes the critical bug)

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTagihanController.php`
- Test: `tests/Feature/Admin/JenisTagihanTest.php`

**Interfaces:**
- Consumes: `JenisTagihan::tagihanItem(): HasMany` (Task 2), `JenisTagihan::nominalJalur(): HasMany` (already exists).
- Produces: `JenisTagihanController::errorResponse(Request, string): RedirectResponse|JsonResponse` — a new private helper other tasks in this controller (Task 4) also use.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/JenisTagihanTest.php`:

```php
it('blocks deleting a jenis tagihan already billed to a registrant, naming the number of tagihan', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    TagihanItem::factory()->create(['jenis_tagihan_id' => $jenisTagihan->id]);

    $response = $this->actingAs($user)->delete(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertRedirect()->assertSessionHasErrors([
        'jenis_tagihan' => 'Tidak bisa dihapus, sudah dipakai di 1 tagihan milik calon murid.',
    ]);
    expect(JenisTagihan::find($jenisTagihan->id))->not->toBeNull();
});

it('blocks deleting a jenis tagihan with configured nominal but no real billing yet', function () {
    [$lembaga, , $jalur] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    $response = $this->actingAs($user)->delete(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertRedirect()->assertSessionHasErrors([
        'jenis_tagihan' => 'Tidak bisa dihapus, sudah ada 1 nominal jalur yang dikonfigurasi. Hapus dulu di halaman Kelola Nominal.',
    ]);
    expect(JenisTagihan::find($jenisTagihan->id))->not->toBeNull();
});

it('allows deleting a jenis tagihan with no related tagihan or nominal rows', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->delete(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    expect(JenisTagihan::find($jenisTagihan->id))->toBeNull();
});

it('responds with json when deleting a jenis tagihan blocked by real billing rows', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    TagihanItem::factory()->create(['jenis_tagihan_id' => $jenisTagihan->id]);

    $response = $this->actingAs($user)->deleteJson(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertStatus(422)->assertJson(['message' => 'Tidak bisa dihapus, sudah dipakai di 1 tagihan milik calon murid.']);
});

it('responds with json on a successful jenis tagihan delete', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->deleteJson(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertOk()->assertJson(['message' => 'Jenis tagihan berhasil dihapus.']);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter "deleting a jenis tagihan"`

Expected: The nominal-only-blocks and no-relations-allows cases currently pass by coincidence (old behavior already blocks on `nominalJalur`), but the tagihan-blocks-delete tests and both JSON tests FAIL — either the old code lets the delete through and then errors/succeeds unexpectedly, or `deleteJson()` doesn't get a JSON response at all (redirect instead).

- [ ] **Step 3: Implement the two-tier guard + JSON support**

Replace `destroy()` in `app/Http/Controllers/Admin/JenisTagihanController.php`:

```php
public function destroy(Request $request, JenisTagihan $jenisTagihan): RedirectResponse|JsonResponse
{
    $this->authorize('jenis-tagihan.delete');

    $jumlahTagihan = $jenisTagihan->tagihanItem()->count();
    if ($jumlahTagihan > 0) {
        return $this->errorResponse(
            $request,
            "Tidak bisa dihapus, sudah dipakai di {$jumlahTagihan} tagihan milik calon murid."
        );
    }

    $jumlahNominal = $jenisTagihan->nominalJalur()->count();
    if ($jumlahNominal > 0) {
        return $this->errorResponse(
            $request,
            "Tidak bisa dihapus, sudah ada {$jumlahNominal} nominal jalur yang dikonfigurasi. Hapus dulu di halaman Kelola Nominal."
        );
    }

    $jenisTagihan->delete();

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Jenis tagihan berhasil dihapus.']);
    }

    return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil dihapus.');
}

private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
{
    if ($request->wantsJson()) {
        return response()->json(['message' => $message], 422);
    }

    return back()->withErrors(['jenis_tagihan' => $message]);
}
```

Add these imports at the top of the file if not already present:

```php
use Illuminate\Http\JsonResponse;
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php`

Expected: All tests PASS, including the pre-existing ones (regression check).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTagihanController.php tests/Feature/Admin/JenisTagihanTest.php
git commit -m "fix: block deleting a jenis tagihan that already has real tagihan rows"
```

---

### Task 4: Jenis Tagihan — nama uniqueness, JSON store/update, drop create/edit pages, fix nominal guard redirects

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTagihanController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JenisTagihanTest.php`

**Interfaces:**
- Consumes: `errorResponse()` (Task 3).
- Produces: `store()` JSON response shape `{ data: JenisTagihan, redirect: string|null }` (201); `update()` JSON response shape `{ data: JenisTagihan }` (200) — both consumed by Task 8's `jenis-tagihan-table.js`.

This task removes `create()` and `edit()` entirely (their pages are being replaced by the inline form) and fixes three existing tests that assert a redirect to the now-removed `jenis-tagihan.edit` route.

- [ ] **Step 1: Write the failing uniqueness test**

Append to `tests/Feature/Admin/JenisTagihanTest.php`:

```php
it('rejects creating a jenis tagihan with a name already used in the same lembaga', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => false,
    ]);

    $response->assertSessionHasErrors('nama');
    expect(JenisTagihan::where('lembaga_id', $lembaga->id)->count())->toBe(1);
});

it('rejects updating a jenis tagihan to a name already used by another jenis tagihan in the same lembaga, but allows keeping its own name', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    $target = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Daftar Ulang', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => false]);

    $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $target), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => false,
    ])->assertSessionHasErrors('nama');

    $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $target), [
        'nama' => 'Daftar Ulang', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => false,
    ])->assertSessionDoesntHaveErrors('nama');

    expect($target->fresh()->nama)->toBe('Daftar Ulang');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter "jenis tagihan with a name already used"`

Expected: FAIL — `store()`/`update()` currently accept duplicate names with no validation error.

- [ ] **Step 3: Add uniqueness validation to `update()`**

`update()` already has `$jenisTagihan` resolved via route model binding, so its uniqueness check is self-contained and can land now. `store()`'s uniqueness check depends on the yayasan/lembaga scope resolution block that Step 7 introduces alongside JSON support, so it lands together with that rewrite instead of here.

In `app/Http/Controllers/Admin/JenisTagihanController.php`, add this import:

```php
use Illuminate\Validation\Rule;
```

Update the `$data = $request->validate([...])` block in `update()`:

```php
$data = $request->validate([
    'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tagihan', 'nama')
        ->where(fn ($query) => $query->where('lembaga_id', $jenisTagihan->lembaga_id))
        ->ignore($jenisTagihan->id)],
    'kategori' => ['required', 'in:pendaftaran,daftar_ulang,lainnya'],
    'bisa_dicicil' => ['nullable', 'boolean'],
    'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
]);
```

- [ ] **Step 4: Run the tests to verify the update-uniqueness test passes**

Run: `php artisan test --filter "updating a jenis tagihan to a name already used"`

Expected: PASS. The sibling test `'rejects creating a jenis tagihan with a name already used in the same lembaga'` is still expected to FAIL at this point — `store()` isn't touched until Step 7 — so do not run the full `--filter "jenis tagihan with a name already used"` group yet.

- [ ] **Step 5: Write the failing JSON create/update tests**

Append to `tests/Feature/Admin/JenisTagihanTest.php`:

```php
it('responds with json and a nominal redirect url after creating a pendaftaran jenis tagihan', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    $jenisTagihan = JenisTagihan::where('nama', 'Biaya Pendaftaran')->firstOrFail();
    $response->assertCreated()->assertJson([
        'data' => ['nama' => 'Biaya Pendaftaran'],
        'redirect' => route('admin.jenis-tagihan.nominal', $jenisTagihan),
    ]);
});

it('responds with json and a null redirect after creating a kategori lainnya jenis tagihan', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false,
    ]);

    $response->assertCreated()->assertJson(['redirect' => null]);
});

it('responds with json after updating a jenis tagihan', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->putJson(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => 'Biaya Pendaftaran Baru', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    $response->assertOk()->assertJson(['data' => ['nama' => 'Biaya Pendaftaran Baru']]);
});
```

- [ ] **Step 6: Run the tests to verify they fail**

Run: `php artisan test --filter "responds with json"`

Expected: FAIL — `store()`/`update()` currently always redirect, never return JSON.

- [ ] **Step 7: Implement JSON support in `store()` and `update()`, remove `create()`/`edit()`**

Replace the full `create()` through `update()` block in `app/Http/Controllers/Admin/JenisTagihanController.php` (i.e. delete the `create()` method entirely, delete the `edit()` method entirely, and replace `store()`/`update()`) with:

```php
public function store(Request $request): RedirectResponse|JsonResponse
{
    $this->authorize('jenis-tagihan.create');

    $isYayasanScope = $request->user()->widestScopeLevel() === 'yayasan';
    if ($isYayasanScope) {
        $lembagaId = session('active_lembaga_id');
        if ($lembagaId === null) {
            $message = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'errors' => ['lembaga_id' => [$message]]], 422);
            }

            return back()->withErrors(['lembaga_id' => $message])->withInput();
        }
    } else {
        $lembagaId = $request->user()->lembaga_id;
    }

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tagihan', 'nama')
            ->where(fn ($query) => $query->where('lembaga_id', $lembagaId))],
        'kategori' => ['required', 'in:pendaftaran,daftar_ulang,lainnya'],
        'bisa_dicicil' => ['nullable', 'boolean'],
        'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
    ]);
    $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');
    if ($isYayasanScope) {
        $data['lembaga_id'] = $lembagaId;
    }

    $jenisTagihan = JenisTagihan::create($data);

    if ($request->wantsJson()) {
        return response()->json([
            'data' => $jenisTagihan->fresh(),
            'redirect' => $jenisTagihan->kategori !== 'lainnya'
                ? route('admin.jenis-tagihan.nominal', $jenisTagihan)
                : null,
        ], 201);
    }

    if ($jenisTagihan->kategori === 'lainnya') {
        return redirect()->route('admin.jenis-tagihan.index')
            ->with('status', 'Jenis tagihan berhasil ditambahkan. Kategori "Lainnya" belum punya mekanisme penentuan nominal — itu akan dibangun bersama modul yang memakainya nanti (misalnya SPP).');
    }

    return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)
        ->with('status', 'Jenis tagihan berhasil ditambahkan. Atur nominal per jalur di bawah.');
}

public function update(Request $request, JenisTagihan $jenisTagihan): RedirectResponse|JsonResponse
{
    $this->authorize('jenis-tagihan.edit');

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tagihan', 'nama')
            ->where(fn ($query) => $query->where('lembaga_id', $jenisTagihan->lembaga_id))
            ->ignore($jenisTagihan->id)],
        'kategori' => ['required', 'in:pendaftaran,daftar_ulang,lainnya'],
        'bisa_dicicil' => ['nullable', 'boolean'],
        'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
    ]);
    $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');

    $jenisTagihan->update($data);

    if ($request->wantsJson()) {
        return response()->json(['data' => $jenisTagihan->fresh()]);
    }

    return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil diperbarui.');
}
```

Also update `index()` to eager-load the usage counts used by the new badge (Task 8):

```php
public function index(): View
{
    $this->authorize('jenis-tagihan.view');

    return view('admin.jenis-tagihan.index', [
        'jenisTagihanList' => JenisTagihan::withCount(['nominalJalur', 'tagihanItem'])->orderBy('nama')->get(),
    ]);
}
```

Finally, fix the two remaining `jenis-tagihan.edit` redirects in `nominal()` and `simpanNominal()` — since the edit page no longer exists, both now redirect to the index instead:

```php
public function nominal(JenisTagihan $jenisTagihan): View|RedirectResponse
{
    $this->authorize('jenis-tagihan.edit');

    if ($jenisTagihan->kategori === 'lainnya') {
        return redirect()->route('admin.jenis-tagihan.index')
            ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang. Kategori "Lainnya" belum punya mekanisme penentuan nominal.']);
    }

    $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $jenisTagihan->lembaga_id)->where('status_aktif', true)->first();

    return view('admin.jenis-tagihan.nominal', [
        'jenisTagihan' => $jenisTagihan,
        'jalurList' => $tahunAjaranAktif
            ? JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
            : collect(),
        'nominalMap' => NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->pluck('nominal', 'jalur_ppdb_id'),
        'tahunAjaranAktif' => $tahunAjaranAktif,
    ]);
}

public function simpanNominal(Request $request, JenisTagihan $jenisTagihan): RedirectResponse
{
    $this->authorize('jenis-tagihan.edit');

    if ($jenisTagihan->kategori === 'lainnya') {
        return redirect()->route('admin.jenis-tagihan.index')
            ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang.']);
    }

    $data = $request->validate([
        'nominal' => ['required', 'array'],
        'nominal.*' => ['nullable', 'numeric', 'min:0'],
    ]);

    $jalurIds = JalurPpdb::where('lembaga_id', $jenisTagihan->lembaga_id)->pluck('id');

    foreach ($data['nominal'] as $jalurPpdbId => $nominal) {
        if (! $jalurIds->contains((int) $jalurPpdbId) || $nominal === null || $nominal === '') {
            continue;
        }

        NominalTagihanJalur::updateOrCreate(
            ['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalurPpdbId],
            ['nominal' => $nominal]
        );
    }

    return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)->with('status', 'Nominal berhasil disimpan.');
}
```

- [ ] **Step 8: Update the three existing tests that assert a redirect to the removed `jenis-tagihan.edit` route**

In `tests/Feature/Admin/JenisTagihanTest.php`, change:

```php
it('does not send kategori lainnya through the jalur-based nominal flow after create', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false,
    ]);

    $jenisTagihan = JenisTagihan::where('nama', 'SPP Bulanan')->firstOrFail();
    $response->assertRedirect(route('admin.jenis-tagihan.edit', $jenisTagihan));
});
```

to:

```php
it('does not send kategori lainnya through the jalur-based nominal flow after create', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false,
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
});
```

Change:

```php
it('redirects away from the nominal page for a kategori lainnya jenis tagihan instead of showing a jalur list', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.nominal', $jenisTagihan));

    $response->assertRedirect(route('admin.jenis-tagihan.edit', $jenisTagihan));
    $response->assertSessionHasErrors('kategori');
});
```

to:

```php
it('redirects away from the nominal page for a kategori lainnya jenis tagihan instead of showing a jalur list', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.nominal', $jenisTagihan));

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    $response->assertSessionHasErrors('kategori');
});
```

Change:

```php
it('rejects a direct post to simpan nominal for a kategori lainnya jenis tagihan without creating rows', function () {
    [$lembaga, , $jalur] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), [
        'nominal' => [$jalur->id => 100000],
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.edit', $jenisTagihan));
    $response->assertSessionHasErrors('kategori');
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeFalse();
});
```

to:

```php
it('rejects a direct post to simpan nominal for a kategori lainnya jenis tagihan without creating rows', function () {
    [$lembaga, , $jalur] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), [
        'nominal' => [$jalur->id => 100000],
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    $response->assertSessionHasErrors('kategori');
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeFalse();
});
```

- [ ] **Step 9: Remove the now-dead `create`/`edit` GET routes**

In `routes/admin.php`, remove these two lines:

```php
Route::get('jenis-tagihan/create', [JenisTagihanController::class, 'create'])->name('jenis-tagihan.create');
```

```php
Route::get('jenis-tagihan/{jenisTagihan}/edit', [JenisTagihanController::class, 'edit'])->name('jenis-tagihan.edit');
```

Leave `POST jenis-tagihan`, `PUT jenis-tagihan/{jenisTagihan}`, `DELETE jenis-tagihan/{jenisTagihan}`, `GET/POST jenis-tagihan/{jenisTagihan}/nominal` untouched.

- [ ] **Step 10: Run the full test file to verify everything passes**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php`

Expected: All tests PASS.

- [ ] **Step 11: Run the full suite to check for stragglers referencing the removed routes**

Run: `php artisan test`

Expected: All tests PASS. If any other test file references `jenis-tagihan.create` or `jenis-tagihan.edit` as a route name, fix it the same way (redirect target becomes `jenis-tagihan.index`).

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTagihanController.php routes/admin.php tests/Feature/Admin/JenisTagihanTest.php
git commit -m "feat: add nama uniqueness and JSON support to jenis tagihan, retire separate create/edit pages"
```

---

### Task 5: Jenis Tes — `jenis-tes.edit` permission + `update()` method + route

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTesMasterController.php`
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/RoleSeeder.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JenisTesMasterTest.php`

**Interfaces:**
- Produces: `update()` JSON response shape `{ data: JenisTesMaster }` (200) — consumed by Task 7's `jenis-tes-table.js`.

- [ ] **Step 1: Update the test file's permission helpers to include the new permission**

In `tests/Feature/Admin/JenisTesMasterTest.php`, change both occurrences of the permission array. In `buatAdminPpdb()`:

```php
function buatAdminPpdb(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return [$lembaga, $user];
}
```

In `buatYayasanSuperAdminDenganLembagaAktif()`:

```php
function buatYayasanSuperAdminDenganLembagaAktif(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete']);
    $user = User::factory()->create();
    $user->assignRole($role);

    test()->actingAs($user);
    test()->get('/dashboard?switch_lembaga='.$lembaga->id);

    return [$lembaga, $user];
}
```

- [ ] **Step 2: Write the failing test**

Append to `tests/Feature/Admin/JenisTesMasterTest.php`:

```php
it('updates a jenis tes nama and deskripsi', function () {
    [$lembaga, $user] = buatAdminPpdb();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);

    $response = $this->actingAs($user)->put(route('admin.jenis-tes.update', $jenisTes), [
        'nama' => 'Tes Tulis Akademik', 'deskripsi' => 'Diperbarui',
    ]);

    $response->assertRedirect(route('admin.jenis-tes.index'));
    expect($jenisTes->fresh()->nama)->toBe('Tes Tulis Akademik');
    expect($jenisTes->fresh()->deskripsi)->toBe('Diperbarui');
});

it('denies updating a jenis tes without the jenis-tes.edit permission', function () {
    [$lembaga] = buatAdminPpdb();
    $bareUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);

    $this->actingAs($bareUser)
        ->put(route('admin.jenis-tes.update', $jenisTes), ['nama' => 'Coba Ubah'])
        ->assertForbidden();
});

it('rejects updating a jenis tes nama to one already used by another jenis tes in the same lembaga, but allows keeping its own name', function () {
    [$lembaga, $user] = buatAdminPpdb();
    JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $target = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Wawancara']);

    $this->actingAs($user)
        ->put(route('admin.jenis-tes.update', $target), ['nama' => 'Tes Tulis'])
        ->assertSessionHasErrors('nama');

    $this->actingAs($user)
        ->put(route('admin.jenis-tes.update', $target), ['nama' => 'Wawancara'])
        ->assertSessionDoesntHaveErrors('nama');

    expect($target->fresh()->nama)->toBe('Wawancara');
});

it('responds with json after updating a jenis tes', function () {
    [$lembaga, $user] = buatAdminPpdb();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);

    $response = $this->actingAs($user)->putJson(route('admin.jenis-tes.update', $jenisTes), [
        'nama' => 'Tes Tulis Akademik',
    ]);

    $response->assertOk()->assertJson(['data' => ['nama' => 'Tes Tulis Akademik']]);
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter "updates a jenis tes"`

Expected: FAIL — the `admin.jenis-tes.update` route does not exist yet.

- [ ] **Step 4: Add the `jenis-tes.edit` permission to the real seeders**

In `database/seeders/PermissionSeeder.php`, change:

```php
'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
```

to:

```php
'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete',
```

In `database/seeders/RoleSeeder.php`, change:

```php
'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
```

to:

```php
'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete',
```

(This is inside the `admin_administrasi` block.)

- [ ] **Step 5: Add the route**

In `routes/admin.php`, add this line directly after the existing `jenis-tes.store` route:

```php
Route::put('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'update'])->name('jenis-tes.update');
```

- [ ] **Step 6: Add the `update()` method**

In `app/Http/Controllers/Admin/JenisTesMasterController.php`, add this import:

```php
use Illuminate\Http\JsonResponse;
```

Add this method directly after `store()`:

```php
public function update(Request $request, JenisTesMaster $jenisTes): RedirectResponse|JsonResponse
{
    $this->authorize('jenis-tes.edit');

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tes_master', 'nama')
            ->where(fn ($query) => $query->where('lembaga_id', $jenisTes->lembaga_id))
            ->ignore($jenisTes->id)],
        'deskripsi' => ['nullable', 'string', 'max:1000'],
    ]);

    $jenisTes->update($data);

    if ($request->wantsJson()) {
        return response()->json(['data' => $jenisTes->fresh()]);
    }

    return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil diperbarui.');
}
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTesMasterTest.php`

Expected: All tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTesMasterController.php database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php routes/admin.php tests/Feature/Admin/JenisTesMasterTest.php
git commit -m "feat: add jenis-tes.edit permission and update() endpoint"
```

---

### Task 6: Jenis Tes — count-based destroy message, JSON store/destroy, index() usage count

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTesMasterController.php`
- Test: `tests/Feature/Admin/JenisTesMasterTest.php`

**Interfaces:**
- Produces: `index()` view data `jenisTesList` items each carry `seleksi_count` (int) — consumed by Task 7's Blade view/badge.
- Produces: `store()` JSON response shape `{ data: JenisTesMaster }` (201); `destroy()` JSON response shapes `{ message }` (200) / `{ message }` (422) — consumed by Task 7's `jenis-tes-table.js`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/JenisTesMasterTest.php`:

```php
it('includes the exact number of blocking seleksi rows in the destroy error message', function () {
    [$lembaga, $user] = buatAdminPpdb();
    $this->actingAs($user);

    $tahunAjaran = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = \App\Models\JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = \App\Models\GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-01-01', 'tanggal_tutup' => '2026-02-01', 'kuota' => 20,
    ]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    \App\Models\SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-01-15 09:00:00',
    ]);

    $response = $this->delete(route('admin.jenis-tes.destroy', $jenisTes));

    $response->assertSessionHasErrors([
        'jenis_tes' => 'Tidak bisa dihapus, jenis tes ini masih dipakai di 1 jadwal seleksi.',
    ]);
});

it('responds with json when a jenis tes delete is blocked by a seleksi row', function () {
    [$lembaga, $user] = buatAdminPpdb();
    $this->actingAs($user);

    $tahunAjaran = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = \App\Models\JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = \App\Models\GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-01-01', 'tanggal_tutup' => '2026-02-01', 'kuota' => 20,
    ]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    \App\Models\SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-01-15 09:00:00',
    ]);

    $response = $this->deleteJson(route('admin.jenis-tes.destroy', $jenisTes));

    $response->assertStatus(422)->assertJson(['message' => 'Tidak bisa dihapus, jenis tes ini masih dipakai di 1 jadwal seleksi.']);
});

it('responds with json on a successful jenis tes create and delete', function () {
    [, $user] = buatAdminPpdb();

    $createResponse = $this->actingAs($user)->postJson(route('admin.jenis-tes.store'), ['nama' => 'Tes Baru']);
    $createResponse->assertCreated()->assertJson(['data' => ['nama' => 'Tes Baru']]);

    $jenisTes = JenisTesMaster::where('nama', 'Tes Baru')->firstOrFail();
    $deleteResponse = $this->actingAs($user)->deleteJson(route('admin.jenis-tes.destroy', $jenisTes));
    $deleteResponse->assertOk()->assertJson(['message' => 'Jenis tes berhasil dihapus.']);
});

it('includes the seleksi usage count for each jenis tes on the index page', function () {
    [$lembaga, $user] = buatAdminPpdb();
    $this->actingAs($user);

    $tahunAjaran = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = \App\Models\JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = \App\Models\GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-01-01', 'tanggal_tutup' => '2026-02-01', 'kuota' => 20,
    ]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    \App\Models\SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-01-15 09:00:00',
    ]);

    $response = $this->get(route('admin.jenis-tes.index'));

    $response->assertViewHas('jenisTesList', function ($jenisTesList) use ($jenisTes) {
        return (int) $jenisTesList->firstWhere('id', $jenisTes->id)->seleksi_count === 1;
    });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter "jenis tes"`

Expected: FAIL — old message text doesn't match ("... masih dipakai di satu atau lebih jadwal seleksi ..."), JSON requests get a redirect instead of a JSON body, and `seleksi_count` is not present on the collection.

- [ ] **Step 3: Implement `index()`, `store()`, `destroy()` changes**

Replace `index()`, `store()`, and `destroy()` in `app/Http/Controllers/Admin/JenisTesMasterController.php`:

```php
public function index(): View
{
    $this->authorize('jenis-tes.view');

    return view('admin.jenis-tes.index', [
        'jenisTesList' => JenisTesMaster::withCount('seleksi')->orderBy('nama')->get(),
    ]);
}

public function store(Request $request): RedirectResponse|JsonResponse
{
    $this->authorize('jenis-tes.create');

    $isYayasanScope = $request->user()->widestScopeLevel() === 'yayasan';
    if ($isYayasanScope) {
        $lembagaId = session('active_lembaga_id');
        if ($lembagaId === null) {
            $message = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tes.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'errors' => ['lembaga_id' => [$message]]], 422);
            }

            return back()->withErrors(['lembaga_id' => $message])->withInput();
        }
    } else {
        $lembagaId = $request->user()->lembaga_id;
    }

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tes_master', 'nama')->where(fn ($query) => $query->where('lembaga_id', $lembagaId))],
        'deskripsi' => ['nullable', 'string', 'max:1000'],
    ]);
    if ($isYayasanScope) {
        $data['lembaga_id'] = $lembagaId;
    }

    $jenisTes = JenisTesMaster::create($data);

    if ($request->wantsJson()) {
        return response()->json(['data' => $jenisTes->fresh()], 201);
    }

    return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil ditambahkan.');
}

public function destroy(Request $request, JenisTesMaster $jenisTes): RedirectResponse|JsonResponse
{
    $this->authorize('jenis-tes.delete');

    $jumlahSeleksi = SeleksiPpdb::where('jenis_tes_master_id', $jenisTes->id)->count();
    if ($jumlahSeleksi > 0) {
        $message = "Tidak bisa dihapus, jenis tes ini masih dipakai di {$jumlahSeleksi} jadwal seleksi.";

        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return redirect()->route('admin.jenis-tes.index')->withErrors(['jenis_tes' => $message]);
    }

    $jenisTes->delete();

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Jenis tes berhasil dihapus.']);
    }

    return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil dihapus.');
}
```

- [ ] **Step 4: Run the full test file to verify everything passes**

Run: `php artisan test tests/Feature/Admin/JenisTesMasterTest.php`

Expected: All tests PASS, including all pre-existing tests (regression check for the message-text change against the one old test that only checked the error *key*, not its exact text).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTesMasterController.php tests/Feature/Admin/JenisTesMasterTest.php
git commit -m "feat: add json support, count-based destroy message, and usage count to jenis tes"
```

---

### Task 7: Frontend — Jenis Tes inline datatable

**Files:**
- Create: `resources/js/jenis-tes-table.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/jenis-tes/index.blade.php`

**Interfaces:**
- Consumes: `route('admin.jenis-tes.store')`, `route('admin.jenis-tes.update', ...)`, `route('admin.jenis-tes.destroy', ...)` (Tasks 5–6); `window.confirmDialog()` (existing); `Alpine.store('toast')` (existing).

- [ ] **Step 1: Create the Alpine component**

Create `resources/js/jenis-tes-table.js`:

```js
export function jenisTesTable(config) {
    return {
        items: config.initialItems,
        storeUrl: config.storeUrl,
        updateUrlTemplate: config.updateUrlTemplate,
        deleteUrlTemplate: config.deleteUrlTemplate,
        editingId: null,
        form: { nama: '', deskripsi: '' },
        errors: {},
        submitting: false,

        startEdit(item) {
            this.editingId = item.id;
            this.form = { nama: item.nama, deskripsi: item.deskripsi ?? '' };
            this.errors = {};
            this.$refs.formCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        cancelEdit() {
            this.editingId = null;
            this.form = { nama: '', deskripsi: '' };
            this.errors = {};
        },

        async submit() {
            this.submitting = true;
            this.errors = {};
            const isEdit = this.editingId !== null;
            const url = isEdit ? this.updateUrlTemplate.replace('__ID__', this.editingId) : this.storeUrl;

            try {
                const response = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.form),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan jenis tes.');
                    return;
                }

                if (isEdit) {
                    const index = this.items.findIndex((existing) => existing.id === this.editingId);
                    if (index !== -1) this.items[index] = json.data;
                } else {
                    this.items.push(json.data);
                }

                this.cancelEdit();
                Alpine.store('toast').push('success', isEdit ? 'Jenis tes berhasil diperbarui.' : 'Jenis tes berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan jenis tes.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            const confirmed = await confirmDialog('Hapus Jenis Tes?', `Apakah Anda yakin ingin menghapus "${item.nama}"?`);
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', item.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus jenis tes.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                if (this.editingId === item.id) {
                    this.cancelEdit();
                }
                Alpine.store('toast').push('success', json.message ?? 'Jenis tes berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus jenis tes.');
            }
        },
    };
}
```

- [ ] **Step 2: Register the component in `app.js`**

In `resources/js/app.js`, add the import next to the other list-component imports:

```js
import { jenisTesTable } from './jenis-tes-table';
```

Add the registration next to the other `Alpine.data(...)` calls:

```js
Alpine.data('jenisTesTable', jenisTesTable);
```

- [ ] **Step 3: Rewrite the Blade view**

Replace the full contents of `resources/views/admin/jenis-tes/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Jenis Tes</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jenis Tes</b>
            </p>
        </div>

        <div
            x-data="jenisTesTable({
                initialItems: @js($jenisTesList),
                storeUrl: @js(route('admin.jenis-tes.store')),
                updateUrlTemplate: @js(route('admin.jenis-tes.update', ['jenisTes' => '__ID__'])),
                deleteUrlTemplate: @js(route('admin.jenis-tes.destroy', ['jenisTes' => '__ID__'])),
            })"
            class="space-y-5"
        >
            @can('jenis-tes.create')
                <div x-ref="formCard" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                    <p class="font-display text-sm font-bold text-gray-900" x-text="editingId === null ? 'Tambah Jenis Tes' : 'Edit Jenis Tes'"></p>
                    <form @submit.prevent="submit()" class="mt-3 flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1">
                            <x-input-label value="Nama Jenis Tes" />
                            <x-text-input type="text" x-model="form.nama" placeholder="mis. Tes Tulis, Wawancara" class="mt-1.5" />
                            <p class="mt-1.5 text-sm text-error-600" x-show="errors.nama" x-text="errors.nama?.[0]"></p>
                        </div>
                        <div class="min-w-[200px] flex-1">
                            <x-input-label value="Deskripsi (Opsional)" />
                            <x-text-input type="text" x-model="form.deskripsi" class="mt-1.5" />
                            <p class="mt-1.5 text-sm text-error-600" x-show="errors.deskripsi" x-text="errors.deskripsi?.[0]"></p>
                        </div>
                        <x-primary-button type="submit" x-bind:disabled="submitting" x-text="editingId === null ? 'Tambah' : 'Simpan'"></x-primary-button>
                        <x-secondary-button type="button" x-show="editingId !== null" @click="cancelEdit()">Batal</x-secondary-button>
                    </form>
                </div>
            @endcan

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-200 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Jenis Tes</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">Nama</th>
                                <th class="px-5 py-3">Deskripsi</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-if="items.length === 0">
                                <tr><td colspan="4" class="px-5 py-6 text-center text-sm text-gray-500">Belum ada jenis tes.</td></tr>
                            </template>
                            <template x-for="item in items" :key="item.id">
                                <tr class="transition hover:bg-gray-50">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                        <x-table-actions>
                                            @can('jenis-tes.edit')
                                                <x-dropdown-link href="#" @click.prevent="startEdit(item)">
                                                    <span class="inline-flex items-center gap-2.5">
                                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                        Edit
                                                    </span>
                                                </x-dropdown-link>
                                            @endcan
                                            @can('jenis-tes.delete')
                                                <x-dropdown-link href="#" @click.prevent="deleteItem(item)" class="text-error-600">Hapus</x-dropdown-link>
                                            @endcan
                                        </x-table-actions>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="item.deskripsi || '—'"></td>
                                    <td class="px-5 py-3.5">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="item.seleksi_count > 0 ? 'bg-brand-50 text-brand-600' : 'bg-gray-100 text-gray-600'"
                                            x-text="item.seleksi_count > 0 ? 'Dipakai di ' + item.seleksi_count + ' Seleksi' : 'Tidak Dipakai'"
                                        ></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 4: Build the frontend**

Run: `npm run build`

Expected: Build completes with no errors.

- [ ] **Step 5: Run the backend test suite to confirm no regressions**

Run: `php artisan test tests/Feature/Admin/JenisTesMasterTest.php`

Expected: All tests PASS (the view rewrite must not break any existing assertion — none of them inspect specific HTML in this file, only redirects and session errors, so this is a straightforward regression check).

- [ ] **Step 6: Commit**

```bash
git add resources/js/jenis-tes-table.js resources/js/app.js resources/views/admin/jenis-tes/index.blade.php
git commit -m "feat: redesign jenis tes into a TailAdmin datatable with an inline add/edit form"
```

---

### Task 8: Frontend — Jenis Tagihan inline datatable, retire create/edit Blade files

**Files:**
- Create: `resources/js/jenis-tagihan-table.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/jenis-tagihan/index.blade.php`
- Delete: `resources/views/admin/jenis-tagihan/create.blade.php`
- Delete: `resources/views/admin/jenis-tagihan/edit.blade.php`

**Interfaces:**
- Consumes: `route('admin.jenis-tagihan.store')`, `route('admin.jenis-tagihan.update', ...)`, `route('admin.jenis-tagihan.destroy', ...)`, `route('admin.jenis-tagihan.nominal', ...)` (Tasks 3–4); `window.confirmDialog()`; `Alpine.store('toast')`.

- [ ] **Step 1: Create the Alpine component**

Create `resources/js/jenis-tagihan-table.js`:

```js
export function jenisTagihanTable(config) {
    return {
        items: config.initialItems,
        storeUrl: config.storeUrl,
        updateUrlTemplate: config.updateUrlTemplate,
        deleteUrlTemplate: config.deleteUrlTemplate,
        nominalUrlTemplate: config.nominalUrlTemplate,
        editingId: null,
        form: { nama: '', kategori: 'pendaftaran', bisa_dicicil: false, maks_cicilan: '' },
        errors: {},
        submitting: false,

        startEdit(item) {
            this.editingId = item.id;
            this.form = {
                nama: item.nama,
                kategori: item.kategori,
                bisa_dicicil: item.bisa_dicicil,
                maks_cicilan: item.maks_cicilan ?? '',
            };
            this.errors = {};
            this.$refs.formCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        cancelEdit() {
            this.editingId = null;
            this.form = { nama: '', kategori: 'pendaftaran', bisa_dicicil: false, maks_cicilan: '' };
            this.errors = {};
        },

        nominalUrl(item) {
            return this.nominalUrlTemplate.replace('__ID__', item.id);
        },

        async submit() {
            this.submitting = true;
            this.errors = {};
            const isEdit = this.editingId !== null;
            const url = isEdit ? this.updateUrlTemplate.replace('__ID__', this.editingId) : this.storeUrl;

            try {
                const response = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.form),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan jenis tagihan.');
                    return;
                }

                if (isEdit) {
                    const index = this.items.findIndex((existing) => existing.id === this.editingId);
                    if (index !== -1) this.items[index] = json.data;
                    this.cancelEdit();
                    Alpine.store('toast').push('success', 'Jenis tagihan berhasil diperbarui.');
                    return;
                }

                this.items.push(json.data);
                Alpine.store('toast').push('success', 'Jenis tagihan berhasil ditambahkan.');

                if (json.redirect) {
                    window.location.href = json.redirect;
                    return;
                }

                this.cancelEdit();
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan jenis tagihan.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            const confirmed = await confirmDialog('Hapus Jenis Tagihan?', `Apakah Anda yakin ingin menghapus "${item.nama}"?`);
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', item.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus jenis tagihan.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                if (this.editingId === item.id) {
                    this.cancelEdit();
                }
                Alpine.store('toast').push('success', json.message ?? 'Jenis tagihan berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus jenis tagihan.');
            }
        },
    };
}
```

- [ ] **Step 2: Register the component in `app.js`**

In `resources/js/app.js`, add the import:

```js
import { jenisTagihanTable } from './jenis-tagihan-table';
```

Add the registration:

```js
Alpine.data('jenisTagihanTable', jenisTagihanTable);
```

- [ ] **Step 3: Rewrite the index Blade view**

Replace the full contents of `resources/views/admin/jenis-tagihan/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Jenis Tagihan</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jenis Tagihan</b>
            </p>
        </div>

        <div
            x-data="jenisTagihanTable({
                initialItems: @js($jenisTagihanList),
                storeUrl: @js(route('admin.jenis-tagihan.store')),
                updateUrlTemplate: @js(route('admin.jenis-tagihan.update', ['jenisTagihan' => '__ID__'])),
                deleteUrlTemplate: @js(route('admin.jenis-tagihan.destroy', ['jenisTagihan' => '__ID__'])),
                nominalUrlTemplate: @js(route('admin.jenis-tagihan.nominal', ['jenisTagihan' => '__ID__'])),
            })"
            class="space-y-5"
        >
            @can('jenis-tagihan.create')
                <div x-ref="formCard" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                    <p class="font-display text-sm font-bold text-gray-900" x-text="editingId === null ? 'Tambah Jenis Tagihan' : 'Edit Jenis Tagihan'"></p>
                    <form @submit.prevent="submit()" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Nama" />
                            <x-text-input type="text" x-model="form.nama" placeholder="mis. Biaya Pendaftaran" class="mt-1.5" />
                            <p class="mt-1.5 text-sm text-error-600" x-show="errors.nama" x-text="errors.nama?.[0]"></p>
                        </div>
                        <div>
                            <x-input-label value="Kategori" />
                            <select x-model="form.kategori" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="pendaftaran">Pendaftaran</option>
                                <option value="daftar_ulang">Daftar Ulang</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                            <p class="mt-1.5 text-sm text-error-600" x-show="errors.kategori" x-text="errors.kategori?.[0]"></p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" x-model="form.bisa_dicicil" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                Bisa dicicil
                            </label>
                            <div x-show="form.bisa_dicicil" x-cloak class="mt-2 max-w-[160px]">
                                <x-input-label value="Maksimal Jumlah Cicilan" />
                                <x-text-input type="number" min="2" x-model="form.maks_cicilan" class="mt-1.5" />
                            </div>
                        </div>
                        <div class="flex items-center gap-3 sm:col-span-2">
                            <x-primary-button type="submit" x-bind:disabled="submitting" x-text="editingId === null ? 'Tambah' : 'Simpan'"></x-primary-button>
                            <x-secondary-button type="button" x-show="editingId !== null" @click="cancelEdit()">Batal</x-secondary-button>
                        </div>
                    </form>
                </div>
            @endcan

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-200 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Jenis Tagihan</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">Nama</th>
                                <th class="px-5 py-3">Kategori</th>
                                <th class="px-5 py-3">Cicilan</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-if="items.length === 0">
                                <tr><td colspan="5" class="px-5 py-6 text-center text-sm text-gray-500">Belum ada jenis tagihan.</td></tr>
                            </template>
                            <template x-for="item in items" :key="item.id">
                                <tr class="transition hover:bg-gray-50">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                        <x-table-actions>
                                            @can('jenis-tagihan.edit')
                                                <x-dropdown-link href="#" @click.prevent="startEdit(item)">
                                                    <span class="inline-flex items-center gap-2.5">
                                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                        Edit
                                                    </span>
                                                </x-dropdown-link>
                                                <x-dropdown-link x-bind:href="nominalUrl(item)">Kelola Nominal</x-dropdown-link>
                                            @endcan
                                            @can('jenis-tagihan.delete')
                                                <x-dropdown-link href="#" @click.prevent="deleteItem(item)" class="text-error-600">Hapus</x-dropdown-link>
                                            @endcan
                                        </x-table-actions>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="{ pendaftaran: 'Pendaftaran', daftar_ulang: 'Daftar Ulang', lainnya: 'Lainnya' }[item.kategori]"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="item.bisa_dicicil ? 'Maks ' + item.maks_cicilan + 'x' : 'Tidak dicicil'"></td>
                                    <td class="px-5 py-3.5">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="item.tagihan_item_count > 0 ? 'bg-brand-50 text-brand-600' : (item.nominal_jalur_count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600')"
                                            x-text="item.tagihan_item_count > 0 ? 'Dipakai di ' + item.tagihan_item_count + ' Tagihan' : (item.nominal_jalur_count > 0 ? item.nominal_jalur_count + ' Nominal Dikonfigurasi' : 'Belum Dipakai')"
                                        ></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 4: Delete the retired Blade files**

```bash
git rm resources/views/admin/jenis-tagihan/create.blade.php resources/views/admin/jenis-tagihan/edit.blade.php
```

- [ ] **Step 5: Build the frontend**

Run: `npm run build`

Expected: Build completes with no errors.

- [ ] **Step 6: Run the backend test suite to confirm no regressions**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php`

Expected: All tests PASS — including `'only lists jenis tagihan belonging to the acting lembaga-scoped user own lembaga'`, which does `assertSee('Punya A')`/`assertDontSee('Punya B')`; this still works because `@js($jenisTagihanList)` embeds each item's `nama` as a literal substring inside the page's `x-data` JSON payload.

- [ ] **Step 7: Commit**

```bash
git add resources/js/jenis-tagihan-table.js resources/js/app.js resources/views/admin/jenis-tagihan/index.blade.php
git commit -m "feat: redesign jenis tagihan into a TailAdmin datatable with an inline add/edit form"
```

---

### Task 9: Visual re-skin of `nominal.blade.php`

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/nominal.blade.php`

This page stays a separate destination (reached via the "Kelola Nominal" action). Only its visual tokens change to match TailAdmin — its logic (form fields, submission) is untouched.

- [ ] **Step 1: Read the current file to confirm its exact current field markup before editing**

Run: read `resources/views/admin/jenis-tagihan/nominal.blade.php` with the Read tool immediately before this step, since its exact current form-field structure (loop variable names, input names) must be preserved byte-for-byte — only the surrounding layout/CSS classes change.

- [ ] **Step 2: Replace the layout chrome, keep the form logic identical**

Wrap the page content in the same structure used by Tasks 7–8's index pages: replace any `<x-slot name="header">` with an inline breadcrumb block (`<h1 class="font-display text-lg font-bold text-gray-900">...</h1>` + `Beranda &rsaquo; ...`), replace `<x-panel>` wrappers with `<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">`, replace any `text-ink`/`text-slate`/`bg-signal-*` token classes with their TailAdmin equivalents (`text-gray-900`/`text-gray-500`/`bg-success-50 text-success-700` / `bg-error-50 text-error-700`), and add a "Kembali ke Jenis Tagihan" link back to `route('admin.jenis-tagihan.index')` at the top (since there is no longer a "Kelola" edit page to return to). Do not change the `<form>` action, method, field `name` attributes, the `@foreach` loop variable, or any validation-error binding — only surrounding markup/classes.

- [ ] **Step 3: Run the relevant tests**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php`

Expected: All tests PASS — `'lets admin_keuangan set nominal per jalur...'` and the two `kategori lainnya` nominal tests exercise this page's controller logic and must be unaffected by a pure visual change.

- [ ] **Step 4: Build the frontend**

Run: `npm run build`

Expected: Build completes with no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/jenis-tagihan/nominal.blade.php
git commit -m "style: re-skin kelola nominal page to match TailAdmin tokens"
```

---

### Task 10: Final verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full backend test suite**

Run: `php artisan test`

Expected: All tests pass, no regressions anywhere in the app.

- [ ] **Step 2: Run the frontend build**

Run: `npm run build`

Expected: Build completes cleanly.

- [ ] **Step 3: Manual browser verification — Jenis Tes**

Log in as a lembaga-scoped admin with `jenis-tes.*` permissions and visit `/admin/jenis-tes`. Verify: the table renders with the "Tidak Dipakai" / "Dipakai di N Seleksi" badges correct for existing rows; clicking "Edit" scrolls to and pre-fills the form (title switches to "Edit Jenis Tes", "Batal" appears); submitting an edit updates the row in place without a page reload and shows a success toast; clicking "Hapus" opens the themed confirm dialog (not a native browser confirm), and confirms/cancels correctly; attempting to delete a jenis tes that has a seleksi jadwal shows the count-based error toast and the row remains.

- [ ] **Step 4: Manual browser verification — Jenis Tagihan**

Visit `/admin/jenis-tagihan`. Verify: the table shows the three-state status badge correctly (Belum Dipakai / N Nominal Dikonfigurasi / Dipakai di N Tagihan); adding a new "Pendaftaran" or "Daftar Ulang" jenis tagihan automatically navigates to its Kelola Nominal page after creation; adding a "Lainnya" jenis tagihan stays on the index and resets the form to add-mode; "Kelola Nominal" in the Aksi menu navigates to the (now re-skinned) nominal page; editing an existing row pre-fills the form including the conditional "Maksimal Jumlah Cicilan" field when "Bisa dicicil" is checked; deleting a jenis tagihan that already has nominal configured or real tagihan rows is blocked with the correct message.

- [ ] **Step 5: Report results**

If any manual check fails, fix the underlying issue and re-run the relevant automated tests plus this manual check before proceeding.

---

### Task 11: Final whole-branch code review

**Files:** none (review only)

- [ ] **Step 1: Review the full diff against `main`**

Run: `git diff main --stat` then review the full diff, checking for: leftover references to the removed `jenis-tagihan.create`/`jenis-tagihan.edit` routes anywhere in the codebase (views, controllers, tests); consistent error-message wording between Jenis Tes and Jenis Tagihan; no `<x-badge>` usage inside any `x-for` loop; every new controller method properly gated by `$this->authorize(...)`; the `tagihan_item` migration's `down()` correctly reverses to `cascadeOnDelete()`.

- [ ] **Step 2: Fix any issues found, with tests, then commit**

If issues are found, fix them following the same TDD pattern as the tasks above (failing test first where applicable), then commit the fix separately.

---

### Task 12: Finish development branch

**Files:** none

- [ ] **Step 1: Confirm all commits are in place**

Run: `git log --oneline main..HEAD` (or equivalent for the current branch) to confirm every task's commit is present.

- [ ] **Step 2: Decide merge/PR path with the user**

Ask the user whether to merge directly, open a PR, or leave the branch as-is, matching how prior plans in this codebase (e.g. the Jalur PPDB partial-safety plan) concluded — do not merge or push without explicit confirmation.
