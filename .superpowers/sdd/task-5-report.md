# Task 5 Report: Dashboard route, controller, skip-alert banner, views, sidebar entry

## Summary

Implemented the parent-facing finance dashboard: `SkipAlertResolver` service, `Keuangan\DashboardController`,
two Blade views (`keuangan.dashboard`, `keuangan.tanpa-anak`), the `keuangan.dashboard` route, and the sidebar
nav entry, following `task-5-brief.md`. Two deviations from the brief's verbatim code were required to make the
brief's own Step-1 tests pass (see **Deviations** below) — both were found via TDD, not introduced speculatively.

## Files changed

- Created `app/Services/Finance/SkipAlertResolver.php`
- Created `app/Http/Controllers/Keuangan/DashboardController.php`
- Created `resources/views/keuangan/dashboard.blade.php`
- Created `resources/views/keuangan/tanpa-anak.blade.php`
- Created `tests/Feature/Keuangan/DashboardControllerTest.php`
- Modified `routes/web.php` (registered `keuangan.dashboard` route group, before `require __DIR__.'/spmb.php';`)
- Modified `resources/views/layouts/sidebar.blade.php` (added "Dompet & Tagihan Saya" nav entry under Keuangan group)

## Test-driven flow

1. Wrote `tests/Feature/Keuangan/DashboardControllerTest.php` exactly as given in the brief (5 test cases).
2. Ran it before any implementation existed:
   `php artisan test tests/Feature/Keuangan/DashboardControllerTest.php`
   → Result: **5 failed** — `RouteNotFoundException: Route [keuangan.dashboard] not defined.` (expected).
3. Implemented `SkipAlertResolver`, `DashboardController`, both views, the route, and the sidebar entry verbatim
   per the brief.
4. Ran the test file again:
   `php artisan test tests/Feature/Keuangan/DashboardControllerTest.php`
   → Result: **3 passed, 2 failed**. Investigated both failures with a throwaway debug test (removed afterward)
   rather than guessing:
   - `it shows the skip-alert banner when balance cannot cover the highest-priority tagihan` — failed because the
     verbatim `SkipAlertResolver` algorithm (copied from `AutoAllocationEngine::run()`) treats *any* partial
     allocation (`amountToPay > 0`) as "allocated", so a single under-funded tagihan (saldo 50.000 vs tagihan
     200.000) was marked allocated, never landing in the `$skipped` collection — the resolver returned `null`
     and no banner rendered. See **Deviations** for the fix.
   - `it shows the "tanpa anak" page...` — failed because the view's heading text
     "Belum ada anak terdaftar" (capital B, as written in the brief's Step 5 code) does not contain the
     case-sensitive substring the brief's own test asserts: `assertSee('belum ada anak terdaftar', false)`.
5. Applied both fixes (below), reran:
   `php artisan test tests/Feature/Keuangan/DashboardControllerTest.php`
   → Result: **5 passed (10 assertions)**.
6. Regression check — ran the whole `Keuangan` feature directory alone (not the full suite, per the
   controller's instruction to avoid racing concurrent `php artisan test` runs against the same MySQL test DB):
   `php artisan test tests/Feature/Keuangan/`
   → Result: **140 passed (371 assertions)**, no regressions.

## Deviations from the brief's verbatim code, with justification

### 1. `SkipAlertResolver::resolve()` — allocation-walk semantics changed from "partial counts as allocated" to "full-or-skip"

The brief's Step 3 code is a byte-for-byte copy of `AutoAllocationEngine::run()`'s walk: for each tagihan in
priority order, `amountToPay = min($saldo, $sisaTagihan)`; if `amountToPay > 0` the tagihan is marked
"allocated" (even if only partially paid) and removed from the candidate "skipped" list. This exactly mirrors
production engine behavior (confirmed by reading `AutoAllocationEngine.php`), where `$skippedTagihan` only ever
contains tagihan that received **zero** allocation.

Under that definition, the brief's own Step-1 test 2 (single tagihan, `net_amount` 200.000, wallet balance
50.000) can never produce a "skip": the tagihan receives a non-zero partial allocation (50.000) and is
therefore "allocated", not "skipped" — `resolve()` returns `null`, and the test's `assertSee('150.000', false)`
fails. This was confirmed directly: the join/where query correctly returns the one tagihan row, and the
`isEmpty()`/`whereNotIn` filtering was the point of divergence, verified via a scratch debug test.

Given `SkipAlertResolver`'s own docblock says it exists "used ONLY to compute what the banner should show" and
the dashboard banner copy is "Saldo tidak cukup untuk {jenis} … Kekurangan Rp{selisih} agar tagihan ini bisa
terbayar" (a full-coverage framing, not a partial-allocation framing), I changed the walk to a full-or-skip
model instead of partial-allocation-counts-as-covered:

```php
foreach ($tagihans as $tagihan) {
    $sisaTagihan = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;

    if ($saldo >= $sisaTagihan) {
        $saldo -= $sisaTagihan;
        continue;
    }

    $selisih = $sisaTagihan - $saldo;
    // ... return ['tagihan' => ..., 'selisih' => $selisih];
}
return null;
```

This satisfies all three balance-related brief tests: test 1 (no tagihan, n/a), test 2 (50.000 vs 200.000 →
selisih 150.000, banner shown), test 3 (500.000 fully covers 200.000 → no banner). It does **not** touch
`AutoAllocationEngine` itself (per the plan's Global Constraint that 6a must not modify or invoke that engine's
write path) — this is purely a read-only, display-only reinterpretation local to the new resolver class.

I flag this explicitly because it is a real behavioral divergence from "replica of `AutoAllocationEngine::run()`'s
... allocation walk" as the brief's docblock comment states, even though I left that docblock comment in place
(it remains accurate for the *ordering* logic, just not the *allocated-vs-skipped* classification). If task 6b
(or a later reviewer) expects byte-identical semantics with the real engine, this should be revisited —
possibly by asking product/spec owner whether the banner should ever fire for partially-payable tagihan at all
under the real engine's actual behavior (currently: it would not).

A related fix was needed in the same method: `$tagihan->load('jenisTagihan')` failed with "Attempt to read
property 'nama' on null" because `JenisTagihan` uses the `BelongsToTenant` trait (global `TenantScope`), and the
authenticated orang_tua user's `lembaga_id` is `null`, so the scoped `jenisTagihan()` relation silently returned
nothing for a `JenisTagihan` row belonging to a different lembaga. Fixed by loading the relation with
`->withoutGlobalScope(TenantScope::class)`, consistent with how the tagihan query itself already does this (and
consistent with `ResolveActiveSiswa`'s established pattern of bypassing `TenantScope` for orang_tua cross-tenant
reads).

### 2. `tanpa-anak.blade.php` — heading text lowercased

Brief's Step 5 view has `<p ...>Belum ada anak terdaftar</p>` (capitalized). The brief's Step-1 test asserts
`assertSee('belum ada anak terdaftar', false)` (all-lowercase, and `assertSee` is case-sensitive). Changed the
rendered heading text to lowercase `belum ada anak terdaftar` to satisfy the test literally, since the brief's
test is presumably authoritative for what real-world sub-project 6a QA / other tasks might assert against. This
is a purely cosmetic change (Indonesian doesn't strictly require sentence-case headings) with no functional
impact.

No other deviations. Route registration, controller, sidebar entry, and the `dashboard.blade.php` wallet/VA/
notification-feed rendering match the brief verbatim.

## Manual browser verification

No interactive browser/Playwright tool was available in this session's toolset (Windows/Laragon environment,
no `chromium-cli` or MCP browser tool present). Instead, verification was done by driving the real running dev
server (`http://localhost/pintera-app/public`, confirmed live and serving the actual Laravel app — note:
`http://pintera-app.test/` on this machine currently serves an Apache directory listing of the repo root, not
the app; the working vhost path is `http://localhost/pintera-app/public`) via `curl` with a cookie jar, exactly
reproducing the login → navigate flow a human would do in a browser, against the real dev MySQL database.

**Step A — admin side (child link check):**
- Logged in as `superadmin@sistem.test` / `password` via `POST /login` → `302` redirect to `/dashboard` (success).
- Fetched `/admin/orang-tua` (`200 OK`) and confirmed phone number `081234560001` — the exact `no_hp` seeded for
  the demo parent in `OrangTuaKaryawanSeeder.php` (`'no_hp' => '081234560001'`, `'email' => 'ortu.demo@...'`) —
  is present in the listing, confirming the orang tua record exists and is seeded. (The listing table doesn't
  render email column, so matched by phone number instead, per the brief's suggested fallback.)
- Did not need to re-run `OrangTuaKaryawanSeeder` — the record and its child link were already present (verified
  conclusively in Step B below, since the dashboard rendered the wallet view, not the "tanpa anak" empty state).

**Step B — parent-facing dashboard:**
- Logged in as `ortu.demo@permatakraksaan.sch.id` / `password` via `POST /login` → `302` redirect to
  `/dashboard` (success).
- Fetched `GET /keuangan` → **`200 OK`**.
- Response contained:
  - `<h2>Dompet &amp; Tagihan — Eliana Putri</h2>` (proves `activeSiswa` resolved correctly to the linked child,
    NOT the `tanpa-anak` empty state)
  - `Saldo Wallet` label with balance `Rp500.000`
  - `Notifikasi Terbaru` section header
  - No skip-alert banner (no `Top-up Rp...` string found) — consistent with a fully-funded wallet
  - No PHP exception/error markers (`Exception`, `Fatal error`, `Whoops` all absent; the only `error` substring
    hits were Tailwind CSS class names like `bg-error-500`, not real errors)

This confirms the dashboard renders correctly end-to-end against the real dev database for the demo account,
including the tenant-scope fix in `SkipAlertResolver` (which would have thrown on any lembaga-scoped
`jenisTagihan` lookup had it not been applied — though in this particular account's data there was no
outstanding tagihan to trigger that code path, the automated test suite's skip-alert tests do exercise it and
pass).

I did not visually screenshot the page (no browser/screenshot tool available) — if a true visual check is
required, recommend running `/run-skill-generator` to capture a Playwright-based verification skill for this
project, as suggested by the `run` skill's fallback guidance.

## Commit

```
77ee... feat(keuangan): add orang tua dashboard with wallet card and skip-alert banner
```
(see actual hash below, filled in after commit)

## Test commands run (final)

```
php artisan test tests/Feature/Keuangan/DashboardControllerTest.php
# Tests: 5 passed (10 assertions)

php artisan test tests/Feature/Keuangan/
# Tests: 140 passed (371 assertions)
```
