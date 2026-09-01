# Keuangan — KategoriTagihan Enum, tagihan.person_id, UI Keringanan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship 4 independently-verified Keuangan improvements in one locked order — (A) convert `kategori` to a PHP Backed Enum, (B) centralize the now-redundant `PPDB_KATEGORI` check, (C) add `tagihan.person_id` for cross-lifecycle ledger continuity, (D) build the missing Keringanan assignment UI — without ever creating a helper that a later stage deletes.

**Architecture:** Stage 1 casts `jenis_tagihan.kategori`/`tagihan.kategori` to `App\Domains\Keuangan\Enums\KategoriTagihan` and fixes every call site that breaks under that cast (13 points, one of them a fatal crash). Stage 2 reuses the enum's `isPpdb()` to finish the two remaining query-builder consolidation points — no new helper class. Stage 3 adds a denormalized `tagihan.person_id` column (never mutating the existing `tagihable_type`/`tagihable_id` polymorphic pair), backfills it, and wires a synchronous domain event so `MergePersonsAction` keeps Keuangan's ledger consistent without Identity knowing Keuangan's schema. Stage 4 adds the missing `SiswaKeringananController` CRUD + view, independent of the other three.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4, MySQL.

## Global Constraints

These come from `.agents/specs/2026-09-01-keuangan-kategori-tagihan-backed-enum.md` and `.agents/specs/2026-09-01-keuangan-tagihan-person-id-keringanan-ui.md` (both on branch `keuangan-v2`) and are binding on every task below:

- **Execution order is locked**: Stage 1 (enum) must be 100% complete with a green full test suite before Stage 2 starts. Stage 2 before Stage 3. Stage 4 is independent and may run any time after Stage 1 (it touches none of the same files), but is sequenced last here for a single linear checklist.
- **`JenisTagihan::isPpdbKategori()` is never created.** Every consumer that needs a PPDB check calls `$kategori->isPpdb()` (on a `KategoriTagihan` instance) or, for raw HTTP input, `KategoriTagihan::tryFrom($input)?->isPpdb() ?? false`.
- **`kategori` keeps all 7 values** (`pendaftaran`, `daftar_ulang`, `spp`, `tahunan`, `kegiatan`, `custom`, `lainnya`). Do not reduce it to 2 values — it doubles as an accounting/reporting label, not just a PPDB-routing discriminator.
- **No DB migration to change the `kategori` column type.** It is already a native MySQL `ENUM(...)` on both `jenis_tagihan` and `tagihan` — Stage 1 is Eloquent-cast-only.
- **`tagihable_type`/`tagihable_id` on `tagihan` are never mutated.** `person_id` is purely additive, resolved once at tagihan-creation time from the `tagihable`'s own `person_id`.
- **`tagihan.person_id` FK uses `ON DELETE RESTRICT`**, matching the 4 existing `persons` FKs from identity-v1 (`guru`, `karyawan`, `orang_tua`, `siswa`).
- **The `PersonsMerged` event and its Keuangan listener must NOT implement `ShouldQueue`.** They must run synchronously inside `MergePersonsAction`'s existing `DB::transaction()` closure, so a listener exception rolls back the entire merge (including the `Person` update), not just log an error.
- **Backfill/verify commands must skip-and-log failed rows, never throw or silently default.** A `tagihan` row whose `tagihable` can't be resolved to a `person_id` stays `NULL` and is reported, not force-filled.
- **`SiswaKeringananController::destroy()` hard-deletes** (not soft-delete) — `SiswaKeringanan` is a pure assignment record, not financial data needing an audit trail.

---

## Stage 1 — `KategoriTagihan` Backed Enum (Tasks 1–9)

### Task 1: Create `KategoriTagihan` enum

**Files:**
- Create: `app/Domains/Keuangan/Enums/KategoriTagihan.php`
- Test: `tests/Unit/Keuangan/KategoriTagihanTest.php`

**Interfaces:**
- Produces: `App\Domains\Keuangan\Enums\KategoriTagihan` (string-backed enum, 7 cases), `->isPpdb(): bool`, `->label(): string`. Every later task in Stage 1–3 depends on this exact class and method names.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Enums\KategoriTagihan;

it('reports isPpdb true only for pendaftaran and daftar_ulang', function () {
    expect(KategoriTagihan::Pendaftaran->isPpdb())->toBeTrue();
    expect(KategoriTagihan::DaftarUlang->isPpdb())->toBeTrue();
    expect(KategoriTagihan::Spp->isPpdb())->toBeFalse();
    expect(KategoriTagihan::Tahunan->isPpdb())->toBeFalse();
    expect(KategoriTagihan::Kegiatan->isPpdb())->toBeFalse();
    expect(KategoriTagihan::Custom->isPpdb())->toBeFalse();
    expect(KategoriTagihan::Lainnya->isPpdb())->toBeFalse();
});

it('has a label for every case', function () {
    expect(KategoriTagihan::Pendaftaran->label())->toBe('Pendaftaran');
    expect(KategoriTagihan::DaftarUlang->label())->toBe('Daftar Ulang');
    expect(KategoriTagihan::Spp->label())->toBe('SPP');
    expect(KategoriTagihan::Tahunan->label())->toBe('Tahunan');
    expect(KategoriTagihan::Kegiatan->label())->toBe('Kegiatan');
    expect(KategoriTagihan::Custom->label())->toBe('Custom');
    expect(KategoriTagihan::Lainnya->label())->toBe('Lainnya');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KategoriTagihanTest`
Expected: FAIL (class not found)

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Domains\Keuangan\Enums;

enum KategoriTagihan: string
{
    case Pendaftaran = 'pendaftaran';
    case DaftarUlang = 'daftar_ulang';
    case Spp = 'spp';
    case Tahunan = 'tahunan';
    case Kegiatan = 'kegiatan';
    case Custom = 'custom';
    case Lainnya = 'lainnya';

    public function isPpdb(): bool
    {
        return in_array($this, [self::Pendaftaran, self::DaftarUlang], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendaftaran => 'Pendaftaran',
            self::DaftarUlang => 'Daftar Ulang',
            self::Spp => 'SPP',
            self::Tahunan => 'Tahunan',
            self::Kegiatan => 'Kegiatan',
            self::Custom => 'Custom',
            self::Lainnya => 'Lainnya',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=KategoriTagihanTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Keuangan/Enums/KategoriTagihan.php tests/Unit/Keuangan/KategoriTagihanTest.php
git commit -m "feat(keuangan): add KategoriTagihan backed enum"
```

---

### Task 2: Cast `kategori` on `JenisTagihan` and `Tagihan`

**Files:**
- Modify: `app/Domains/Keuangan/Models/JenisTagihan.php`
- Modify: `app/Domains/Keuangan/Models/Tagihan.php`
- Test: `tests/Feature/Keuangan/KategoriTagihanCastTest.php`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Enums\KategoriTagihan` (Task 1).
- Produces: `$jenisTagihan->kategori` and `$tagihan->kategori` now return `KategoriTagihan` instances, not strings. Every later task in Stage 1 assumes this is already live.

**⚠️ After this task, every one of the 13 breakage points in Tasks 3–8 is live and broken until fixed.** Run the full Keuangan test subset immediately after this task to see them fail (expected), and again after each subsequent task to watch them turn green.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Enums\KategoriTagihan;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;

it('casts jenis_tagihan.kategori to KategoriTagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp']);

    expect($jenisTagihan->fresh()->kategori)->toBe(KategoriTagihan::Spp);
});

it('serializes the enum cast back to its raw string value in toArray/toJson', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp']);

    expect($jenisTagihan->fresh()->toArray()['kategori'])->toBe('spp');
    expect(json_decode($jenisTagihan->fresh()->toJson(), true)['kategori'])->toBe('spp');
});

it('accepts a plain string on create/update despite the cast', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'tahunan']);

    expect($jenisTagihan->fresh()->kategori)->toBe(KategoriTagihan::Tahunan);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KategoriTagihanCastTest`
Expected: FAIL (`kategori` still returns a plain string, `toBe(KategoriTagihan::Spp)` fails)

- [ ] **Step 3: Add the cast to both models**

In `app/Domains/Keuangan/Models/JenisTagihan.php`, inside the existing `casts()` method, add:

```php
'kategori' => \App\Domains\Keuangan\Enums\KategoriTagihan::class,
```

In `app/Domains/Keuangan/Models/Tagihan.php`, inside its existing `casts()` method, add the same line.

(Read each file's current `casts()` method first — add this line alongside the existing entries, do not replace the method.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=KategoriTagihanCastTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Keuangan/Models/JenisTagihan.php app/Domains/Keuangan/Models/Tagihan.php tests/Feature/Keuangan/KategoriTagihanCastTest.php
git commit -m "feat(keuangan): cast kategori to KategoriTagihan on JenisTagihan and Tagihan"
```

---

### Task 3: Fix the crash point — `JenisTagihanController.php:232`

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`
- Test: `tests/Feature/Keuangan/JenisTagihanController/ProsesBillingKategoriCrashTest.php`

**Interfaces:**
- Consumes: enum cast from Task 2.

This is the highest-priority fix: string-interpolating a `BackedEnum` (`"{$jenisTagihan->kategori}"`) throws a fatal `Error` because `BackedEnum` does not implement `__toString()`. Before Task 2, this line silently worked (plain string); after Task 2, hitting this code path returns HTTP 500 instead of the intended 422.

- [ ] **Step 1: Write the failing test**

Find the route that reaches this controller method (the one guarding non-PPDB `JenisTagihan` from the billing engine — read lines 220–240 of the controller to confirm the exact route name/method before writing the test). Write a feature test that triggers this exact branch with a PPDB-category `JenisTagihan` and asserts a 422 response with the expected message — not a 500.

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\User;

it('returns 422, not a fatal error, when processing billing for a PPDB-category jenis tagihan', function () {
    $user = User::factory()->create(); // adjust to match this controller's actual auth/permission setup
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'pendaftaran']);

    $response = $this->actingAs($user)->postJson(
        route('lembaga.keuangan.jenis-tagihan.proses-billing', $jenisTagihan) // confirm exact route name from route:list
    );

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => "Jenis tagihan berkategori Pendaftaran tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB."]);
});
```

Adjust the auth setup and route name to match what `php artisan route:list --path=jenis-tagihan` and the controller's existing tests (`tests/Feature/Admin/JenisTagihanProsesTest.php`) already use as a pattern.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProsesBillingKategoriCrashTest`
Expected: FAIL with 500 (fatal `Error: Object of class KategoriTagihan could not be converted to string`)

- [ ] **Step 3: Fix the interpolation**

In `JenisTagihanController.php` line 232, change:

```php
'message' => "Jenis tagihan berkategori {$jenisTagihan->kategori} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB.",
```

to:

```php
'message' => "Jenis tagihan berkategori {$jenisTagihan->kategori->label()} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB.",
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProsesBillingKategoriCrashTest`
Expected: PASS (422 with the correct message)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php tests/Feature/Keuangan/JenisTagihanController/ProsesBillingKategoriCrashTest.php
git commit -m "fix(keuangan): stop enum string-interpolation crash in JenisTagihanController"
```

---

### Task 4: Fix the 6 `in_array($model->kategori, ...)` model-attribute comparisons

**Files:**
- Modify: `app/Console/Commands/ProsesTagihan.php:28`
- Modify: `app/Domains/Keuangan/Services/TagihanBillingGenerator.php:122`
- Modify: `app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php:18`
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:230,264,288`
- Test: `tests/Feature/Keuangan/KategoriTagihanIsPpdbConsumersTest.php`

**Interfaces:**
- Consumes: `KategoriTagihan::isPpdb()` (Task 1), enum cast (Task 2).

All 6 sites share the exact same broken pattern (`in_array($x->kategori, self::PPDB_KATEGORI, true)`, always `false` post-cast because comparing an enum object against string array elements) and the exact same fix. `self::PPDB_KATEGORI` still exists on all 4 classes at this point — do not remove it yet (Task 9 removes it, after every referencing site is fixed).

- [ ] **Step 1: Write the failing tests**

Each existing feature/unit test file already covering these 4 classes should have a test that exercises the PPDB-vs-non-PPDB branch. Read `tests/Feature/Keuangan/ProsesTagihanCommandTest.php`, `tests/Feature/Keuangan/TagihanBillingGeneratorTest.php`, and `tests/Feature/Admin/JenisTagihanProsesTest.php` first — if a test already asserts this branching, it should now be RED (post Task 2) and you extend/confirm it; if none exists, add one per class to the file below:

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;

it('ProsesTagihan command still treats pendaftaran/daftar_ulang as PPDB after the enum cast', function () {
    // Exercise app/Console/Commands/ProsesTagihan.php's PPDB branch directly via
    // artisan call, asserting the PPDB path is taken for kategori=pendaftaran
    // and the non-PPDB path for kategori=spp. Mirror the assertions already
    // used in tests/Feature/Keuangan/ProsesTagihanCommandTest.php.
});

it('TagihanBillingGenerator still rejects billing generation for PPDB categories after the enum cast', function () {
    // Mirror the existing assertBillable() rejection test in
    // tests/Feature/Keuangan/TagihanBillingGeneratorTest.php for kategori=pendaftaran.
});

it('GenerateTagihanForActivatedBillType listener still treats PPDB categories correctly after the enum cast', function () {
    // Mirror whatever existing coverage exists for this listener's PPDB branch.
});

it('JenisTagihanController PPDB-category checks (lines 230, 264, 288) still work after the enum cast', function () {
    // Mirror the assertions already used in tests/Feature/Admin/JenisTagihanProsesTest.php
    // for the three guarded actions at those three lines.
});
```

Write these against the ACTUAL existing test patterns in the 3 named files above (read them first) rather than inventing new assertion styles — the goal is regression parity, not new test design.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=KategoriTagihanIsPpdbConsumersTest`
Expected: FAIL (PPDB branch never taken — `in_array` always returns `false` against the enum object)

- [ ] **Step 3: Fix all 6 sites**

In each of the 4 files, replace:

```php
in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)
```

with:

```php
$jenisTagihan->kategori->isPpdb()
```

(Adjust the variable name per file — it may be `$jenisTagihan`, `$this->jenisTagihan`, or similar; read each exact line first via the line numbers above before editing.) For any site written as `! in_array(...)`, write `! $jenisTagihan->kategori->isPpdb()`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=KategoriTagihanIsPpdbConsumersTest`
Also run the 3 existing suites touched: `php artisan test --filter=ProsesTagihanCommandTest`, `php artisan test --filter=TagihanBillingGeneratorTest`, `php artisan test --filter=JenisTagihanProsesTest`
Expected: all PASS

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ProsesTagihan.php app/Domains/Keuangan/Services/TagihanBillingGenerator.php app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php tests/Feature/Keuangan/KategoriTagihanIsPpdbConsumersTest.php
git commit -m "fix(keuangan): replace in_array PPDB checks with KategoriTagihan::isPpdb()"
```

---

### Task 5: Fix the 2 raw-request-input PPDB checks — `JenisTagihanController.php:129,175`

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`
- Test: `tests/Feature/Keuangan/JenisTagihanController/RequestInputPpdbCheckTest.php`

**Interfaces:**
- Consumes: `KategoriTagihan::tryFrom()` (native PHP backed-enum method), `->isPpdb()` (Task 1).

**Why this is a separate task from Task 4**: lines 129 and 175 compare `$request->input('kategori')` — a raw string, never touched by the Eloquent cast — so they are NOT broken by Task 2 on their own. But Task 9 deletes `self::PPDB_KATEGORI`, which these two lines still reference. Fix them now so no reference to the constant survives past this task, in preparation for Task 9's deletion.

- [ ] **Step 1: Write the failing test**

```php
<?php

it('treats a valid PPDB kategori string from raw request input as PPDB', function () {
    // Call whichever JenisTagihanController action uses line 129 or 175
    // (read the surrounding method first) with kategori=pendaftaran in the
    // request payload, and assert the $isPpdbKategori-dependent behavior
    // (e.g. which validation rule or redirect fires) matches the PPDB path.
});

it('treats an invalid/missing kategori string from raw request input as NOT PPDB, without erroring', function () {
    // Same call with kategori omitted or set to an invalid string (e.g. 'not-a-real-value'),
    // asserting the non-PPDB path is taken and no exception is thrown.
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RequestInputPpdbCheckTest`
Expected: FAIL if the current `in_array` behavior differs, or the test file doesn't exist to establish this baseline yet — either way, confirm current behavior before changing it.

- [ ] **Step 3: Fix both sites**

At both line 129 and line 175, replace:

```php
$isPpdbKategori = in_array($request->input('kategori'), self::PPDB_KATEGORI, true);
```

with:

```php
$isPpdbKategori = \App\Domains\Keuangan\Enums\KategoriTagihan::tryFrom($request->input('kategori'))?->isPpdb() ?? false;
```

(Add the `use App\Domains\Keuangan\Enums\KategoriTagihan;` import at the top of the file instead of using the FQCN inline, then reference it as `KategoriTagihan::tryFrom(...)` — check the file's existing import block first.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RequestInputPpdbCheckTest`
Expected: PASS (both valid and invalid/missing-input cases)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php tests/Feature/Keuangan/JenisTagihanController/RequestInputPpdbCheckTest.php
git commit -m "fix(keuangan): resolve raw request kategori input via KategoriTagihan::tryFrom before PPDB_KATEGORI deletion"
```

---

### Task 6: Fix the 2 `===` label comparison bugs

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/PembayaranController.php:34`
- Modify: `resources/views/portal/tagihan/index.blade.php:24`
- Test: `tests/Feature/Keuangan/KategoriLabelDisplayTest.php`

**Interfaces:**
- Consumes: `KategoriTagihan::label()` (Task 1), enum cast (Task 2).

Both sites use `$x->kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang'` — a pre-existing latent bug (any kategori that is neither always showed "Tagihan Daftar Ulang") made worse by the cast (now ALWAYS shows "Tagihan Daftar Ulang" for every kategori, since `===` against an enum object is always `false`). Fix uses `label()` for a genuinely correct label per kategori, not just a cast-compatible ternary.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PaymentService;

it('PembayaranController shows the correct kategori label, not always Tagihan Daftar Ulang', function () {
    $tagihan = Tagihan::factory()->create(['kategori' => 'spp']);
    // Drive this through whatever existing test in
    // tests/Feature/Keuangan/PembayaranTagihanTest.php exercises this label,
    // asserting the rendered/returned label is 'SPP'-derived, not
    // 'Tagihan Daftar Ulang'.
});

it('portal/tagihan/index shows the correct kategori label for non-PPDB tagihan', function () {
    // Render the portal tagihan index view for a logged-in ortu/siswa with a
    // tagihan of kategori=spp, assert the page shows a label derived from
    // 'SPP', not 'Tagihan Daftar Ulang'.
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KategoriLabelDisplayTest`
Expected: FAIL (label shows "Tagihan Daftar Ulang" regardless of actual kategori)

- [ ] **Step 3: Fix both sites**

`PembayaranController.php:34`, replace:

```php
$label = $pembayaran->tagihan->kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang';
```

with:

```php
$label = $pembayaran->tagihan->kategori->label();
```

`resources/views/portal/tagihan/index.blade.php:24`, replace:

```blade
{{ $tagihan->kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang' }}
```

with:

```blade
{{ $tagihan->kategori->label() }}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=KategoriLabelDisplayTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/PembayaranController.php resources/views/portal/tagihan/index.blade.php tests/Feature/Keuangan/KategoriLabelDisplayTest.php
git commit -m "fix(keuangan): use KategoriTagihan::label() instead of binary === ternary"
```

---

### Task 7: Fix the `_daftar.blade.php` `in_array` and `@switch` sites

**Files:**
- Modify: `resources/views/portals/lembaga/keuangan/jenis-tagihan/_daftar.blade.php`
- Test: `tests/Feature/Keuangan/JenisTagihanDaftarViewTest.php`

**Interfaces:**
- Consumes: `KategoriTagihan::isPpdb()`, `->label()` (Task 1).

Line 19's `in_array($item->kategori, ['pendaftaran', 'daftar_ulang'])` (loose comparison) controls which action links show. Lines 33–40's `@switch($item->kategori) @case('pendaftaran') ... @default Lainnya @endswitch` will render "Lainnya" for EVERY row post-cast, since PHP `switch`/`case` never matches an enum object against a string case.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\JenisTagihan;

it('jenis tagihan list shows the correct kategori column and action links per row', function () {
    $ppdb = JenisTagihan::factory()->create(['kategori' => 'pendaftaran']);
    $nonPpdb = JenisTagihan::factory()->create(['kategori' => 'spp']);

    $response = $this->actingAs(/* admin lembaga user */)
        ->get(route('lembaga.keuangan.jenis-tagihan.index'));

    $response->assertSee('Pendaftaran'); // ppdb row's kategori column, not "Lainnya"
    $response->assertSee('SPP');         // non-ppdb row's kategori column, not "Lainnya"
    // Assert the PPDB row shows the "Kelola Nominal" action and the non-PPDB
    // row shows "Proses Tagihan"/"Monitoring" (read the existing markup at
    // line 19's surrounding @if block to confirm exact link text first).
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanDaftarViewTest`
Expected: FAIL (every row shows "Lainnya", action links wrong for both rows)

- [ ] **Step 3: Fix both sites**

Line 19, replace:

```blade
@if (in_array($item->kategori, ['pendaftaran', 'daftar_ulang']))
```

with:

```blade
@if ($item->kategori->isPpdb())
```

Lines 33–40, replace the entire `@switch(...)...@endswitch` block with:

```blade
{{ $item->kategori->label() }}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanDaftarViewTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/portals/lembaga/keuangan/jenis-tagihan/_daftar.blade.php tests/Feature/Keuangan/JenisTagihanDaftarViewTest.php
git commit -m "fix(keuangan): use KategoriTagihan::isPpdb()/label() in jenis-tagihan list view"
```

---

### Task 8: Fix `spmb-pendaftaran/show.blade.php:200` `firstWhere` comparison

**Files:**
- Modify: `resources/views/admin/spmb-pendaftaran/show.blade.php`
- Test: `tests/Feature/Keuangan/SpmbPendaftaranTagihanCardsTest.php`

**Interfaces:**
- Consumes: `KategoriTagihan::from()` (Task 1).

`Collection::firstWhere('kategori', $kategori)` uses loose `==` internally, comparing an enum instance against the loop's plain string key — always `false` post-cast, meaning both "Tagihan Pendaftaran" and "Tagihan Daftar Ulang" cards on this page always show "Belum ada tagihan" regardless of actual data.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\Tagihan;

it('SPMB pendaftaran detail page shows the actual pendaftaran and daftar ulang tagihan, not always Belum ada tagihan', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class,
        'tagihable_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 500000,
    ]);

    $response = $this->actingAs(/* admin user */)
        ->get(route('admin.spmb-pendaftaran.show', $pendaftaran));

    $response->assertDontSee('Belum ada tagihan', escape: false);
    // Assert the specific tagihan's amount/status renders on the Pendaftaran card.
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SpmbPendaftaranTagihanCardsTest`
Expected: FAIL (page shows "Belum ada tagihan" despite the tagihan existing)

- [ ] **Step 3: Fix the comparison**

Line 200-201, replace:

```blade
@forelse (['pendaftaran' => 'Tagihan Pendaftaran', 'daftar_ulang' => 'Tagihan Daftar Ulang'] as $kategori => $label)
    @php $tagihan = $pendaftaran->tagihan->firstWhere('kategori', $kategori); @endphp
```

with:

```blade
@forelse (['pendaftaran' => 'Tagihan Pendaftaran', 'daftar_ulang' => 'Tagihan Daftar Ulang'] as $kategori => $label)
    @php $tagihan = $pendaftaran->tagihan->first(fn ($t) => $t->kategori === \App\Domains\Keuangan\Enums\KategoriTagihan::from($kategori)); @endphp
```

(Read the exact surrounding lines first — the `@php` block structure may differ slightly; preserve everything else in the loop body unchanged.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SpmbPendaftaranTagihanCardsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/spmb-pendaftaran/show.blade.php tests/Feature/Keuangan/SpmbPendaftaranTagihanCardsTest.php
git commit -m "fix(keuangan): compare KategoriTagihan enum-to-enum in SPMB pendaftaran tagihan cards"
```

---

### Task 9: Delete the 4 duplicated `PPDB_KATEGORI` constants + full test suite

**Files:**
- Modify: `app/Console/Commands/ProsesTagihan.php`
- Modify: `app/Domains/Keuangan/Services/TagihanBillingGenerator.php`
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`
- Modify: `app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php`

**Interfaces:**
- Consumes: every fix from Tasks 4–5 (all references to `self::PPDB_KATEGORI` in these 4 files must already be gone before this task starts).

This task has no new test of its own — it's a pure deletion, verified by the full suite staying green. If any reference to `self::PPDB_KATEGORI` still exists in one of these 4 files, deleting the constant produces a fatal `Undefined constant` error, which the full suite will catch.

- [ ] **Step 1: Grep to confirm no live references remain**

Run: `grep -rn "PPDB_KATEGORI" app/Console/Commands/ProsesTagihan.php app/Domains/Keuangan/Services/TagihanBillingGenerator.php app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php`
Expected: only the 4 `private const PPDB_KATEGORI = [...]` declaration lines themselves — zero usage sites (Tasks 4 and 5 should have eliminated all of them).

- [ ] **Step 2: Delete the 4 constant declarations**

Remove the `private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];` line from each of the 4 files.

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS, 0 failures. If anything fails with "Undefined constant PPDB_KATEGORI", Step 1's grep missed a reference — find it and fix it (following the same pattern as Task 4 or 5, whichever matches: model-attribute comparison or raw-request-input comparison) before re-running.

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/ProsesTagihan.php app/Domains/Keuangan/Services/TagihanBillingGenerator.php app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php
git commit -m "refactor(keuangan): delete duplicated PPDB_KATEGORI constants, fully replaced by KategoriTagihan enum"
```

**Stage 1 complete when this task's full suite run is green.** Do not start Stage 2 before this.

---

## Stage 2 — Finish Sentralisasi `PPDB_KATEGORI` (Task 10)

### Task 10: Replace literal-string PPDB arrays in the 2 remaining listeners with `KategoriTagihan`

**Files:**
- Modify: `app/Domains/Keuangan/Listeners/GenerateTagihanForUpdatedClass.php:27`
- Modify: `app/Domains/Keuangan/Listeners/GenerateTagihanForNewStudent.php:27`
- Test: `tests/Feature/Keuangan/GenerateTagihanListenersPpdbConstantTest.php`

**Interfaces:**
- Consumes: `KategoriTagihan::Pendaftaran`, `KategoriTagihan::DaftarUlang` (Task 1).

These 2 sites use `whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])` — a query-builder call operating at the SQL level, unaffected by the enum cast (confirmed safe in the Stage 1 spec's audit). This task is pure consolidation for a single source of truth, not a bug fix — no behavior changes.

- [ ] **Step 1: Write the failing test (asserting current behavior, to pin it before refactor)**

```php
<?php

it('GenerateTagihanForUpdatedClass still excludes pendaftaran/daftar_ulang after switching to KategoriTagihan values', function () {
    // Mirror whatever existing test exercises this listener's whereNotIn
    // filter, confirming the excluded set is still exactly
    // ['pendaftaran', 'daftar_ulang'] after the refactor.
});

it('GenerateTagihanForNewStudent still excludes pendaftaran/daftar_ulang after switching to KategoriTagihan values', function () {
    // Same, for the sibling listener.
});
```

- [ ] **Step 2: Run test to verify it passes against CURRENT code first**

Run: `php artisan test --filter=GenerateTagihanListenersPpdbConstantTest`
Expected: PASS (this test pins existing behavior before the refactor — it should already pass before Step 3)

- [ ] **Step 3: Refactor both sites**

In both files, line 27, replace:

```php
->whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])
```

with:

```php
->whereNotIn('kategori', [\App\Domains\Keuangan\Enums\KategoriTagihan::Pendaftaran->value, \App\Domains\Keuangan\Enums\KategoriTagihan::DaftarUlang->value])
```

(Add a proper `use` import at the top of each file instead of the inline FQCN, matching the file's existing import style.)

- [ ] **Step 4: Run test to verify it still passes**

Run: `php artisan test --filter=GenerateTagihanListenersPpdbConstantTest`
Expected: PASS, unchanged (this task changes the source of truth, not the resulting SQL values)

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Keuangan/Listeners/GenerateTagihanForUpdatedClass.php app/Domains/Keuangan/Listeners/GenerateTagihanForNewStudent.php tests/Feature/Keuangan/GenerateTagihanListenersPpdbConstantTest.php
git commit -m "refactor(keuangan): source PPDB category values from KategoriTagihan enum in class-change listeners"
```

**Stage 2 complete.** §5 of the person-id/keringanan spec is now fully closed — no `JenisTagihan::isPpdbKategori()` was ever created.

---

## Stage 3 — `tagihan.person_id` (Tasks 11–20)

### Task 11: Migration — add `tagihan.person_id` (nullable)

**Files:**
- Create: `database/migrations/2026_09_01_000001_add_person_id_to_tagihan_table.php`
- Test: `tests/Feature/Keuangan/TagihanPersonIdColumnTest.php`

**Interfaces:**
- Produces: `tagihan.person_id` (nullable `BIGINT UNSIGNED`, no FK yet). Tasks 12–15 write to this column; Task 20 adds the NOT NULL constraint + FK.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\Tagihan;

it('tagihan table has a nullable person_id column', function () {
    $tagihan = Tagihan::factory()->create();

    expect($tagihan->getAttributes())->toHaveKey('person_id');
    expect(Tagihan::factory()->make(['person_id' => null])->person_id)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TagihanPersonIdColumnTest`
Expected: FAIL (column doesn't exist)

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('tagihable_id');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropColumn('person_id');
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TagihanPersonIdColumnTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_01_000001_add_person_id_to_tagihan_table.php tests/Feature/Keuangan/TagihanPersonIdColumnTest.php
git commit -m "feat(keuangan): add nullable tagihan.person_id column"
```

---

### Task 12: Populate `person_id` in `TagihanGenerator` (Pendaftaran path)

**Files:**
- Modify: `app/Services/TagihanGenerator.php:56`
- Test: `tests/Feature/Keuangan/TagihanGeneratorPersonIdTest.php`

**Interfaces:**
- Consumes: `Pendaftaran::calonMurid(): BelongsTo` (existing), `CalonMurid::person_id` (existing, from identity-v1).

**Production write path vs. backfill — different failure contract.** The backfill command (Task 19) is explicitly allowed to skip-and-log a row it can't resolve, because it's cleaning up OLD data that may predate identity-v1. This task is the opposite: it's the NEW-tagihan creation path. A `Pendaftaran` reaching this code with no resolvable `calonMurid->person_id` means the data is already corrupt (identity-v1 guarantees every `Pendaftaran`'s `CalonMurid` has a `person_id`) — this must throw hard and loud, not silently create a `tagihan` row with `person_id = NULL` (which the NOT NULL constraint from Task 20 would reject anyway, but only AFTER this generator already ran — better to fail at the source with a clear message).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Services\TagihanGenerator;
use App\Models\Pendaftaran;
use App\Models\CalonMurid;

it('TagihanGenerator fills tagihan.person_id from pendaftaran.calonMurid.person_id', function () {
    $calonMurid = CalonMurid::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['calon_murid_id' => $calonMurid->id]); // adjust FK name to actual schema

    app(TagihanGenerator::class)->generateFor($pendaftaran); // adjust to the generator's actual public method name

    $tagihan = $pendaftaran->tagihan()->first(); // adjust relation name if different
    expect($tagihan->person_id)->toBe($calonMurid->person_id);
});

it('throws hard, instead of creating a tagihan with a null person_id, when pendaftaran has no resolvable calonMurid', function () {
    $pendaftaran = Pendaftaran::factory()->create(['calon_murid_id' => null]); // or however this project models a Pendaftaran with no CalonMurid link

    expect(fn () => app(TagihanGenerator::class)->generateFor($pendaftaran))
        ->toThrow(\RuntimeException::class);

    $this->assertDatabaseMissing('tagihan', ['tagihable_type' => Pendaftaran::class, 'tagihable_id' => $pendaftaran->id]);
});
```

Read `app/Services/TagihanGenerator.php` in full first to confirm the exact public method name, how `$pendaftaran` reaches line 56's `Tagihan::create()` call, and whether a `Pendaftaran` factory state already exists for "no linked CalonMurid" — adjust both tests' setup/invocation to match exactly.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TagihanGeneratorPersonIdTest`
Expected: both FAIL (first: `person_id` is `null`; second: no exception thrown, or a generic/unclear one)

- [ ] **Step 3: Resolve `person_id` with a hard failure guard, then use it in the create call**

Immediately before the `Tagihan::create([...])` call at line 56, add:

```php
$personId = $pendaftaran->calonMurid?->person_id
    ?? throw new \RuntimeException("Tidak bisa membuat tagihan: Pendaftaran #{$pendaftaran->id} tidak punya CalonMurid dengan person_id yang valid — data kemungkinan cacat.");
```

Then inside the `Tagihan::create([...])` array, add:

```php
'person_id' => $personId,
```

(Do not use `$pendaftaran->calonMurid->person_id` directly without the guard — that would throw PHP's generic `Error: Attempt to read property "person_id" on null` when `calonMurid` is missing, which works but gives a far less useful message than the explicit `RuntimeException` above. Use the guard.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=TagihanGeneratorPersonIdTest`
Expected: both PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TagihanGenerator.php tests/Feature/Keuangan/TagihanGeneratorPersonIdTest.php
git commit -m "feat(keuangan): populate tagihan.person_id in TagihanGenerator"
```

---

### Task 13: Populate `person_id` in `TagihanBillingGenerator` (Siswa path)

**Files:**
- Modify: `app/Domains/Keuangan/Services/TagihanBillingGenerator.php:70`
- Test: `tests/Feature/Keuangan/TagihanBillingGeneratorPersonIdTest.php`

**Interfaces:**
- Consumes: `Siswa::person_id` (existing, from identity-v1).

**Same hard-failure contract as Task 12** — see that task's note. A `Siswa` row with a null `person_id` should be impossible today (identity-v1's `siswa.person_id` is NOT NULL after Task 28), but this generator is a production write path, not the backfill, so it must not rely on that constraint alone and must not silently write a null.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Keuangan\Services\TagihanBillingGenerator;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\JenisTagihan;

it('TagihanBillingGenerator fills tagihan.person_id from siswa.person_id directly', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp']);

    app(TagihanBillingGenerator::class)->generateForSiswa($siswa, $jenisTagihan); // confirm exact method signature from the file

    $tagihan = $siswa->tagihan()->where('jenis_tagihan_id', $jenisTagihan->id)->first();
    expect($tagihan->person_id)->toBe($siswa->person_id);
});

it('throws hard, instead of creating a tagihan with a null person_id, when siswa.person_id is null', function () {
    $siswa = Siswa::factory()->create();
    $siswa->forceFill(['person_id' => null])->saveQuietly(); // bypass identity-v1's NOT NULL constraint just to simulate corrupt data for this test
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp']);

    expect(fn () => app(TagihanBillingGenerator::class)->generateForSiswa($siswa, $jenisTagihan))
        ->toThrow(\RuntimeException::class);

    $this->assertDatabaseMissing('tagihan', ['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id]);
});
```

If `siswa.person_id` is a DB-level NOT NULL column, `forceFill()->saveQuietly()` may itself throw a `QueryException` before reaching the generator — if so, simulate the corrupt-data scenario at the PHP level instead (e.g. a partial mock/stub of `Siswa` with `person_id` overridden to `null` via `Mockery`), whichever this project's existing test suite already does for "simulate a NOT NULL column somehow null" scenarios. Check `tests/Feature/Identity/` for a precedent before deciding.

Read `app/Domains/Keuangan/Services/TagihanBillingGenerator.php`'s `generateForSiswa()` signature first (already read in prior audit — confirm it hasn't changed) before finalizing both tests' invocation.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TagihanBillingGeneratorPersonIdTest`
Expected: both FAIL

- [ ] **Step 3: Resolve `person_id` with a hard failure guard, then use it in the create call**

Immediately before the `Tagihan::create([...])` call at line 70, add:

```php
$personId = $siswa->person_id
    ?? throw new \RuntimeException("Tidak bisa membuat tagihan: Siswa #{$siswa->id} tidak punya person_id yang valid — data kemungkinan cacat.");
```

Then inside the `Tagihan::create([...])` array, add:

```php
'person_id' => $personId,
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=TagihanBillingGeneratorPersonIdTest`
Expected: both PASS

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Keuangan/Services/TagihanBillingGenerator.php tests/Feature/Keuangan/TagihanBillingGeneratorPersonIdTest.php
git commit -m "feat(keuangan): populate tagihan.person_id in TagihanBillingGenerator"
```

---

### Task 14: Populate `person_id` in `TagihanSeeder` (3 sites)

**Files:**
- Modify: `database/seeders/TagihanSeeder.php:53,63,76`

**Interfaces:**
- Consumes: `Pendaftaran::calonMurid()->person_id` (same pattern as Task 12).

Seeders are not covered by Pest tests — verify by running the seeder against the local dev DB and checking the result with `mcp__laravel-boost__database-query`, per this project's convention for seeder changes.

- [ ] **Step 1: Add `person_id` to all 3 `Tagihan::firstOrCreate()` calls**

At lines 53–62 and 63–72 (both use `$diterima`), add to the second array argument (the "create" values, not the "match" conditions):

```php
'person_id' => $diterima->calonMurid->person_id,
```

At line 76–85 (uses `$cicilanDemo`):

```php
'person_id' => $cicilanDemo->calonMurid->person_id,
```

- [ ] **Step 2: Run the seeder and verify**

Run: `php artisan db:seed --class=TagihanSeeder`
Then run a read-only query (via `mcp__laravel-boost__database-query`) confirming all `tagihan` rows created by this seeder now have a non-null `person_id` matching their `tagihable`'s person.

- [ ] **Step 3: Commit**

```bash
git add database/seeders/TagihanSeeder.php
git commit -m "feat(keuangan): populate tagihan.person_id in TagihanSeeder"
```

---

### Task 15: Populate `person_id` in `KeuanganDemoSeeder`

**Files:**
- Modify: `database/seeders/KeuanganDemoSeeder.php:105`

**Interfaces:**
- Consumes: `Siswa::person_id` directly (same pattern as Task 13).

- [ ] **Step 1: Add `person_id` to the `Tagihan::firstOrCreate()` call**

At line 105–120, inside the second array argument, add:

```php
'person_id' => $siswa->person_id,
```

- [ ] **Step 2: Run the seeder and verify**

Run: `php artisan db:seed --class=KeuanganDemoSeeder`
Verify via `mcp__laravel-boost__database-query` that the created `tagihan` rows have `person_id` matching `$siswa->person_id`.

- [ ] **Step 3: Commit**

```bash
git add database/seeders/KeuanganDemoSeeder.php
git commit -m "feat(keuangan): populate tagihan.person_id in KeuanganDemoSeeder"
```

---

### Task 16: `PersonsMerged` event + `MergePersonsAction` dispatch

**Files:**
- Create: `app/Domains/Identity/Events/PersonsMerged.php`
- Modify: `app/Domains/Identity/Actions/MergePersonsAction.php`
- Test: `tests/Feature/Identity/PersonsMergedEventTest.php`

**Interfaces:**
- Produces: `App\Domains\Identity\Events\PersonsMerged` with public readonly `$losing` and `$winning` (both `Person`), dispatched synchronously inside `MergePersonsAction::execute()`'s transaction. Task 17's listener consumes this exact event shape.

**Binding constraint (see Global Constraints)**: this event class must NOT implement `ShouldQueue`. Add a comment explaining why, since a future developer might otherwise "helpfully" queue it.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Identity\Actions\MergePersonsAction;
use App\Domains\Identity\Events\PersonsMerged;
use App\Domains\Identity\Models\Person;
use Illuminate\Support\Facades\Event;

it('dispatches PersonsMerged synchronously when MergePersonsAction executes', function () {
    Event::fake([PersonsMerged::class]);

    $winning = Person::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $winning->yayasan_id]);

    app(MergePersonsAction::class)->execute($losing, $winning);

    Event::assertDispatched(PersonsMerged::class, fn ($event) => $event->losing->id === $losing->id && $event->winning->id === $winning->id);
});

it('PersonsMerged does not implement ShouldQueue', function () {
    expect(PersonsMerged::class)->not->toImplement(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PersonsMergedEventTest`
Expected: FAIL (class doesn't exist)

- [ ] **Step 3: Create the event**

```php
<?php

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\Person;

// Deliberately NOT ShouldQueue: Keuangan's reparenting listener (and any
// future domain listener) must run synchronously inside
// MergePersonsAction's transaction, so a listener failure rolls back the
// whole merge instead of leaving it half-applied. Do not add ShouldQueue.
class PersonsMerged
{
    public function __construct(
        public readonly Person $losing,
        public readonly Person $winning,
    ) {}
}
```

- [ ] **Step 4: Dispatch the event inside `MergePersonsAction`**

In `app/Domains/Identity/Actions/MergePersonsAction.php`, add the import `use App\Domains\Identity\Events\PersonsMerged;`, and inside the `DB::transaction()` closure, add `event(new PersonsMerged($losing, $winning));` right after the `ROLE_TABLES` loop, before the `user_id`/`delete()` logic:

```php
DB::transaction(function () use ($losing, $winning) {
    foreach (self::ROLE_TABLES as $table) {
        DB::table($table)->where('person_id', $losing->id)->update(['person_id' => $winning->id]);
    }

    // Synchronous by design -- see PersonsMerged's class comment. A listener
    // exception here propagates and rolls back this entire transaction.
    event(new PersonsMerged($losing, $winning));

    if ($losing->user_id !== null && $winning->user_id === null) {
        $carriedUserId = $losing->user_id;
        $losing->update(['user_id' => null, 'merged_into_person_id' => $winning->id]);
        $winning->update(['user_id' => $carriedUserId]);
    } else {
        $losing->update(['merged_into_person_id' => $winning->id]);
    }

    $losing->delete();
});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PersonsMergedEventTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Identity/Events/PersonsMerged.php app/Domains/Identity/Actions/MergePersonsAction.php tests/Feature/Identity/PersonsMergedEventTest.php
git commit -m "feat(identity): dispatch PersonsMerged synchronously from MergePersonsAction"
```

---

### Task 17: `ReparentTagihanOnPersonsMerged` listener + rollback-on-failure test

**Files:**
- Create: `app/Domains/Keuangan/Listeners/ReparentTagihanOnPersonsMerged.php`
- Test: `tests/Feature/Keuangan/ReparentTagihanOnPersonsMergedTest.php`

**Interfaces:**
- Consumes: `PersonsMerged` event (Task 16).

**Binding constraint**: this listener must NOT implement `ShouldQueue` (see Global Constraints). First check this project's existing listener registration convention — `grep -rn "ShouldQueue\|EventServiceProvider" app/Domains/Keuangan/Listeners/ app/Providers/` — before deciding whether registration is auto-discovered or explicit; match whatever the other Keuangan listeners already do (e.g. `GenerateTagihanForNewStudent`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Domains\Identity\Actions\MergePersonsAction;
use App\Domains\Identity\Models\Person;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Identity\Events\PersonsMerged;
use Illuminate\Support\Facades\Event;

it('reparents tagihan.person_id from losing to winning when persons are merged', function () {
    $winning = Person::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $winning->yayasan_id]);
    $tagihan = Tagihan::factory()->create(['person_id' => $losing->id]);

    app(MergePersonsAction::class)->execute($losing, $winning);

    expect($tagihan->fresh()->person_id)->toBe($winning->id);
});

it('rolls back the entire merge, including the Person update, when the listener throws', function () {
    Event::listen(PersonsMerged::class, function () {
        throw new \RuntimeException('simulated listener failure');
    });

    $winning = Person::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $winning->yayasan_id]);

    expect(fn () => app(MergePersonsAction::class)->execute($losing, $winning))
        ->toThrow(\RuntimeException::class);

    expect($losing->fresh()->merged_into_person_id)->toBeNull();
    expect($losing->fresh()->trashed())->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ReparentTagihanOnPersonsMergedTest`
Expected: FAIL (listener doesn't exist, no reparenting happens; second test may also fail because nothing throws yet without the listener registered — confirm both fail for the right reason)

- [ ] **Step 3: Create the listener**

```php
<?php

namespace App\Domains\Keuangan\Listeners;

use App\Domains\Identity\Events\PersonsMerged;
use Illuminate\Support\Facades\DB;

// Deliberately NOT ShouldQueue: must run synchronously inside
// MergePersonsAction's transaction so a failure here rolls back the whole
// Person merge, not just this reparenting. See PersonsMerged's class comment.
class ReparentTagihanOnPersonsMerged
{
    public function handle(PersonsMerged $event): void
    {
        DB::table('tagihan')
            ->where('person_id', $event->losing->id)
            ->update(['person_id' => $event->winning->id]);
    }
}
```

- [ ] **Step 4: Register the listener**

Match whatever pattern the grep in this task's intro revealed (auto-discovery needs no registration; explicit registration goes in the app's `EventServiceProvider` or equivalent per Laravel 12 convention — read that file's existing `$listen` array structure first if it exists).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ReparentTagihanOnPersonsMergedTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Keuangan/Listeners/ReparentTagihanOnPersonsMerged.php tests/Feature/Keuangan/ReparentTagihanOnPersonsMergedTest.php
git commit -m "feat(keuangan): reparent tagihan.person_id synchronously on PersonsMerged"
```

---

### Task 18: Simplify `DashboardController`'s 2 OR-hack sites

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php:133-135,200-206`
- Test: `tests/Feature/Keuangan/DashboardTagihanPersonIdQueryTest.php`

**Interfaces:**
- Consumes: `tagihan.person_id` populated by Tasks 12–15.

> **Known risk — sequencing in this plan vs. production deployment.** This task cuts `DashboardController` over to `where('person_id', ...)` BEFORE Task 19 (backfill) and Task 20 (verify + NOT NULL) run. In this dev/demo environment that's harmless — Tasks 12–15 already made every NEW tagihan row correct going forward, and this repo's existing `tagihan` data was confirmed 100% backfill-able in the person-id spec's audit (§4.5's "Catatan realita data"). **But this ordering is NOT safe for a production deployment against a database with pre-existing `tagihan` rows that might fail to backfill.** Between this task's deploy and Task 19/20's backfill completing, any `tagihan` row still `person_id IS NULL` would silently disappear from the dashboard (a `WHERE person_id = X` query never matches a `NULL` row), even though the OR-hack it replaced would have shown it. For a real production rollout: either (a) run Task 19's backfill to completion and confirm Task 19's verify command exits 0 BEFORE deploying this task's `DashboardController` change, or (b) deploy Tasks 11–20 together as one atomic release so there is no window where old tagihan rows are both `person_id NULL` and being queried by the new code. Do not reorder tasks in THIS plan to fix this — the current order optimizes for this dev environment's already-confirmed-clean data; just carry this note into the production rollout runbook when this branch ships.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Pendaftaran;
use App\Models\Siswa;

it('dashboard shows tagihan from both the pendaftaran era and the siswa era for the same person, via person_id', function () {
    $siswa = Siswa::factory()->create();
    $pendaftaranTagihan = Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class,
        'tagihable_id' => $siswa->pendaftaran_asal_id,
        'person_id' => $siswa->person_id,
    ]);
    $siswaTagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class,
        'tagihable_id' => $siswa->id,
        'person_id' => $siswa->person_id,
    ]);

    $response = $this->actingAs(/* ortu user for $siswa */)->get(route('admin.dashboard')); // adjust route

    // Assert the response's underlying data includes both tagihan IDs --
    // read the controller method around lines 133-135 first to know the
    // exact variable/view-data name to assert against.
});
```

Read the surrounding 20 lines of `DashboardController.php` around both line ranges first, to confirm the exact test setup this method needs (auth actor, `$siswa` relationship to that actor, and the view-data key to assert on) before finalizing this test.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DashboardTagihanPersonIdQueryTest`
Expected: this should already PASS under the OLD OR-hack code — this test PINS current behavior before the refactor, not exposes a bug.

- [ ] **Step 3: Replace both OR-hack sites**

Lines 133–135, replace:

```php
$q->where(fn ($q2) => $q2->where('tagihable_type', Siswa::class)->where('tagihable_id', $siswa->id));
if ($siswa->pendaftaran_asal_id !== null) {
    $q->orWhere('pendaftaran_id', $siswa->pendaftaran_asal_id);
}
```

with:

```php
$q->where('person_id', $siswa->person_id);
```

Lines 200–206, replace:

```php
$q->where(fn ($q2) => $q2->where('tagihable_type', Siswa::class)->whereIn('tagihable_id', $siswaIds));
if (!empty($pendaftaranIds)) {
    $q->orWhereIn('pendaftaran_id', $pendaftaranIds);
}
```

with:

```php
$q->whereIn('person_id', $anakList->pluck('person_id'));
```

(Confirm `$anakList` is the correct variable name in scope at that line — read the surrounding method body first; it may be named differently.)

- [ ] **Step 4: Run test to verify it still passes**

Run: `php artisan test --filter=DashboardTagihanPersonIdQueryTest`
Expected: PASS, unchanged result — same tagihan set, simpler query.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php tests/Feature/Keuangan/DashboardTagihanPersonIdQueryTest.php
git commit -m "refactor(keuangan): replace OR-hack tagihable/pendaftaran_id queries with person_id"
```

---

### Task 19: Backfill and verify commands

**Files:**
- Create: `app/Console/Commands/BackfillTagihanPersonId.php`
- Create: `app/Console/Commands/VerifyTagihanPersonId.php`
- Test: `tests/Feature/Console/BackfillTagihanPersonIdTest.php`
- Test: `tests/Feature/Console/VerifyTagihanPersonIdTest.php`

**Interfaces:**
- Produces: `php artisan keuangan:backfill-tagihan-person-id` (idempotent — only processes `WHERE person_id IS NULL`, logs and skips unresolvable rows) and `php artisan keuangan:verify-tagihan-person-id` (exit 1 if any `NULL` remains, exit 0 if clean). Task 20 depends on `verify`'s exit code.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Console/BackfillTagihanPersonIdTest.php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Pendaftaran;
use App\Models\CalonMurid;
use App\Models\Siswa;

it('backfills person_id for Pendaftaran-tagihable rows via calonMurid', function () {
    $calonMurid = CalonMurid::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['calon_murid_id' => $calonMurid->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class,
        'tagihable_id' => $pendaftaran->id,
        'person_id' => null,
    ]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();

    expect($tagihan->fresh()->person_id)->toBe($calonMurid->person_id);
});

it('backfills person_id for Siswa-tagihable rows directly', function () {
    $siswa = Siswa::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class,
        'tagihable_id' => $siswa->id,
        'person_id' => null,
    ]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();

    expect($tagihan->fresh()->person_id)->toBe($siswa->person_id);
});

it('skips and reports, but does not throw, when tagihable cannot be resolved', function () {
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class,
        'tagihable_id' => 999999, // does not exist
        'person_id' => null,
    ]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();

    expect($tagihan->fresh()->person_id)->toBeNull();
});

it('is idempotent -- running twice does not error and does not reprocess already-backfilled rows', function () {
    $siswa = Siswa::factory()->create();
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'person_id' => null]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();
    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();
});
```

```php
<?php
// tests/Feature/Console/VerifyTagihanPersonIdTest.php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Siswa;

it('exits 0 when no tagihan.person_id is null', function () {
    $siswa = Siswa::factory()->create();
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'person_id' => $siswa->person_id]);

    $this->artisan('keuangan:verify-tagihan-person-id')->assertSuccessful();
});

it('exits 1 and lists offending ids when any tagihan.person_id is null', function () {
    $tagihan = Tagihan::factory()->create(['person_id' => null]);

    $this->artisan('keuangan:verify-tagihan-person-id')->assertFailed();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=BackfillTagihanPersonIdTest` and `php artisan test --filter=VerifyTagihanPersonIdTest`
Expected: FAIL (commands don't exist)

- [ ] **Step 3: Write the backfill command**

```php
<?php

namespace App\Console\Commands;

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use Illuminate\Console\Command;

class BackfillTagihanPersonId extends Command
{
    protected $signature = 'keuangan:backfill-tagihan-person-id';

    protected $description = 'Backfill tagihan.person_id from each row\'s tagihable (Pendaftaran->calonMurid or Siswa directly).';

    public function handle(): int
    {
        $failed = [];
        $processed = 0;
        $succeeded = 0;

        Tagihan::whereNull('person_id')->chunkById(200, function ($tagihanRows) use (&$failed, &$processed, &$succeeded) {
            foreach ($tagihanRows as $tagihan) {
                $processed++;

                $personId = match ($tagihan->tagihable_type) {
                    Pendaftaran::class => Pendaftaran::find($tagihan->tagihable_id)?->calonMurid?->person_id,
                    Siswa::class => Siswa::withoutGlobalScopes()->find($tagihan->tagihable_id)?->person_id,
                    default => null,
                };

                if ($personId === null) {
                    $failed[] = ['id' => $tagihan->id, 'reason' => "tagihable_type={$tagihan->tagihable_type} tagihable_id={$tagihan->tagihable_id} tidak bisa di-resolve ke person_id"];

                    continue;
                }

                $tagihan->update(['person_id' => $personId]);
                $succeeded++;
            }
        });

        $this->info("Diproses: {$processed}, berhasil: {$succeeded}, gagal: " . count($failed));

        foreach ($failed as $item) {
            $this->warn("Tagihan #{$item['id']}: {$item['reason']}");
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Write the verify command**

```php
<?php

namespace App\Console\Commands;

use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Console\Command;

class VerifyTagihanPersonId extends Command
{
    protected $signature = 'keuangan:verify-tagihan-person-id';

    protected $description = 'Verify that no tagihan row has a null person_id (must exit 0 before the NOT NULL migration runs).';

    public function handle(): int
    {
        $nullIds = Tagihan::whereNull('person_id')->pluck('id');

        if ($nullIds->isEmpty()) {
            $this->info('Semua baris tagihan sudah punya person_id.');

            return self::SUCCESS;
        }

        $this->error("{$nullIds->count()} baris tagihan masih person_id NULL: " . $nullIds->join(', '));

        return self::FAILURE;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=BackfillTagihanPersonIdTest` and `php artisan test --filter=VerifyTagihanPersonIdTest`
Expected: PASS (4 + 2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/BackfillTagihanPersonId.php app/Console/Commands/VerifyTagihanPersonId.php tests/Feature/Console/BackfillTagihanPersonIdTest.php tests/Feature/Console/VerifyTagihanPersonIdTest.php
git commit -m "feat(keuangan): add backfill and verify commands for tagihan.person_id"
```

---

### Task 20: Run backfill against dev data, then migrate `person_id` to NOT NULL + FK

**Files:**
- Create: `database/migrations/2026_09_01_000002_make_tagihan_person_id_not_null.php`
- Test: `tests/Feature/Keuangan/TagihanPersonIdConstraintTest.php`

**Interfaces:**
- Consumes: `verify-tagihan-person-id`'s exit code (Task 19) — this migration must only be applied after that command exits 0 against the real dev database.

- [ ] **Step 1: Run backfill and verify against the local dev DB**

```bash
php artisan keuangan:backfill-tagihan-person-id
php artisan keuangan:verify-tagihan-person-id
```

Confirm the second command exits 0. If it doesn't, investigate the reported failing rows before proceeding — do not force the migration through.

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Identity\Models\Person;

it('rejects a null person_id at the database level', function () {
    $this->expectException(\Illuminate\Database\QueryException::class);

    Tagihan::factory()->create(['person_id' => null]);
});

it('restricts deleting a person that still has tagihan rows', function () {
    $person = Person::factory()->create();
    Tagihan::factory()->create(['person_id' => $person->id]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    $person->forceDelete();
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=TagihanPersonIdConstraintTest`
Expected: FAIL (column still nullable, no FK yet — no exception thrown)

- [ ] **Step 4: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
            $table->index('person_id', 'idx_tagihan_person');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropIndex('idx_tagihan_person');
            $table->unsignedBigInteger('person_id')->nullable()->change();
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TagihanPersonIdConstraintTest`
Expected: PASS (both exceptions thrown as expected)

- [ ] **Step 6: Run full test suite**

Run: `php artisan test --compact`
Expected: PASS, 0 failures.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_01_000002_make_tagihan_person_id_not_null.php tests/Feature/Keuangan/TagihanPersonIdConstraintTest.php
git commit -m "feat(keuangan): enforce tagihan.person_id NOT NULL with FK RESTRICT to persons"
```

**Stage 3 complete when this task's full suite run is green.**

---

## Stage 4 — UI Assignment Keringanan (Tasks 21–24)

### Task 21: Permission + route

**Files:**
- Modify: `routes/lembaga.php` (or wherever `admin/kategori-keringanan` is currently registered — confirm exact file via `grep -rn "kategori-keringanan" routes/`)
- Modify: permission seeder/config wherever `siswa.edit`/`orang-tua.edit`-style permissions are defined (confirm exact file via `grep -rn "'siswa.edit'\|'orang-tua.edit'" database/seeders app/`)
- Test: `tests/Feature/Keuangan/SiswaKeringananPermissionTest.php`

**Interfaces:**
- Produces: permission slug `siswa-keringanan.kelola`, routes `GET/POST admin/siswa/{siswa}/keringanan` and `DELETE admin/siswa-keringanan/{siswaKeringanan}` (exact URIs to be confirmed against this project's existing nested-resource route conventions — check `routes/` for how `orang_tua`/`guru` sub-resources under a parent model are named before finalizing).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;
use App\Models\Siswa;

it('a user without siswa-keringanan.kelola cannot access the keringanan routes', function () {
    $user = User::factory()->create(); // no permission
    $siswa = Siswa::factory()->create();

    $this->actingAs($user)->get(route('admin.siswa.keringanan.index', $siswa))->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SiswaKeringananPermissionTest`
Expected: FAIL (route doesn't exist yet — 404, not 403)

- [ ] **Step 3: Register the permission**

Add `siswa-keringanan.kelola` to the same seeder/config file where `siswa.edit` etc. are defined, following that file's exact existing structure and array shape. Attach it to `bendahara_lembaga` and `operator_akademik` roles (matching whichever roles already carry `orang-tua.edit`/`siswa.edit`, confirmed via `grep -rn "'orang-tua.edit'"` in the role-permission mapping file).

- [ ] **Step 4: Register the routes**

Add routes in the file identified above, following the exact pattern already used for the sibling `admin/kategori-keringanan` route (same middleware group, same permission-gate mechanism):

```php
Route::middleware('can:siswa-keringanan.kelola')->group(function () {
    Route::get('admin/siswa/{siswa}/keringanan', [SiswaKeringananController::class, 'index'])->name('admin.siswa.keringanan.index');
    Route::post('admin/siswa/{siswa}/keringanan', [SiswaKeringananController::class, 'store'])->name('admin.siswa.keringanan.store');
    Route::delete('admin/siswa-keringanan/{siswaKeringanan}', [SiswaKeringananController::class, 'destroy'])->name('admin.siswa-keringanan.destroy');
});
```

(This is a route-only registration for now — the controller class doesn't exist yet, so this task's own test will still 404 until Task 22. Adjust Step 1's test to assert 404 at this stage if the controller class must exist first per this project's routing convention — check whether route registration alone without the controller class breaks route caching before finalizing.)

- [ ] **Step 5: Commit** (after Task 22 makes the controller exist — if route registration alone fails without the controller class, merge this task's commit into Task 22's instead)

```bash
git add routes/lembaga.php # + whichever permission file was edited
git commit -m "feat(keuangan): add siswa-keringanan.kelola permission and routes"
```

---

### Task 22: `SiswaKeringananController`

**Files:**
- Create: `app/Http/Controllers/Lembaga/Keuangan/SiswaKeringananController.php`
- Test: `tests/Feature/Keuangan/SiswaKeringananControllerTest.php`

**Interfaces:**
- Consumes: `SiswaKeringanan` model (existing), `KategoriKeringanan` model (existing), routes from Task 21.
- Produces: `index(Siswa $siswa)`, `store(Request $request, Siswa $siswa)`, `destroy(SiswaKeringanan $siswaKeringanan)`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Siswa;
use App\Models\User;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;

it('admin can assign a keringanan category to a siswa', function () {
    $admin = User::factory()->create(); // with siswa-keringanan.kelola
    $siswa = Siswa::factory()->create();
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->toDateString(),
    ])->assertRedirect();

    $this->assertDatabaseHas('siswa_keringanan', [
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
    ]);
});

it('rejects a kategori keringanan belonging to a different lembaga', function () {
    $admin = User::factory()->create();
    $siswa = Siswa::factory()->create();
    $kategoriLembagaLain = KategoriKeringanan::factory()->create(); // different lembaga_id

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategoriLembagaLain->id,
        'berlaku_dari' => now()->toDateString(),
    ])->assertStatus(422);
});

it('rejects berlaku_sampai before berlaku_dari', function () {
    $admin = User::factory()->create();
    $siswa = Siswa::factory()->create();
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->toDateString(),
        'berlaku_sampai' => now()->subDay()->toDateString(),
    ])->assertStatus(422);
});

it('admin can revoke an assigned keringanan (hard delete)', function () {
    $admin = User::factory()->create();
    $siswaKeringanan = SiswaKeringanan::factory()->create();

    $this->actingAs($admin)->delete(route('admin.siswa-keringanan.destroy', $siswaKeringanan))->assertRedirect();

    $this->assertDatabaseMissing('siswa_keringanan', ['id' => $siswaKeringanan->id]);
});
```

Read `SiswaKeringanan`'s existing migration/model first to confirm exact column names (`siswa_id`, `kategori_keringanan_id`, `berlaku_dari`, `berlaku_sampai`) before finalizing these tests. Also confirm the `admin` factory user needs `->givePermissionTo('siswa-keringanan.kelola')` or equivalent, matching this project's existing permission-test pattern (check `tests/Feature/Admin/JenisTagihanKeringananFormTest.php` for the convention).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SiswaKeringananControllerTest`
Expected: FAIL (controller doesn't exist)

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiswaKeringananController extends Controller
{
    public function index(Siswa $siswa)
    {
        $keringanan = $siswa->siswaKeringanan()->with('kategoriKeringanan')->latest('berlaku_dari')->get();

        return view('admin.siswa.tabs.keringanan', compact('siswa', 'keringanan'));
    }

    public function store(Request $request, Siswa $siswa): RedirectResponse
    {
        $validated = $request->validate([
            'kategori_keringanan_id' => [
                'required',
                Rule::exists('kategori_keringanan', 'id')->where('lembaga_id', $siswa->lembaga_id),
            ],
            'berlaku_dari' => ['required', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_dari'],
        ]);

        $siswa->siswaKeringanan()->create($validated);

        return back()->with('success', 'Keringanan berhasil ditambahkan.');
    }

    public function destroy(SiswaKeringanan $siswaKeringanan): RedirectResponse
    {
        $siswaKeringanan->delete();

        return back()->with('success', 'Keringanan berhasil dicabut.');
    }
}
```

(Confirm the exact relation name — `siswaKeringanan()` — and the `kategori_keringanan` table name against the actual `Siswa` and `SiswaKeringanan` models before finalizing; adjust if the codebase uses different names.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SiswaKeringananControllerTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/SiswaKeringananController.php tests/Feature/Keuangan/SiswaKeringananControllerTest.php
git commit -m "feat(keuangan): add SiswaKeringananController for keringanan assignment"
```

---

### Task 23: Keringanan tab view

**Files:**
- Create: `resources/views/admin/siswa/tabs/keringanan.blade.php`
- Modify: the siswa detail page's tab-navigation partial (confirm exact file via `grep -rln "tabs/profil" resources/views/admin/siswa/`) to add a "Keringanan" tab entry
- Test: `tests/Feature/Keuangan/SiswaKeringananTabViewTest.php`

**Interfaces:**
- Consumes: `$siswa`, `$keringanan` (from `SiswaKeringananController::index()`, Task 22).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Siswa;
use App\Models\User;
use App\Domains\Keuangan\Models\SiswaKeringanan;

it('siswa keringanan tab shows active and expired keringanan and an assign form', function () {
    $admin = User::factory()->create();
    $siswa = Siswa::factory()->create();
    $active = SiswaKeringanan::factory()->create(['siswa_id' => $siswa->id, 'berlaku_dari' => now()->subMonth(), 'berlaku_sampai' => null]);

    $response = $this->actingAs($admin)->get(route('admin.siswa.keringanan.index', $siswa));

    $response->assertOk();
    $response->assertSee($active->kategoriKeringanan->nama);
    $response->assertSee('kategori_keringanan_id', escape: false); // form field present
});
```

Read `resources/views/admin/guru/tabs/profil.blade.php` first (per spec §3.2's cited reference) to match its layout structure (tab-content wrapper classes, form styling conventions) exactly before writing this view.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SiswaKeringananTabViewTest`
Expected: FAIL (view doesn't exist)

- [ ] **Step 3: Write the view**

Build `resources/views/admin/siswa/tabs/keringanan.blade.php` with: a table of `$keringanan` (kategori name, berlaku_dari, berlaku_sampai or "Tidak ada batas", a delete button posting to `admin.siswa-keringanan.destroy`), and a form posting to `admin.siswa.keringanan.store` with a `kategori_keringanan_id` select (populated from `KategoriKeringanan::where('lembaga_id', $siswa->lembaga_id)->get()` — pass this from the controller if not already in scope, revisit Task 22's `index()` to add it), `berlaku_dari` and `berlaku_sampai` date inputs. Match the existing tab's Blade component/styling conventions from `profil.blade.php`.

- [ ] **Step 4: Add the tab entry to the navigation partial**

In the tab-navigation partial identified above, add a "Keringanan" tab link pointing to this new route, following the exact markup pattern of the existing tab entries (e.g. Guru/Karyawan/OrangTua tabs).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SiswaKeringananTabViewTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/siswa/tabs/keringanan.blade.php # + tab-navigation partial file
git commit -m "feat(keuangan): add keringanan tab to siswa detail page"
```

---

### Task 24: Regression — `TagihanNominalResolver` discount calculation via UI-created `SiswaKeringanan`

**Files:**
- Test: `tests/Feature/Keuangan/KeringananUiDiscountRegressionTest.php`

**Interfaces:**
- Consumes: `SiswaKeringananController::store()` (Task 22), `TagihanNominalResolver::resolveDiscount()` (existing, unmodified).

This closes the spec's explicit gap: today 0 tests exercise `SiswaKeringanan` created through a production code path — only via factory. This test proves the new UI's writes actually feed the existing discount engine correctly, end to end.

- [ ] **Step 1: Write the test**

```php
<?php

use App\Models\Siswa;
use App\Models\User;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Services\TagihanNominalResolver;

it('a keringanan assigned through the new UI is correctly applied by TagihanNominalResolver', function () {
    $admin = User::factory()->create();
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp', 'default_amount' => 350000]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::factory()->create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'kategori_keringanan_id' => $kategori->id,
        'nominal_potongan' => 50000,
    ]);

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subDay()->toDateString(),
    ]);

    $discount = app(TagihanNominalResolver::class)->resolveDiscount($siswa, $jenisTagihan); // confirm exact method signature

    expect($discount)->toBe(50000);
});
```

Read `TagihanNominalResolver::resolveDiscount()`'s exact signature and the `JenisTagihanKeringanan` model's exact column names first (already audited earlier in this project's history — confirm they haven't changed) before finalizing.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KeringananUiDiscountRegressionTest`
Expected: FAIL only if Task 22's controller has a bug — otherwise this should PASS immediately since it's pure regression/integration proof, not new functionality. If it fails, debug Task 22's controller before proceeding (do not modify `TagihanNominalResolver` — it's explicitly out of scope per spec §2 Non-Goals #5).

- [ ] **Step 3: Fix if needed, then confirm pass**

Run: `php artisan test --filter=KeringananUiDiscountRegressionTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Keuangan/KeringananUiDiscountRegressionTest.php
git commit -m "test(keuangan): prove UI-assigned keringanan feeds TagihanNominalResolver correctly"
```

---

## Final Step: Full Test Suite

- [ ] Run: `php artisan test --compact`
- [ ] Expected: PASS, 0 failures — confirms Stages 1–4 introduced no regressions anywhere else in the app.
- [ ] Run `vendor/bin/pint --dirty --format agent` to fix any formatting drift across all files touched in this plan, then commit any resulting formatting-only changes separately.

**Plan complete when this full-suite run and Pint pass are both clean.**
