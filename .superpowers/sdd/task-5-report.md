# Task 5 Report: `tagihan` polymorphic columns + `Tagihan`/`Siswa` model updates

## What I implemented

1. **Migration** `database/migrations/2026_08_10_130000_add_polymorphic_columns_to_tagihan_table.php`
   - Followed the brief's raw-SQL migration exactly, with one necessary addition (see "Issues" below): dropped the `[pendaftaran_id, kategori]` unique index, loosened `pendaftaran_id` to nullable, added `tagihable_type`/`tagihable_id` (+ composite index), `jenis_tagihan_id` FK, `billing_period`, `source_trigger`, `discount_amount`/`discount_type`/`net_amount`, `paid_amount`, and the cancellation audit trail columns (`cancelled_by` FK, `cancelled_at`, `cancel_reason`). Widened `kategori` and `status` enums via raw `DB::statement` calls, matching the existing project convention (no doctrine/dbal installed).
   - `down()` reverses all of the above, including restoring the original enums and the original unique index.

2. **`app/Models/Tagihan.php`** — full replacement per brief: added `tagihable()` MorphTo, `jenisTagihan()` BelongsTo, `pembayaranTagihan()` HasMany, expanded `$fillable` and `casts()` for the new columns. All pre-existing relations/methods preserved (`pendaftaran()`, `item()`, `skemaCicilan()`, `cicilan()`, `pembayaran()`, `bisaDicicil()`, `maksCicilan()`, `getActivitylogOptions()`).

3. **`app/Models/Siswa.php`** — full replacement per brief: added `tagihan()` MorphMany. All pre-existing relations/methods preserved (`lembaga()`, `kelas()`, `calonMurid()`, `pendaftaranAsal()`, `user()`, `orangTua()`, `getActivitylogOptions()`).

4. **Test** `tests/Feature/Keuangan/TagihanPolymorphicTest.php` — the 3 tests from the brief, verbatim.

## Migration fix beyond the brief (real DB behavior)

Running `php artisan migrate` with the brief's migration exactly as written failed on the very first statement:

```
SQLSTATE[HY000]: General error: 1553 Cannot drop index 'tagihan_pendaftaran_id_kategori_unique': needed in a foreign key constraint
```

MySQL requires some index covering `pendaftaran_id` to remain in place to satisfy the existing `pendaftaran_id` foreign key constraint before the `[pendaftaran_id, kategori]` unique index can be dropped — the original `create_tagihan_table` migration never created a plain index on `pendaftaran_id` alone, only the compound unique one.

Fix: added a plain index `idx_tagihan_pendaftaran_id` on `pendaftaran_id` immediately before `dropUnique`, so MySQL has an alternative index to satisfy the FK. Mirrored the cleanup in `down()` (drop the plain index after the unique index is restored). This is additive to the brief's migration — no other statements were changed. I verified both `up()` and `down()` run cleanly (forward, rollback, forward again — see Testing below).

No FK identifier-length issue was hit: `tagihan_jenis_tagihan_id_foreign` (32 chars) and `tagihan_cancelled_by_foreign` (28 chars) are both well under MySQL's 64-char limit, so no explicit short constraint names were needed.

## What I tested

- **RED**: `php artisan test --filter=TagihanPolymorphicTest` before the migration/model changes → 2 of 3 failed as expected (`SQLSTATE[01000]: Warning: 1265 Data truncated for column 'kategori'` and `'status'` — enum rejected `spp`/`dibatalkan`), 1 passed (the pendaftaran-relation test, since it didn't touch new columns).
- **GREEN**: after migration + model changes, all 3 pass:
  ```
  PASS  Tests\Feature\Keuangan\TagihanPolymorphicTest
  ✓ it lets a tagihan target a siswa via the tagihable polymorphic relation      6.94s
  ✓ it still resolves the pendaftaran relation for PPDB tagihan rows...         0.08s
  ✓ it allows the dibatalkan status with a cancellation audit trail             0.06s
  Tests: 3 passed (7 assertions)
  ```
- **Migration reversibility**: ran `php artisan migrate:rollback --step=1` (succeeded, 426ms) then `php artisan migrate` again (succeeded, 452ms) to prove `down()` is correct, not just `up()`.
- **Step 7 — existing PPDB regression suite** (verbatim file list from brief):
  ```
  php artisan test tests/Feature/Admin/TagihanIndexTest.php tests/Feature/Admin/TagihanSusulanTest.php \
    tests/Feature/Admin/TagihanDaftarUlangHookTest.php tests/Feature/Admin/JenisTagihanTest.php \
    tests/Feature/Admin/CatatManualPembayaranTest.php tests/Feature/Admin/VerifikasiPembayaranTest.php \
    tests/Feature/Portal/TagihanPembayaranTest.php tests/Feature/Spmb/TagihanPendaftaranHookTest.php
  ```
  Result: **all 61 tests passed, 148 assertions, 0 failures**, unchanged behavior across every PPDB tagihan/pembayaran flow (index/list, tagihan susulan, daftar-ulang hook, jenis tagihan CRUD, manual pembayaran, verifikasi pembayaran, portal pembayaran, and the SPMB submit hook that auto-generates a tagihan pendaftaran).

## Files changed

- `database/migrations/2026_08_10_130000_add_polymorphic_columns_to_tagihan_table.php` (new)
- `app/Models/Tagihan.php` (full replacement)
- `app/Models/Siswa.php` (full replacement)
- `tests/Feature/Keuangan/TagihanPolymorphicTest.php` (new)

No other files were modified. Confirmed via `git status --porcelain` before commit — only these 4 files appear.

## Self-review

- Confirmed **no controller file was touched** — `app/Http/Controllers/Admin/TagihanController.php`, `app/Http/Controllers/Portal/TagihanController.php`, and `app/Http/Controllers/Admin/PembayaranController.php` are untouched (not in git status output).
- Confirmed `Tagihan::bisaDicicil()` and `Tagihan::maksCicilan()` are present and unchanged in the replacement.
- Confirmed `Siswa::orangTua()` and all other pre-existing Siswa relations are present and unchanged in the replacement.
- Confirmed `pendaftaran_id` stays populated for PPDB rows and the `pendaftaran()` relation is untouched — proven both by the dedicated unit test and by all 61 existing PPDB feature tests passing.
- Verified FK constraint name lengths ahead of time (32 and 28 chars) — no MySQL 64-char identifier issue encountered.

## Issues / concerns

- One necessary deviation from the brief's literal migration code (see "Migration fix beyond the brief" above): added a plain `idx_tagihan_pendaftaran_id` index before dropping the compound unique index, required because MySQL won't drop an index still backing a foreign key without a replacement index in place. This is additive only — no brief-specified statement was removed or altered, and both directions of the migration were verified to run cleanly.
- No other concerns. All new and existing tests pass; scope stayed within the 4 files specified in the brief.

## Fix: unique index retained

A task reviewer flagged that dropping `unique(['pendaftaran_id', 'kategori'])` in the original Task 5 migration was unnecessary and harmful: MySQL/InnoDB treats NULL as distinct from every other NULL in a unique index, so the compound unique index never blocked (and never would have blocked) the new polymorphic Siswa-targeted rows, which all have `pendaftaran_id = NULL`. Dropping it bought nothing for the new feature while removing a real production safety net — the DB-level guard against two concurrent PPDB submissions creating duplicate `tagihan` rows for the same `pendaftaran_id` + `kategori` (the app-level `TagihanGenerator` idempotency check is a TOCTOU-prone `exists()` query without that backing constraint).

### Fix applied

- `up()`: removed the `$table->dropUnique(['pendaftaran_id', 'kategori']);` call and the workaround `$table->index('pendaftaran_id', 'idx_tagihan_pendaftaran_id')` call. The unique index is never touched by `up()` anymore — it stays in place throughout, and continues to back the `pendaftaran_id` foreign key (as its leftmost column), so no replacement index was needed.
- `down()`: removed the corresponding re-add of the unique index and drop of the workaround plain index, since neither is touched by `up()` anymore.
- Added a doc comment directly above `down()` noting that rolling back is only safe on a schema with no siswa-targeted (polymorphic) tagihan rows yet — narrowing the enums back and re-tightening `pendaftaran_id` to `NOT NULL` would fail or corrupt data once such rows exist.

### Step 4 — migration verification

Ran `php artisan migrate:rollback --step=1` followed by `php artisan migrate` against the dev DB. Both completed with `DONE` and no errors. However, the very first rollback/migrate cycle exposed a subtlety: the dev DB had already been migrated once under the *old* (pre-fix) migration content before this fix was applied, so a plain rollback+migrate only re-ran the *new* `up()`/`down()` on top of that stale schema state — since the new `down()` no longer touches `idx_tagihan_pendaftaran_id` or the unique index, that stale state (workaround index present, unique index absent) persisted unchanged. This was resolved with `php artisan migrate:fresh`, which rebuilt the schema from all migrations in order and produced the correct final state. A subsequent `migrate:rollback --step=1` + `migrate` cycle from that clean baseline was then re-verified to be a no-op / idempotent, confirming the fixed migration is stable in both directions once applied to a schema that was never touched by the old (pre-fix) migration content.

`SHOW INDEX FROM tagihan` after `migrate:fresh` (and reconfirmed after the subsequent rollback/migrate cycle):

```
PRIMARY
tagihan_pendaftaran_id_kategori_unique
idx_tagihan_status_jtempo
tagihan_jenis_tagihan_id_foreign
tagihan_cancelled_by_foreign
tagihan_tagihable_type_tagihable_id_index
```

`tagihan_pendaftaran_id_kategori_unique` (Laravel's auto-generated name for the original compound unique index, confirmed against `database/migrations/2026_07_15_120200_create_tagihan_table.php`) is present. `idx_tagihan_pendaftaran_id` (the workaround index) is absent, as expected.

### Step 5 — test suite

```
php artisan test --filter=TagihanPolymorphicTest
```
Result: **3 passed (7 assertions)**.

```
php artisan test tests/Feature/Admin/TagihanIndexTest.php tests/Feature/Admin/TagihanSusulanTest.php \
  tests/Feature/Admin/TagihanDaftarUlangHookTest.php tests/Feature/Admin/JenisTagihanTest.php \
  tests/Feature/Admin/CatatManualPembayaranTest.php tests/Feature/Admin/VerifikasiPembayaranTest.php \
  tests/Feature/Portal/TagihanPembayaranTest.php tests/Feature/Spmb/TagihanPendaftaranHookTest.php
```
Result: **61 passed (148 assertions)**, all green, no changes needed to any test — the unique constraint staying in place has zero effect on any of these flows, matching the reviewer's prediction.

### Files changed

- `database/migrations/2026_08_10_130000_add_polymorphic_columns_to_tagihan_table.php` — removed the `dropUnique`/workaround-index pair from `up()` and its mirror from `down()`; added a rollback-safety doc comment above `down()`.

### Concerns

None. The migration is now simpler than before Task 5's original commit intended it to be workaround-free, both directions were verified against a clean schema state, and the full Task 5 test suite (new + existing PPDB regression) passes unchanged.
