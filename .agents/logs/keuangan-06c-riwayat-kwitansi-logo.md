# Handoff Log — Keuangan Sub-project 6c: Riwayat Transaksi & Kwitansi PDF + Pengaturan Yayasan

**Status: SHIPPED.** All 7 plan tasks complete, task-reviewed, and a final whole-branch review's findings fixed across two fix rounds (the first round's own fix introduced a new bug, caught by re-review — see below). Branch `demo`, **not pushed to remote** per project convention.

- Spec: `.agents/specs/keuangan-06c-riwayat-kwitansi-logo.md`
- Plan: `.agents/plans/keuangan-06c-riwayat-kwitansi-logo.md`
- Base commit before Task 1: `72aac78`
- Final commit: `7f11048` (plus a docs-only commit `e4e9cf9` appending fix-round-2 notes to the scratch report)
- 10 commits total (7 task commits + 1 final-review fix wave + 1 second fix round that corrected the first wave's own regression + the plan/spec docs commits already counted separately before Task 1).

## What this sub-project built

The third of 4 sub-plans decomposing Keuangan Sub-project 6 (Parent Dashboard & Kwitansi), following 6a (fondasi/dashboard) and 6b (checkout multi-channel). Purely presentational/reporting — **no changes to `PaymentService`, `PaymentAllocationService`, `AutoAllocationEngine`, or `Wallet` anywhere in this sub-project, including both fix rounds**:

- **Riwayat Transaksi page** (`GET /keuangan/riwayat`) — lists all `Pembayaran` for the active child (every status, not just `lunas`), with date-range and metode filters (now validated as real dates after fix round 2), a per-row status badge, and a "Total" amount column (added in the final-review fix wave after the plan/spec silently omitted it despite the spec requiring it) that correctly falls back to `Pembayaran.amount` for still-pending transactions (fixed in fix round 2 after the first wave's fallback-less version showed "Rp0" for every pending row).
- **Kwitansi PDF download** (`GET /keuangan/riwayat/{pembayaran}/kwitansi`) — on-demand `Pdf::loadView(...)->stream()`, never persisted to disk, gated by `authorizePembayaran()` before status is even checked (so a cross-parent request gets a uniform 403 regardless of whether the target payment exists or is settled — no status-oracle leak).
- **`AuthorizesPembayaran` trait** — extracted from `CheckoutController` (6b) into `App\Http\Controllers\Keuangan\Concerns\AuthorizesPembayaran` so `RiwayatController` reuses the identical cross-tenant ownership check rather than reimplementing it. Verified as a byte-for-byte pure refactor with zero behavior change to `CheckoutController`.
- **Admin Pengaturan Yayasan** (`GET/PUT /admin/pengaturan-yayasan`) — brand new admin page (there was no Yayasan-settings UI at all before this sub-project), gated by a new `yayasan.kelola` permission, managing every field on the `yayasan` table (not just the logo, per an explicit mid-brainstorming scope expansion from the user) with a view/edit-mode-toggle UI mirroring `admin/lembaga/edit.blade.php`'s established pattern. Logo upload validates `mimes:jpg,jpeg,png,svg|max:1024` and deletes the old file only after a successful DB update (fixed in the final-review fix wave — originally deleted before, risking an orphaned-reference state on update failure).

## Task-by-task summary

| Task | What | Fix rounds |
|---|---|---|
| 1 | `yayasan.kelola` permission | clean |
| 2 | Extract `AuthorizesPembayaran` trait from `CheckoutController` | clean — pure refactor, 23/23 pre-existing `CheckoutController` tests confirmed unchanged behavior |
| 3 | Riwayat Transaksi page | clean — one deliberate, plan-anticipated `url()`-not-`route()` workaround for the not-yet-existing kwitansi route name, resolved cleanly in Task 4 |
| 4 | Kwitansi PDF download | clean — implementer caught and fixed a real bug in the plan's own given code (`siswa` relation needed a `TenantScope` bypass too, not just `jenisTagihan`), independently verified by the task reviewer |
| 5 | Admin Pengaturan Yayasan page | clean — 2 of 3 claimed deviations from the brief were real/necessary (verified against actual codebase files: `AuthorizesRequests` trait genuinely needed, `apartment` vs invalid `account_balance` icon name), 1 was an unnecessary but harmless misunderstanding (an explicit `Storage` facade import that wasn't actually required) |
| 6 | Cross-parent authorization suite | clean, no fix round — explicitly applied the lesson from 6b's equivalent task (where 2 of 6 original assertions were vacuous); this task's reviewer independently traced all 3 assertions against real controller/trait/view code and confirmed each would genuinely fail if the real authorization check were removed |
| 7 | Playwright verification + scoped regression + full-suite gate | clean after 1 background-wait stall (see Process Notes) — also found and fixed a real full-suite-only PHP fatal (global function name collision) |
| Final review | whole-branch review (opus) | 0 Critical + 5 Important + 6 Minor findings → fix wave 1 → **re-review found fix wave 1 itself introduced a new bug** → fix round 2 → re-reviewed clean |

## The final whole-branch review, and its two-round fix

Read this section in full before scoping 6c2 or 6d — it's the most consequential part of this sub-project's process.

**No Critical findings** — the reviewer independently traced authorization ordering (`authorizePembayaran()` always runs before the `status === 'lunas'` check, so a cross-parent request can't distinguish "doesn't exist" from "exists but pending" via 403-vs-404), `TenantScope` usage, and `Pembayaran.status`/`Pembayaran.metode` enum coverage against the actual database migrations (not the plan's text) — all sound.

**5 Important findings, all eventually fixed (across two rounds):**
1. `RiwayatController::kwitansi()`'s eager-load had a `TenantScope` bypass on `siswa` and `pembayaranTagihan.tagihan.jenisTagihan`, but not on `siswa.kelas` — since `Kelas` also carries `TenantScope`, the "Kelas" field silently rendered blank on every kwitansi PDF for an `orang_tua` acting user. Fixed in fix wave 1; **fix wave 1's own regression test for this didn't actually work** (see below) — properly guarded in fix round 2.
2. The riwayat list silently omitted the spec-required "Total" column entirely (a plan defect — the plan's own given Blade code omitted it too, so this traces back to the plan, not the Task 3 implementer). Added in fix wave 1 — **but with a bug**: it only summed settled `pembayaranTagihan` allocations, showing "Rp0" for any still-pending transaction. Fixed properly in fix round 2 with a fallback to `Pembayaran.amount`.
3. `YayasanSettingController::update()` had no null-guard for a missing `Yayasan` row (unlike `edit()`, which handles it) — would 500 on a fresh install. Fixed in fix wave 1, held up under re-review.
4. `KwitansiControllerTest.php`'s 4 original tests asserted zero PDF content (only status codes/headers) — the logo-present rendering path had literally never been exercised by any test. Fix wave 1 added two tests, but they called `view('pdf.kwitansi', [...])->render()` directly, bypassing `actingAs()` and the real controller entirely — since `TenantScope::apply()` short-circuits on no authenticated user, this meant the tests exercised nothing about the `TenantScope` bypasses they were meant to protect. Fix round 2 rewrote both tests to go through the real authenticated HTTP route and decompress the PDF's FlateDecode content streams (`gzuncompress()`) to assert the actual rendered kelas name text — proven via a manual red/green check (temporarily reverting the `siswa.kelas` bypass, confirming the rewritten test failed, restoring it, confirming green).
5. Date-range filtering required both `dari` AND `sampai` to apply any filter at all (a `dari`-only input was silently ignored), and the empty-state message misleadingly implied a filter had matched nothing even when no filter was actually active. Fix wave 1 made the two bounds independently applicable — correct — but left `dari`/`sampai` unvalidated as date strings, so a garbage value would silently coerce through MySQL instead of being rejected. Fix round 2 added `$request->validate(['dari' => ['nullable','date'], 'sampai' => ['nullable','date'], ...])`.

**6 Minor findings**: 3 fixed in fix wave 1 (old-logo-delete-after-not-before-update, null-safe relation chains in two Blade files, `isNotEmpty()` instead of `?:` for the kwitansi PDF's zero-allocation edge case), 3 explicitly accepted/deferred with stated reasons (svg logo upload is spec-mandated and low-risk given the single highest-privilege role required; no composite DB index needed at current data volumes, matching the plan's own "no migration" decision; a cosmetic metode-icon omission that matches what the plan itself specified).

**Why this needed two fix rounds, not one**: the final reviewer explicitly warned the fix-wave dispatch to mirror the exact fallback pattern already present in a sibling template — the dispatch did name the correct pattern, but the implementer applied it to the kwitansi PDF template (which already needed no change, since the reviewer's example WAS the correct existing pattern there) without also carrying that same fallback into the brand-new riwayat list column being added in the same commit. This is a lesson for future fix-wave dispatches: **when a fix wave adds a NEW code path that parallels an EXISTING one being referenced as the pattern to follow, explicitly double-check the new path was built to the same standard, not just that the existing one is correct.** Re-review caught both this and the vacuous-test problem in one pass; a second, smaller fix round resolved both cleanly with no further issues.

## Process notes

- **Task 7's implementer stalled once** on the exact same background-wait failure mode documented in 6a/6b's handoff logs: it launched the full `php artisan test` run in the background and then reported "waiting for the monitor," with no mechanism to actually be notified. Resumed via `SendMessage` with explicit foreground-only instructions; completed cleanly on the resume, including finishing the verification and committing.
- **Task 7 also found a second real bug**, unrelated to the sub-project's own feature work: a global (non-namespaced) PHP function name collision between `actingAsYayasanSuperAdmin()` (added by Task 5) and a pre-existing, unrelated same-named function in `KaryawanCrudTest.php`. Each file passed individually/in isolation (which is why per-task test runs never caught it), but the collision fatally crashed any FULL-suite run once both files loaded in the same PHP process. Fixed by renaming only the Task-5-added helper; the task reviewer independently confirmed this is standard PHP behavior (not Pest-specific) and that the fix correctly left the pre-existing file untouched.
- **IDOR discipline held, independently re-verified twice**: once by Task 6's reviewer (tracing all 3 cross-parent tests against the real controller/trait/view code and confirming none were vacuous — directly applying the lesson from 6b's equivalent task, where 2 of 6 original assertions there had to be rewritten), and again by the final whole-branch reviewer (tracing `authorizePembayaran()`'s call ordering and `ResolveActiveSiswa`'s scoping chain from first principles).
- **A genuinely mid-brainstorming scope change**: the user explicitly expanded the admin Yayasan page's scope from "just the logo" (the original spec draft) to "every field on the `yayasan` table, matching the Lembaga edit page's UI pattern" — this was captured in the spec before planning began, not bolted on after, so no plan/implementation mismatch resulted from it.

## Final full-suite verification

Last isolated run, after both fix rounds (commit `7f11048`): project-wide `php artisan test` — **6 failed / 1586 passed** (4894 assertions), confirmed to be the exact same pre-existing baseline established in 6a/6b's handoff logs (`LembagaCrudTest` ×1, `RoleBuilderTest` ×4, `RoleFormAuditBannerTest` ×1), zero new regressions from this sub-project.

## Explicitly out of scope for 6c (deferred)

- **Bundled wallet top-up during VA/QRIS checkout** and **admin approve/reject UI for manual-transfer proofs** — both carried forward from 6b's open items, still deferred to a dedicated Sub-project 6c2 (scheduled before 6d per an explicit user decision when 6c's scope was being decided).
- Notification preference toggles (→ 6d).
- Tanda tangan digital asli (real digital signature) on the kwitansi footer — a static placeholder text is used instead.
- Multi-yayasan support — the admin settings page assumes exactly one `Yayasan` record per installation (`Yayasan::first()`), matching the rest of this codebase's existing single-yayasan assumption.

## Open items carried forward from 6a/6b (still unaddressed — re-surfacing per prior handoff logs)

1. "Notifikasi Terbaru" panel still not filtered to the active child (needs `siswa_id` on Finance notification payloads) — still 6d/later territory.
2. No mark-as-read mechanism for notifications anywhere — still 6d's territory.
3. `NotificationFeedResolver` still lives in `App\Services\Finance\` despite being cross-module.
4. `PaymentAllocationService::allocate()`'s `paid_amount +=` re-call double-counting risk — still unaddressed, not touched by any reporting-only sub-project like this one.
5. `CheckoutController::status()`'s polling endpoint still uses full route-model binding rather than a minimal column-only lookup — not fixed in 6b's fix wave, not touched here either (out of this sub-project's scope).

## New open items surfaced by 6c (for 6c2/6d awareness)

- **The `'date'` Laravel validation rule accepts relative date strings** (e.g. `?dari=now`, `?dari=+1 week`) since it's `strtotime()`-based, not a strict format check — the final reviewer's re-review flagged this as a residual minor gap in fix round 2's date validation. Not fixed (deemed low-risk: worst case is a wider-than-intended but still date-shaped query bound, not an injection or crash). A `date_format:Y-m-d` rule would close this fully if it's ever revisited.
- The kwitansi logo-present test (after fix round 2's rewrite) has slightly less content-assertion coverage than fix wave 1's version had, since the rewritten test can no longer inspect view-render output directly and instead relies on decompressed-PDF-stream inspection, which the reviewer confirmed is sound but noted as a minor coverage trade-off worth remembering if this area is touched again.
- `RiwayatController::index()`'s pagination links (`->appends($request->query())`) still use raw request query params rather than the validated `$validated` array — harmless today since validation gates entry before this line runs, but worth tidying for consistency if this controller is touched again.

## Verification account

Browser/manual testing throughout this sub-project used the seeded demo account `ortu.demo@permatakraksaan.sch.id` / `password` and student "Eliana Putri," with a `lunas` `Pembayaran` fixture created via tinker for Task 7's Playwright riwayat/kwitansi check.
