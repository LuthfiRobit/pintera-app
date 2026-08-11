# Task 2 Report: JenisTagihanSasaranMatcher service

## What was implemented

- `app/Services/JenisTagihanSasaranMatcher.php` — new service, transcribed verbatim from the task brief. Provides:
  - `resolveTargetSiswa(JenisTagihan $jenisTagihan): Collection<int, Siswa>` — bulk DB query, OR-of-AND over `jenis_tagihan_sasaran_grup` / `jenis_tagihan_sasaran_kriteria`, scoped to `lembaga_id`, with `kelas` eager-loaded. Bypasses `TenantScope` on `Siswa` via `Siswa::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)` so evaluation is driven by the `jenis_tagihan`'s actual `lembaga_id`, not the current session's tenant.
  - `siswaMatchesGrup(Siswa $siswa, JenisTagihanSasaranGrup $grup): bool` — in-memory AND over a single grup's kriteria.
  - `siswaMatchesJenisTagihan(Siswa $siswa, JenisTagihan $jenisTagihan): bool` — lembaga check + OR over all sasaran grups (true if no grups at all).
  - Private helpers `applyKriteriaToQuery()` (query-builder side, handles `lembaga`/`kelas`/`jenis_kelamin`/`status_siswa`/`tahun_ajaran`/`tingkat` fields, `in`/`not_in` operators, with `tahun_ajaran`/`tingkat` going through `whereHas`/`whereDoesntHave` on the `kelas` relation) and `siswaMatchesKriteria()` (in-memory equivalent, `status` compared via `->value` since `Siswa::status` is cast to the `StatusSiswa` enum).

- `tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php` — the brief's 6 tests, used verbatim, no assertions altered.

## Verification (TDD evidence)

**RED** — before creating the service:
```
php artisan test --filter=JenisTagihanSasaranMatcherTest
```
Result: 6 failed, all `Error: Class "App\Services\JenisTagihanSasaranMatcher" not found`.

**GREEN** — after creating the service:
```
php artisan test --filter=JenisTagihanSasaranMatcherTest
```
Result:
```
PASS  Tests\Feature\Keuangan\JenisTagihanSasaranMatcherTest
✓ it returns every siswa in the lembaga when there is no sasaran grup at all
✓ it matches siswa by AND-ing every kriteria within one grup
✓ it OR-s multiple sasaran grup together
✓ it excludes siswa matching a not_in kriteria
✓ it matches tahun_ajaran and tingkat kriteria through the kelas relation
✓ it siswaMatchesJenisTagihan is true for an empty sasaran and false for a non-matching lembaga

Tests:    6 passed (10 assertions)
Duration: 7.30s
```

## Files changed

- `app/Services/JenisTagihanSasaranMatcher.php` (new, 120 lines)
- `tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php` (new, 112 lines)

No existing files (models, factories) were modified.

## Self-review

- Confirmed `Siswa::withoutGlobalScope(TenantScope::class)` is present exactly as the brief specified, inside `resolveTargetSiswa()`.
- Confirmed all 6 tests from the brief are present verbatim and pass; no assertion was weakened or altered to force a pass.
- Confirmed `app/Models/Scopes/TenantScope.php` exists and matches the import used.
- Confirmed `Siswa::status` is cast to `App\Enums\StatusSiswa` (backed enum), matching the brief's use of `$siswa->status->value` in `siswaMatchesKriteria()`.
- Confirmed `JenisTagihan::sasaranGrup()`, `JenisTagihanSasaranGrup::kriteria()`, and `Siswa::kelas()` relations already exist from Sub-project 1 and match what the service calls.
- File placement matches the flat `app/Services/` convention (no subfolder created).

## Concerns

None. The transcription matched the existing schema/model surface exactly; no discrepancies were found between the brief's assumptions and the actual codebase.

## Fix: kelas not_in NULL handling

### The bug

The query path (`applyKriteriaToQuery`, `case 'kelas':`) and the PHP path (`siswaMatchesKriteria`) disagreed on `not_in kelas` for a siswa with `kelas_id = null`:

- Query path issued `$query->whereNotIn('kelas_id', $values)`. In SQL, `NULL NOT IN (...)` is unknown/false, so rows with `kelas_id IS NULL` were **excluded**.
- PHP path computed `in_array(null, $values)` → `false`, then `! false = true` for the `not_in` operator, so the same siswa was **included**.

This was inherited verbatim from the original plan text (the brief in the "What was implemented" section above), not introduced during the initial transcription.

### The fix

In `applyKriteriaToQuery()`, the `case 'kelas':` branch's `not_in` path now wraps the condition in a nested `where()` closure so `kelas_id IS NULL` rows are included, grouped correctly so it stays AND-scoped within its enclosing grup regardless of the outer `orWhere`-across-grups nesting:

```php
case 'kelas':
    if ($isIn) {
        $query->whereIn('kelas_id', $values);
    } else {
        $query->where(function (Builder $q) use ($values) {
            $q->whereNotIn('kelas_id', $values)->orWhereNull('kelas_id');
        });
    }
    break;
```

The `in` branch is unchanged — `whereIn('kelas_id', $values)` already naturally excludes NULL rows, matching the PHP path (`in_array(null, $values)` is `false` for any non-null-containing `$values`).

### TDD evidence

**RED** — new test added (`it treats siswa with kelas_id null as matching a not_in kelas kriteria, agreeing with the PHP path`), run against the pre-fix query code (`case 'kelas':` still doing plain `whereNotIn`):
```
FAILED  Tests\Feature\Keuangan\JenisTagihanSasaranMatcherTest > it treats siswa with kelas_id null as matching a…
Failed asserting that two arrays are identical.
--- Expected
+++ Actual
@@ @@
 Array &0 [
-    0 => 1,
-    1 => 2,
+    0 => 2,
 ]
Tests:    1 failed (1 assertions)
```
(siswa with `kelas_id = null` was missing from the result — confirming the query path excluded it while the PHP path would have matched it.)

**GREEN** — after applying the fix:
```
✓ it treats siswa with kelas_id null as matching a not_in kelas kriteria, agreeing with the PHP path   0.04s
```

### Full test file run (post-fix)

```
php artisan test --filter=JenisTagihanSasaranMatcherTest

PASS  Tests\Feature\Keuangan\JenisTagihanSasaranMatcherTest
✓ it returns every siswa in the lembaga when there is no sasaran grup at all
✓ it matches siswa by AND-ing every kriteria within one grup
✓ it OR-s multiple sasaran grup together
✓ it excludes siswa matching a not_in kriteria
✓ it matches tahun_ajaran and tingkat kriteria through the kelas relation
✓ it treats siswa with kelas_id null as matching a not_in kelas kriteria, agreeing with the PHP path
✓ it siswaMatchesJenisTagihan is true for an empty sasaran and false for a non-matching lembaga

Tests:    7 passed (12 assertions)
Duration: 7.40s
```

### Files changed

- `app/Services/JenisTagihanSasaranMatcher.php` — fixed `case 'kelas':` `not_in` branch.
- `tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php` — added regression test.

### Concerns

None. Fix is scoped to the `kelas` field's `not_in` branch only; no other field/operator combination was touched, and the AND/OR grouping across grups (verified by the existing "OR-s multiple sasaran grup together" test, still passing) is unaffected.
