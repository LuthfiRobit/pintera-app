# Keuangan Sub-project 2b-2: Tombol "Proses Tagihan" + Guard Kategori PPDB — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close a live, armed data-integrity gap (PPDB-kategori `jenis_tagihan` can be processed by the billing engine, generating bogus bills for every siswa in the lembaga) with a defense-in-depth guard across every caller, and ship the "Proses Tagihan" manual-trigger button with an unambiguous result breakdown.

**Architecture:** Guard lives primarily in `TagihanBillingGenerator` (last line of defense, both public entry points), backed by early pre-checks in every caller (2 event listeners, 1 cron command, 1 console command, 1 new controller action) for clear error messages and to avoid wasted work. The button itself is a `POST` endpoint composing the existing matcher + generator, computing a 4-way breakdown (`bills_generated`/`sudah_tertagih`/`tidak_memenuhi_kriteria`/`gagal`) without changing `generate()`'s existing return contract.

**Tech Stack:** Laravel 12, Pest, Alpine.js (existing `jenis-tagihan-table.js`).

## Global Constraints

- PPDB kategori: `['pendaftaran', 'daftar_ulang']` (same list as `JenisTagihanController::PPDB_KATEGORI` — each class in this plan defines its own local copy since PHP has no cross-class shared const in this codebase's existing convention; do not introduce a shared Enum/constant class for this, out of scope).
- **`TagihanBillingGenerator::generate()` and `::generateForSiswaViaEvent()` must throw `\RuntimeException` immediately (before any query/side effect) when `$jenisTagihan->kategori` is PPDB.** No `BillingJobLog` row, no `Tagihan` row, no wasted `resolveTargetSiswa()` query.
- `GenerateTagihanForActivatedBillType` must NOT let this exception propagate — a PPDB `jenis_tagihan` being reactivated is a valid admin action and must not 500 the triggering `update()` request. Same principle for the cron loop in `GenerateTagihanHarian` (Task 3) — one `jenis_tagihan` erroring must not abort the rest of that day's run.
- `sudah_tertagih` = matched-by-sasaran siswa who already have a non-`dibatalkan` tagihan for this `jenis_tagihan`+period (idempotency skip — expected, not an error). `tidak_memenuhi_kriteria` = siswa in the lembaga who did NOT match any sasaran group. These are DIFFERENT counts and must never be merged into one "skipped" number — see spec `.agents/specs/keuangan-02b2-proses-tagihan.md` for the full rationale.
- Do not change `TagihanBillingGenerator::generate()`'s return type (still `BillingJobLog`) — the breakdown is computed by composing `JenisTagihanSasaranMatcher` calls at the controller layer, accepting one extra `resolveTargetSiswa()` query as a deliberate, documented trade-off (see spec).
- `JenisTagihanFactory`'s default `kategori` is currently `'pendaftaran'` — this MUST change before Task 2's guard lands, or every existing test that creates a bare `JenisTagihan::factory()->create()` (12 files, confirmed via repo-wide grep) will start throwing the new guard exception. Task 1 does this first and re-verifies the full Keuangan suite in isolation from the guard change, so any breakage is attributable to the factory change alone, not conflated with Task 2's guard.

---

### Task 1: Fix `JenisTagihanFactory` default kategori before the guard lands

**Files:**
- Modify: `database/factories/JenisTagihanFactory.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: a factory default that Tasks 2-9's tests (and the 12 pre-existing test files that use a bare `JenisTagihan::factory()->create()`) rely on being non-PPDB.

- [x] **Step 1: Confirm no existing test relies on the current default being `'pendaftaran'`**

Already verified during planning (repo-wide grep of `tests/`): all 12 files using `JenisTagihan::factory()->create(...)` without an explicit `kategori` never assert anything about `kategori` itself (they test `mode`/`default_amount`/sasaran/nominal/discount behavior). No file outside `tests/Feature/Keuangan/` uses `JenisTagihan::factory()` at all. This step is a checkpoint, not new work — re-run the grep yourself to be sure nothing changed since planning:

```bash
grep -rn "JenisTagihan::factory()->create(" tests/ | grep -v "kategori"
```

Skim the output; if anything looks like it depends on the PPDB default, stop and flag it before proceeding — do not guess.

- [x] **Step 2: Change the factory default**

```php
<?php

namespace Database\Factories;

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JenisTagihan> */
class JenisTagihanFactory extends Factory
{
    protected $model = JenisTagihan::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => 'Biaya Pendaftaran',
            // Non-PPDB default: TagihanBillingGenerator (Sub-project 2a/2b-2) rejects
            // pendaftaran/daftar_ulang kategori, and most tests using this factory
            // exercise the billing engine without caring about kategori specifically.
            // Tests that DO need PPDB kategori pass it explicitly.
            'kategori' => 'lainnya',
            'bisa_dicicil' => false,
            'maks_cicilan' => null,
        ];
    }
}
```

- [x] **Step 3: Run the full Keuangan suite to establish a clean baseline BEFORE the guard exists**

Run: `php artisan test tests/Feature/Keuangan/`
Expected: PASS, same count as before this change (55 passed, 130 assertions — this change must be a no-op for every existing assertion, proving none of them depended on the PPDB default).

- [x] **Step 4: Also run the Admin jenis-tagihan test files** (they don't use the factory bare, but confirm no incidental interaction)

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php tests/Feature/Admin/JenisTagihanFormTest.php`
Expected: PASS (21/21, 5/5 — unchanged).

- [x] **Step 5: Commit**

```bash
git add database/factories/JenisTagihanFactory.php
git commit -m "fix(keuangan): default JenisTagihanFactory to a non-PPDB kategori"
```

---

### Task 2: `TagihanBillingGenerator::assertBillable()` guard

**Files:**
- Modify: `app/Services/TagihanBillingGenerator.php`
- Test: `tests/Feature/Keuangan/TagihanBillingGeneratorTest.php`

**Interfaces:**
- Consumes: `JenisTagihan::kategori` (existing column).
- Produces: `\RuntimeException` thrown by `generate()`/`generateForSiswaViaEvent()` for PPDB kategori — every caller in Tasks 3-8 must handle or pre-empt this.

- [x] **Step 1: Write the failing tests** — append to `tests/Feature/Keuangan/TagihanBillingGeneratorTest.php`:

```php
it('rejects generate() for a pendaftaran-kategori jenis_tagihan without creating anything', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'pendaftaran', 'default_amount' => 200000]);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    expect(fn () => buatGenerator()->generate($jenisTagihan, 'manual'))->toThrow(\RuntimeException::class);

    expect(Tagihan::count())->toBe(0);
    expect(\App\Models\BillingJobLog::count())->toBe(0);
});

it('rejects generateForSiswaViaEvent() for a daftar_ulang-kategori jenis_tagihan without creating anything', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'daftar_ulang', 'default_amount' => 200000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    expect(fn () => buatGenerator()->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentCreated'))->toThrow(\RuntimeException::class);

    expect(Tagihan::count())->toBe(0);
    expect(\App\Models\BillingJobLog::count())->toBe(0);
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/TagihanBillingGeneratorTest.php --filter="rejects"`
Expected: FAIL (no guard exists yet, both PPDB-kategori calls currently succeed and create a `Tagihan`)

- [x] **Step 3: Add the guard to `app/Services/TagihanBillingGenerator.php`**

Add this private const and method, and call it at the top of both `generate()` and `generateForSiswaViaEvent()`:

```php
    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];
```

```php
    private function assertBillable(JenisTagihan $jenisTagihan): void
    {
        if (in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            throw new \RuntimeException(
                "Jenis tagihan berkategori {$jenisTagihan->kategori} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB."
            );
        }
    }
```

Edit `generate()` to call the guard as its first line:

```php
    public function generate(JenisTagihan $jenisTagihan, string $triggerType, ?string $triggerEvent = null): BillingJobLog
    {
        $this->assertBillable($jenisTagihan);

        $targetSiswa = $this->matcher->resolveTargetSiswa($jenisTagihan);
        // ...rest unchanged...
```

Edit `generateForSiswaViaEvent()` to call the guard as its first line:

```php
    public function generateForSiswaViaEvent(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerEvent): BillingJobLog
    {
        $this->assertBillable($jenisTagihan);

        $billsGenerated = 0;
        // ...rest unchanged...
```

Do NOT add the guard inside `generateForSiswa()` (the private per-siswa worker) — both its callers (`generate()`'s loop and `generateForSiswaViaEvent()`) already guard before reaching it; a third guard there is redundant defensive duplication with no added protection.

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/TagihanBillingGeneratorTest.php`
Expected: PASS (9/9 — 7 existing + 2 new)

- [x] **Step 5: Commit**

```bash
git add app/Services/TagihanBillingGenerator.php tests/Feature/Keuangan/TagihanBillingGeneratorTest.php
git commit -m "feat(keuangan): reject ppdb kategori in TagihanBillingGenerator as last line of defense"
```

---

### Task 3: Harden `GenerateTagihanHarian` cron loop against a single jenis_tagihan's exception

**Files:**
- Modify: `app/Console/Commands/GenerateTagihanHarian.php`
- Test: `tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php`

**Interfaces:**
- Consumes: `TagihanBillingGenerator::generate()` (now throws for PPDB kategori, Task 2).
- Produces: nothing new consumed elsewhere — this task only makes the existing command resilient to Task 2's new throw path (and any other future exception a single `jenis_tagihan` might raise).

**Why this task exists:** `GenerateTagihanHarian`'s query already filters `mode = 'otomatis'`, and PPDB-kategori `jenis_tagihan` default to `mode = 'manual'` (2b-1's form never exposes the mode toggle for PPDB kategori) — so in practice this command is accidentally safe from Task 2's guard today. But the loop currently has no exception isolation: `foreach ($kandidat as $jenisTagihan) { $generator->generate($jenisTagihan, 'cron'); }` — if ANY single `jenis_tagihan` in that day's batch throws (Task 2's guard, or any other unexpected error), the whole command aborts and every OTHER `jenis_tagihan` scheduled for that day silently doesn't get processed. This mirrors the exact fault-isolation principle `TagihanBillingGenerator` already applies per-siswa (Sub-project 2a) — apply it per-`jenis_tagihan` here too.

- [x] **Step 1: Write the failing test** — append to `tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php`:

```php
it('does not abort the whole run when one jenis_tagihan throws — others still get processed', function () {
    Carbon::setTestNow('2026-09-15');

    $lembagaBaik = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaBaik->id]);
    $baik = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaBaik->id, 'default_amount' => 200000, 'mode' => 'otomatis',
        'is_active' => true, 'tanggal_generate' => 15, 'tanggal_mulai' => '2026-01-01',
    ]);

    // Simulasi jenis_tagihan yang akan throw meski lolos filter mode=otomatis
    // (mis. data korup atau constraint yang berubah di masa depan) — dibuat kategori
    // PPDB secara paksa lewat query builder karena factory/validasi normal tidak
    // mengizinkan kombinasi mode=otomatis + kategori PPDB.
    $lembagaThrow = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaThrow->id]);
    $throw = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaThrow->id, 'default_amount' => 100000, 'mode' => 'otomatis',
        'is_active' => true, 'tanggal_generate' => 15, 'tanggal_mulai' => '2026-01-01',
    ]);
    JenisTagihan::withoutEvents(fn () => $throw->update(['kategori' => 'pendaftaran']));

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $baik->id)->count())->toBe(1);
    expect(Tagihan::where('jenis_tagihan_id', $throw->id)->count())->toBe(0);

    Carbon::setTestNow();
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php --filter="does not abort"`
Expected: FAIL (uncaught `\RuntimeException` from Task 2's guard aborts the whole command; `$baik` never gets processed because `$throw` is iterated first alphabetically/by-id ordering in this fixture, or the command exits non-zero — either way the test fails against current code)

- [x] **Step 3: Wrap the loop in `app/Console/Commands/GenerateTagihanHarian.php`**

Replace:
```php
        foreach ($kandidat as $jenisTagihan) {
            $generator->generate($jenisTagihan, 'cron');
        }
```
with:
```php
        foreach ($kandidat as $jenisTagihan) {
            try {
                $generator->generate($jenisTagihan, 'cron');
            } catch (\Throwable $e) {
                $this->error("Gagal memproses jenis_tagihan #{$jenisTagihan->id} ({$jenisTagihan->nama}): {$e->getMessage()}");
            }
        }
```

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php`
Expected: PASS (2/2 — 1 existing + 1 new)

- [x] **Step 5: Commit**

```bash
git add app/Console/Commands/GenerateTagihanHarian.php tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php
git commit -m "fix(keuangan): isolate per-jenis_tagihan failures in the daily billing cron"
```

---

### Task 4: Fix `GenerateTagihanForNewStudent` and `GenerateTagihanForUpdatedClass` — the live bug

**Files:**
- Modify: `app/Listeners/GenerateTagihanForNewStudent.php`
- Modify: `app/Listeners/GenerateTagihanForUpdatedClass.php`
- Test: `tests/Feature/Keuangan/StudentBillingEventsTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new consumed elsewhere — this is the actual fix for the live/armed bug found during brainstorming.

**This is the highest-priority task in this plan** — confirmed via direct DB query (2026-08-11) that all 8 PPDB-kategori `jenis_tagihan` rows in the real dev DB are `is_active = true` with no sasaran configured, meaning `GenerateTagihanForNewStudent` (which queries ALL active `jenis_tagihan` with no kategori filter, then calls `siswaMatchesJenisTagihan()` which returns `true` for empty sasaran) will generate a bogus `pendaftaran`/`daftar_ulang` kategori `Tagihan` for every new `Siswa` created from this point forward, until fixed.

- [x] **Step 1: Write the failing tests** — append to `tests/Feature/Keuangan/StudentBillingEventsTest.php`:

```php
it('does not generate a spurious pendaftaran-kategori tagihan when a new siswa is created and a ppdb jenis_tagihan happens to be active with no sasaran', function () {
    $jenisTagihanPpdb = JenisTagihan::factory()->create(['kategori' => 'pendaftaran', 'is_active' => true]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihanPpdb->lembaga_id]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->where('tagihable_type', Siswa::class)->where('jenis_tagihan_id', $jenisTagihanPpdb->id)->exists())->toBeFalse();
});

it('does not generate a spurious daftar_ulang-kategori tagihan when a siswa changes kelas and a ppdb jenis_tagihan happens to be active with no sasaran', function () {
    $jenisTagihanPpdb = JenisTagihan::factory()->create(['kategori' => 'daftar_ulang', 'is_active' => true]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $jenisTagihanPpdb->lembaga_id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihanPpdb->lembaga_id, 'kelas_id' => null]);
    Tagihan::query()->delete(); // buang tagihan dari StudentCreated supaya tes ini murni soal StudentUpdatedClass

    $siswa->update(['kelas_id' => $kelasBaru->id]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihanPpdb->id)->exists())->toBeFalse();
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/StudentBillingEventsTest.php --filter="spurious"`
Expected: FAIL — a `Tagihan` IS created for the PPDB-kategori `jenis_tagihan` in both cases (reproducing the live bug), OR the test errors with the `\RuntimeException` from Task 2's guard bubbling up uncaught through the listener (also a failure, just a different symptom of the same missing early-filter).

- [x] **Step 3: Fix `app/Listeners/GenerateTagihanForNewStudent.php`**

Add `->whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])` to the query:

```php
    public function handle(StudentCreated $event): void
    {
        $siswa = $event->siswa;

        JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('is_active', true)
            ->whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])
            ->get()
            ->each(function (JenisTagihan $jenisTagihan) use ($siswa) {
                if ($this->matcher->siswaMatchesJenisTagihan($siswa, $jenisTagihan)) {
                    $this->generator->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentCreated');
                }
            });
    }
```

- [x] **Step 4: Fix `app/Listeners/GenerateTagihanForUpdatedClass.php`** — identical change:

```php
    public function handle(StudentUpdatedClass $event): void
    {
        $siswa = $event->siswa;

        JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('is_active', true)
            ->whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])
            ->get()
            ->each(function (JenisTagihan $jenisTagihan) use ($siswa) {
                if ($this->matcher->siswaMatchesJenisTagihan($siswa, $jenisTagihan)) {
                    $this->generator->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentUpdatedClass');
                }
            });
    }
```

- [x] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/StudentBillingEventsTest.php`
Expected: PASS (6/6 — 4 existing + 2 new)

- [x] **Step 6: Verify against the REAL dev database** (this is the exact scenario that's currently armed — confirm it's actually fixed, not just in the test DB)

```bash
php artisan tinker --execute="
\$lembaga = App\Models\Lembaga::first();
\$before = App\Models\Tagihan::count();
\$siswa = App\Models\Siswa::factory()->create(['lembaga_id' => \$lembaga->id]);
\$after = App\Models\Tagihan::where('tagihable_id', \$siswa->id)->where('kategori', 'pendaftaran')->count();
echo 'Spurious pendaftaran tagihan created for new siswa: '.\$after.PHP_EOL;
\$siswa->delete();
"
```
Expected output: `Spurious pendaftaran tagihan created for new siswa: 0`. If this DOES create a factory-based Siswa in the real dev DB as a side effect, delete it afterward (the script already does `$siswa->delete()` — confirm this ran and the dev DB is left clean; if the delete fails for any FK reason, report it rather than leaving orphaned test data).

- [x] **Step 7: Commit**

```bash
git add app/Listeners/GenerateTagihanForNewStudent.php app/Listeners/GenerateTagihanForUpdatedClass.php tests/Feature/Keuangan/StudentBillingEventsTest.php
git commit -m "fix(keuangan): exclude ppdb kategori from StudentCreated/StudentUpdatedClass billing listeners"
```

---

### Task 5: `GenerateTagihanForActivatedBillType` pre-check

**Files:**
- Modify: `app/Listeners/GenerateTagihanForActivatedBillType.php`
- Test: `tests/Feature/Keuangan/BillTypeActivatedEventTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new consumed elsewhere.

- [x] **Step 1: Write the failing test** — append to `tests/Feature/Keuangan/BillTypeActivatedEventTest.php`:

```php
it('does not throw and does not generate anything when a ppdb-kategori jenis_tagihan is reactivated', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'pendaftaran', 'is_active' => false]);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $jenisTagihan->update(['is_active' => true]);

    expect(Tagihan::count())->toBe(0);
    expect(\App\Models\BillingJobLog::count())->toBe(0);
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/BillTypeActivatedEventTest.php --filter="does not throw"`
Expected: FAIL — the `update()` call throws Task 2's `\RuntimeException` uncaught (since `JenisTagihan::booted()`'s `static::updated()` hook fires the event synchronously inside the same request/transaction as the `->update()` call itself)

- [x] **Step 3: Fix `app/Listeners/GenerateTagihanForActivatedBillType.php`**

```php
<?php

namespace App\Listeners;

use App\Events\BillTypeActivated;
use App\Services\TagihanBillingGenerator;

class GenerateTagihanForActivatedBillType
{
    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];

    public function __construct(private readonly TagihanBillingGenerator $generator)
    {
    }

    public function handle(BillTypeActivated $event): void
    {
        if (in_array($event->jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return;
        }

        $this->generator->generate($event->jenisTagihan, 'event', 'BillTypeActivated');
    }
}
```

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/BillTypeActivatedEventTest.php`
Expected: PASS (3/3 — 2 existing + 1 new)

- [x] **Step 5: Commit**

```bash
git add app/Listeners/GenerateTagihanForActivatedBillType.php tests/Feature/Keuangan/BillTypeActivatedEventTest.php
git commit -m "fix(keuangan): skip billing generation when a ppdb-kategori jenis_tagihan is reactivated"
```

---

### Task 6: `ProsesTagihan` console command pre-check

**Files:**
- Modify: `app/Console/Commands/ProsesTagihan.php`
- Test: `tests/Feature/Keuangan/ProsesTagihanCommandTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new consumed elsewhere.

- [x] **Step 1: Write the failing test** — append to `tests/Feature/Keuangan/ProsesTagihanCommandTest.php`:

```php
it('fails gracefully with a clear message for a ppdb-kategori jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'daftar_ulang']);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $this->artisan('billing:proses', ['jenis_tagihan_id' => $jenisTagihan->id])
        ->expectsOutputToContain('tidak bisa diproses lewat billing engine')
        ->assertExitCode(1);

    expect(Tagihan::count())->toBe(0);
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/ProsesTagihanCommandTest.php --filter="ppdb"`
Expected: FAIL — command currently either lets Task 2's exception bubble uncaught (crashing the artisan process) or, if this task runs before Task 2 in isolation, succeeds and creates a `Tagihan` (either way, not the clean `assertExitCode(1)` + message this test expects)

- [x] **Step 3: Fix `app/Console/Commands/ProsesTagihan.php`**

```php
<?php
// app/Console/Commands/ProsesTagihan.php

namespace App\Console\Commands;

use App\Models\JenisTagihan;
use App\Services\TagihanBillingGenerator;
use Illuminate\Console\Command;

class ProsesTagihan extends Command
{
    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];

    protected $signature = 'billing:proses {jenis_tagihan_id}';

    protected $description = 'Generate tagihan manually for one jenis_tagihan (admin "Proses Tagihan" button, or backfill/testing)';

    public function handle(TagihanBillingGenerator $generator): int
    {
        $jenisTagihan = JenisTagihan::find($this->argument('jenis_tagihan_id'));

        if (! $jenisTagihan) {
            $this->error('Jenis tagihan tidak ditemukan.');

            return self::FAILURE;
        }

        if (in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            $this->error("Jenis tagihan berkategori {$jenisTagihan->kategori} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB.");

            return self::FAILURE;
        }

        $log = $generator->generate($jenisTagihan, 'manual');

        $this->info("{$log->bills_generated} tagihan dibuat. Status: {$log->status}.");

        return self::SUCCESS;
    }
}
```

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/ProsesTagihanCommandTest.php`
Expected: PASS (3/3 — 2 existing + 1 new)

- [x] **Step 5: Commit**

```bash
git add app/Console/Commands/ProsesTagihan.php tests/Feature/Keuangan/ProsesTagihanCommandTest.php
git commit -m "fix(keuangan): reject ppdb kategori in billing:proses console command"
```

---

### Task 7: `JenisTagihanSasaranMatcher::countTotalSiswaPool()`

**Files:**
- Modify: `app/Services/JenisTagihanSasaranMatcher.php`
- Test: `tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `countTotalSiswaPool(JenisTagihan $jenisTagihan): int` — consumed by Task 8's controller action to compute `tidak_memenuhi_kriteria`.

- [x] **Step 1: Write the failing test** — append to `tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php`:

```php
it('countTotalSiswaPool counts every siswa in the lembaga regardless of any sasaran kriteria', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $lembagaLain = \App\Models\Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    Siswa::factory()->count(3)->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);
    Siswa::factory()->count(2)->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'P']);
    Siswa::factory()->create(['lembaga_id' => $lembagaLain->id, 'jenis_kelamin' => 'L']);

    $total = (new JenisTagihanSasaranMatcher())->countTotalSiswaPool($jenisTagihan);

    expect($total)->toBe(5); // 3 L + 2 P di lembaga yang sama, kriteria diabaikan; siswa lembaga lain tidak dihitung
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php --filter="countTotalSiswaPool"`
Expected: FAIL (method doesn't exist)

- [x] **Step 3: Add the method to `app/Services/JenisTagihanSasaranMatcher.php`** — add after `resolveTargetSiswa()`:

```php
    public function countTotalSiswaPool(JenisTagihan $jenisTagihan): int
    {
        return Siswa::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $jenisTagihan->lembaga_id)
            ->count();
    }
```

- [x] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php`
Expected: PASS (8/8 — 7 existing + 1 new)

- [x] **Step 5: Commit**

```bash
git add app/Services/JenisTagihanSasaranMatcher.php tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php
git commit -m "feat(keuangan): add countTotalSiswaPool to JenisTagihanSasaranMatcher"
```

---

### Task 8: `JenisTagihanController::prosesTagihan()` action + route

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTagihanController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JenisTagihanProsesTest.php`

**Interfaces:**
- Consumes: `TagihanBillingGenerator::generate()` (Task 2, now guarded), `JenisTagihanSasaranMatcher::resolveTargetSiswa()` (existing), `::countTotalSiswaPool()` (Task 7).
- Produces: `POST admin/jenis-tagihan/{jenisTagihan}/proses` → JSON response, consumed by Task 9's Alpine button.

- [x] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Admin/JenisTagihanProsesTest.php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use App\Services\TagihanBillingGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatUserKeuanganUntukProses(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

it('returns a full breakdown of generated, sudah_tertagih, tidak_memenuhi_kriteria, and gagal', function () {
    [$user, $lembaga] = buatUserKeuanganUntukProses();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'spp', 'default_amount' => 200000]);
    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    $siswaCocokBelumTertagih = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);
    $siswaCocokSudahTertagih = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'P']); // tidak memenuhi kriteria

    app(TagihanBillingGenerator::class)->generateForSiswa($siswaCocokSudahTertagih, $jenisTagihan, 'manual');

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.proses', $jenisTagihan));

    $response->assertOk();
    $response->assertJson([
        'bills_generated' => 1,
        'sudah_tertagih' => 1,
        'tidak_memenuhi_kriteria' => 1,
        'gagal' => 0,
    ]);
    expect(Tagihan::where('tagihable_id', $siswaCocokBelumTertagih->id)->exists())->toBeTrue();
});

it('rejects proses for a ppdb-kategori jenis_tagihan with a 422', function () {
    [$user, $lembaga] = buatUserKeuanganUntukProses();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'pendaftaran']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.proses', $jenisTagihan));

    $response->assertStatus(422);
    expect(Tagihan::count())->toBe(0);
});

it('denies proses without jenis-tagihan.edit permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'spp']);

    $this->actingAs($user)->postJson(route('admin.jenis-tagihan.proses', $jenisTagihan))->assertForbidden();
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JenisTagihanProsesTest.php`
Expected: FAIL (route/action don't exist)

- [x] **Step 3: Add the route** — in `routes/admin.php`, directly after the `jenis-tagihan.destroy` line:

```php
    Route::post('jenis-tagihan/{jenisTagihan}/proses', [JenisTagihanController::class, 'prosesTagihan'])->name('jenis-tagihan.proses');
```

- [x] **Step 4: Add the action to `app/Http/Controllers/Admin/JenisTagihanController.php`**

Add these two imports:
```php
use App\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanBillingGenerator;
```

Add this method, placed after `destroy()`:

```php
    public function prosesTagihan(JenisTagihan $jenisTagihan, JenisTagihanSasaranMatcher $matcher, TagihanBillingGenerator $generator): JsonResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if (in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return response()->json([
                'message' => "Jenis tagihan berkategori {$jenisTagihan->kategori} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB.",
            ], 422);
        }

        $totalPool = $matcher->countTotalSiswaPool($jenisTagihan);
        $targetCount = $matcher->resolveTargetSiswa($jenisTagihan)->count();

        $log = $generator->generate($jenisTagihan, 'manual');

        $gagal = count($log->error_log ?? []);
        $tidakMemenuhiKriteria = $totalPool - $targetCount;
        $sudahTertagih = $targetCount - $log->bills_generated - $gagal;

        return response()->json([
            'message' => "{$log->bills_generated} tagihan dibuat, {$sudahTertagih} sudah tertagih, {$tidakMemenuhiKriteria} tidak memenuhi kriteria, {$gagal} gagal.",
            'bills_generated' => $log->bills_generated,
            'sudah_tertagih' => $sudahTertagih,
            'tidak_memenuhi_kriteria' => $tidakMemenuhiKriteria,
            'gagal' => $gagal,
            'status' => $log->status,
        ]);
    }
```

- [x] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanProsesTest.php`
Expected: PASS (3/3)

- [x] **Step 6: Run the pre-existing Admin jenis-tagihan tests to confirm no regression**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php tests/Feature/Admin/JenisTagihanFormTest.php`
Expected: PASS (21/21, 5/5 — unchanged)

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTagihanController.php routes/admin.php tests/Feature/Admin/JenisTagihanProsesTest.php
git commit -m "feat(keuangan): add prosesTagihan controller action with sudah_tertagih/tidak_memenuhi_kriteria breakdown"
```

---

### Task 9: "Proses Tagihan" button — index page + JS

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/index.blade.php`
- Modify: `resources/js/jenis-tagihan-table.js`
- Test: `tests/Feature/Admin/JenisTagihanProsesButtonTest.php`

**Interfaces:**
- Consumes: `route('admin.jenis-tagihan.proses', $item)` (Task 8).
- Produces: nothing new consumed elsewhere — final integration point.

- [x] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/JenisTagihanProsesButtonTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the Proses Tagihan action for a non-ppdb jenis_tagihan on the index page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp']);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.index'));

    $response->assertOk();
    $response->assertSee('prosesUrl', false);
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JenisTagihanProsesButtonTest.php`
Expected: FAIL (no `prosesUrl` reference exists yet)

- [x] **Step 3: Update `resources/views/admin/jenis-tagihan/index.blade.php`**

Add `prosesUrlTemplate` to the `x-data` config (alongside the existing `deleteUrlTemplate`/`nominalUrlTemplate`/`editUrlTemplate`):

```blade
                prosesUrlTemplate: @js(route('admin.jenis-tagihan.proses', ['jenisTagihan' => '__ID__'])),
```

Add the button inside `<x-table-actions>`, after the "Kelola Nominal" `<template x-if>` block and before the `@endcan` for `jenis-tagihan.edit`:

```blade
                                            <template x-if="!['pendaftaran', 'daftar_ulang'].includes(item.kategori)">
                                                <x-dropdown-link href="#" @click.prevent="prosesTagihan(item)">Proses Tagihan</x-dropdown-link>
                                            </template>
```

- [x] **Step 4: Add the method to `resources/js/jenis-tagihan-table.js`** — add `prosesUrlTemplate` to the returned state object and this method alongside `deleteItem`:

```js
        prosesUrlTemplate: config.prosesUrlTemplate,
```

```js
        async prosesTagihan(item) {
            const confirmed = await confirmDialog(
                'Proses Tagihan?',
                `Proses tagihan untuk "${item.nama}"? Ini akan membuat tagihan baru untuk siswa yang cocok kriteria dan belum tertagih periode ini.`
            );
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(this.prosesUrlTemplate.replace('__ID__', item.id), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal memproses tagihan.');
                    return;
                }

                Alpine.store('toast').push('success', json.message);
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memproses tagihan.');
            }
        },
```

- [x] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanProsesButtonTest.php`
Expected: PASS (1/1)

- [x] **Step 6: Run the full Admin jenis-tagihan test set to confirm no regression**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php tests/Feature/Admin/JenisTagihanFormTest.php tests/Feature/Admin/JenisTagihanProsesTest.php`
Expected: PASS (21/21, 5/5, 3/3)

- [x] **Step 7: Commit**

```bash
git add resources/views/admin/jenis-tagihan/index.blade.php resources/js/jenis-tagihan-table.js tests/Feature/Admin/JenisTagihanProsesButtonTest.php
git commit -m "feat(keuangan): add Proses Tagihan button to jenis-tagihan index"
```

---

### Task 10: Full regression verification + handoff log

**Files:** none (verification-only task)

- [x] **Step 1: Run every test file touched or exercised by this plan**

```bash
php artisan test tests/Feature/Keuangan/ tests/Feature/Admin/JenisTagihanTest.php tests/Feature/Admin/JenisTagihanFormTest.php tests/Feature/Admin/JenisTagihanProsesTest.php tests/Feature/Admin/JenisTagihanProsesButtonTest.php
```
Expected: all PASS, no failures.

- [x] **Step 2: Run the full project suite** (single foreground run — never in background, never concurrent with another `php artisan test` process, per the repeated shared-test-DB corruption lesson from Sub-project 2a/2b-1)

```bash
php artisan test
```
Expected: same pass/fail count as the `demo` baseline established after Sub-project 2b-1 (1398 passed / 6 pre-existing unrelated failures — `LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest`). Any NEW failure beyond this baseline is a real regression from this plan and must be fixed before moving on. Expect the passed count to be higher than 1398 given this plan's new tests.

- [x] **Step 3: Re-verify the real dev DB one more time** (Task 4 already did a targeted check; this is the final confirmation after all 10 tasks are in)

```bash
php artisan tinker --execute="
\$rows = App\Models\JenisTagihan::whereIn('kategori', ['pendaftaran','daftar_ulang'])->where('is_active', true)->count();
echo 'Active PPDB-kategori jenis_tagihan rows still is_active=true (expected, not itself a problem — the guard, not is_active, is what protects them now): '.\$rows.PHP_EOL;
\$spurious = App\Models\Tagihan::where('tagihable_type', App\Models\Siswa::class)->whereIn('kategori', ['pendaftaran','daftar_ulang'])->count();
echo 'Spurious Siswa-tagihable PPDB-kategori Tagihan rows (must be 0): '.\$spurious.PHP_EOL;
"
```
Expected: the spurious count is `0`.

- [x] **Step 4: Write the handoff log**

Per `AGENTS.md` Stage 7, write `.agents/logs/keuangan-02b2-proses-tagihan.md` covering: the live-bug discovery and its fix (Task 4, the highest-priority part of this plan), the defense-in-depth guard design across all 5 callers, the `JenisTagihanFactory` default change and why it had to happen first (Task 1), the `sudah_tertagih`/`tidak_memenuhi_kriteria` split and its computation trade-off (extra query, deliberate), the "Proses Tagihan" button, and current git state.
