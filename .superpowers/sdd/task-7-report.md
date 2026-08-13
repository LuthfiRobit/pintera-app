# Task 7 Report: Playwright verification + scoped regression + full-suite gate

Sub-project: Keuangan 6c (Riwayat Transaksi & Kwitansi PDF + Pengaturan Yayasan)
Task type: verification-only. One real pre-existing bug (test-only, not
production code) was found and fixed during verification: a global PHP
function name collision that fatally crashed the full test suite.

## Step 1: Tinker fixture (real dev DB)

Command run:

```bash
php artisan tinker --execute="
\$siswa = \App\Models\Siswa::whereHas('orangTua.user', fn(\$q) => \$q->where('email', 'ortu.demo@permatakraksaan.sch.id'))->first();
\$jenis = \App\Models\JenisTagihan::first();
\$tagihan = \App\Models\Tagihan::updateOrCreate(
    ['tagihable_id' => \$siswa->id, 'tagihable_type' => \App\Models\Siswa::class, 'jenis_tagihan_id' => \$jenis->id, 'status' => 'lunas'],
    ['total_tagihan' => 25000, 'net_amount' => 25000, 'paid_amount' => 25000, 'jatuh_tempo' => now()->subDays(3)]
);
\$pembayaran = \App\Models\Pembayaran::firstOrCreate(
    ['siswa_id' => \$siswa->id, 'metode' => 'cash', 'status' => 'lunas'],
    ['channel_reference' => (string) \Illuminate\Support\Str::uuid()]
);
\App\Models\PembayaranTagihan::firstOrCreate(
    ['pembayaran_id' => \$pembayaran->id, 'tagihan_id' => \$tagihan->id],
    ['amount_allocated' => 25000]
);
echo 'riwayat fixture ready, pembayaran id: '.\$pembayaran->id.PHP_EOL;
"
```

Output:
```
riwayat fixture ready, pembayaran id: 14
```

The demo-account lookup by student name worked directly (no fallback needed).

## Step 2: Playwright check added

Read `scripts/keuangan-6a-browser-check.mjs` in full first to match its
existing login flow, `console.log` format, and dispatch-block pattern.
Appended `checkRiwayatKwitansi(page)` (exact code from the brief, unmodified)
and wired it into the dispatch block under flag `riwayat`. Also updated the
top-of-file usage comment to list the new `riwayat` flag.

## Step 3: Playwright check run (live dev server)

Command:
```bash
KEUANGAN_CHECK_BASE_URL=http://localhost:8000 node scripts/keuangan-6a-browser-check.mjs --check=riwayat
```

Output:
```
[riwayat] history page renders lunas row and kwitansi PDF link returns application/pdf: OK
```

## Step 4: Scoped regression suite

Command:
```bash
php artisan test tests/Feature/Keuangan/ tests/Feature/Admin/YayasanSettingControllerTest.php tests/Unit/PermissionSeederTest.php tests/Unit/RoleSeederTest.php tests/Feature/RolePermissionSeederTest.php
```

Result: **220 passed (704 assertions)**, 0 failed. Duration 160.47s.
(Full per-test listing captured in tool transcript; all suites — Keuangan
feature tests, YayasanSettingControllerTest, PermissionSeederTest,
RoleSeederTest, RolePermissionSeederTest — reported PASS.)

## Step 5: Full-suite gate

### First full-suite attempt uncovered a real bug (not a flake)

The first `php artisan test` run fatally crashed before completing, with:

```
Pest\Exceptions\FatalException
Cannot redeclare actingAsYayasanSuperAdmin() (previously declared in
D:\laragon\www\pintera-app\tests\Feature\Admin\KaryawanCrudTest.php:29)
at tests\Feature\Admin\YayasanSettingControllerTest.php:13
```

Root cause: `YayasanSettingControllerTest.php` (added in Task 5 of this
sub-project) declared a global helper function `actingAsYayasanSuperAdmin()`
with a different signature/behavior than an identically-named global
function already present in `tests/Feature/Admin/KaryawanCrudTest.php`
(pre-existing, unrelated to this sub-project). Pest loads all test files'
top-level functions into one global namespace, so when both files are in
the same suite run, the second declaration is a fatal PHP redeclaration
error. This wasn't caught by the scoped run in Step 4 because that scope
excludes `KaryawanCrudTest.php`.

This is test-only code — it does not touch `PaymentService`,
`PaymentAllocationService`, `AutoAllocationEngine`, or `Wallet` — so it was
in scope to fix under "if any pre-existing test now fails, it's a real
regression from this plan — fix before continuing" (a fatal crash blocking
the whole suite is a more severe case of the same principle). Fix: renamed
the Task 5 helper to `actingAsYayasanSettingSuperAdmin()` in
`tests/Feature/Admin/YayasanSettingControllerTest.php` (all 4 call sites).
Re-ran `php artisan test tests/Feature/Admin/YayasanSettingControllerTest.php`
afterward: 5 passed (13 assertions), confirming the fix didn't change test
semantics.

### Full-suite run (after the fix), in isolation

Confirmed via `tasklist //FI "IMAGENAME eq php.exe"` that no other test
process was running (only the two `php artisan serve` dev-server PIDs were
present) before starting, and again before the final run.

The `php artisan test` run took longer than the 600s hard cap on a single
foreground tool call and was auto-backgrounded by the harness twice. Per
process-rule correction from the coordinator, the second background
instance's output file was polled synchronously and repeatedly from the
foreground (no new commands were themselves backgrounded by choice) until
completion, then read in full.

Command: `php artisan test`

Final result line:
```
Tests:    6 failed, 1578 passed (4874 assertions)
Duration: 752.32s
```

This exactly matches the documented 6-failure baseline count. The captured
log lost most of the individual FAIL block detail due to terminal
carriage-return redraw artifacts in the piped output capture, so the 6
failures were independently re-confirmed by running the three known
baseline classes directly in isolation:

```bash
php artisan test --filter="LembagaCrudTest|RoleBuilderTest|RoleFormAuditBannerTest"
```

Result: **6 failed, 31 passed (92 assertions)**, Duration 26.09s — the
failures are exactly:
- `Tests\Feature\Admin\LembagaCrudTest > it paginates the index at 10 per page` (1)
- `Tests\Feature\Admin\RoleBuilderTest` (4):
  - it returns a paginated, searchable, sortable JSON payload from the datatable endpoint
  - it filters the datatable endpoint by search and scope
  - it denies the datatable endpoint to a user without roles.view permission
  - it renders the roles index page with the datatable mount point instead of a server-rendered table
- `Tests\Feature\Admin\RoleFormAuditBannerTest > it renders the audit banner markup on the create-role page...` (1)

This matches the documented pre-existing baseline (`LembagaCrudTest` x1,
`RoleBuilderTest` x4, `RoleFormAuditBannerTest` x1 = 6 total) exactly.
**No new regressions from any Sub-project 6c work.**

## Step 6: Commit

```bash
git add scripts/keuangan-6a-browser-check.mjs tests/Feature/Admin/YayasanSettingControllerTest.php
git commit -m "test(keuangan): add riwayat+kwitansi Playwright check, completing 6c verification

Also fix a real pre-existing bug found during full-suite verification: a
global function name collision between actingAsYayasanSuperAdmin() in
YayasanSettingControllerTest.php (Task 5) and the identically-named,
differently-behaved helper in KaryawanCrudTest.php, which fatally crashed
the full php artisan test run. Renamed the Task 5 helper to
actingAsYayasanSettingSuperAdmin()."
```

Commit hash: `3a8cf24e9ba0dad2102169193958fabb19a61053` (`3a8cf24`)
Parent: `2de56a2` (test(keuangan): add two-party cross-parent authorization
suite for riwayat/kwitansi — Task 6)

Files changed: `scripts/keuangan-6a-browser-check.mjs`,
`tests/Feature/Admin/YayasanSettingControllerTest.php` (2 files, 26
insertions, 6 deletions).

## Process note for future subagents

The full `php artisan test` run in this repo takes ~750s (12.5 min), which
exceeds the Bash tool's 600s hard per-call cap. The harness auto-backgrounds
the command when this happens rather than the agent choosing to background
it. The correct recovery (confirmed working here) is: verify via
`tasklist //FI "IMAGENAME eq php.exe"` that the process is genuinely still
running (not a stray/dead one), then poll its output file synchronously
from a *new*, blocking foreground Bash call (a bounded loop checking for
the `Duration:` line, re-issued if needed) rather than using an
async/notify-based Monitor task — Monitor's completion notification does
not reliably arrive as "waiting" in this environment and reads as an
indefinite stall.
