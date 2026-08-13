# Handoff Log — Keuangan Sub-project 6c2: Bundling Top-Up Wallet & Verifikasi Admin Transfer Manual

**Status: SHIPPED.** All 9 plan tasks implemented, plus a whole-branch code review found 8 Important findings (0 Critical) that were fixed and re-reviewed clean, plus one small follow-up fix caught by that re-review. Branch `demo`, **not pushed to remote** per project convention.

> This log supersedes an earlier version written directly after implementation, before the whole-branch review ran. That earlier version overstated scope in two places, corrected here: (1) it claimed notifications were built in this sub-project — `TransferManualDisetujuiNotification`/`TransferManualDitolakNotification` already existed from a prior sub-project and were not touched; (2) it reported "236 passed, 627 assertions" as if it were a full-suite run — it was always a scoped run (`tests/Feature/Keuangan/` + 4 admin `ManualPayment*` test files), per an explicit user decision earlier in this session to skip full-suite runs for token cost, not an oversight.

- Spec: `.agents/specs/keuangan-06c2-topup-bundling-verifikasi-admin.md`
- Plan: `.agents/plans/keuangan-06c2-topup-bundling-verifikasi-admin.md`
- Base commit before implementation: `b4216f3` (plan commit)
- Final commit: `c093f0e`
- 12 commits total (9 task commits by the user's own direct implementation + 1 whole-branch-review fix wave + 1 follow-up fix from that fix wave's own re-review + the plan/spec docs commits already counted separately).

## What this sub-project built

The fourth sub-part of the Keuangan Sub-project 6 decomposition (6a/6b/6c already shipped), closing two open items deferred from 6b:

- **Bundled wallet top-up during VA/QRIS checkout.** A parent can pay tagihan and top up their wallet in one combined VA/QRIS payment. Architecturally this is a single `Pembayaran` record — `PembayaranTagihan` covers only the tagihan portion, `Pembayaran.amount` holds the combined total, and the gap is resolved at settlement time via a new shared method, `PaymentAllocationService::topupSisaJikaAda()`, called from three places: the BRI webhook, `ReconcilePayments`'s waiting-payment sweep, and its failed-topup retry (which this sub-project also fixed — it previously retried the *full* `Pembayaran.amount` instead of the remainder, which would have double-credited the wallet for bundled payments once they existed).
- **Admin "Verifikasi Transfer Manual" listing page** (`GET /admin/manual-payment`). The approve/reject *backend* (`ManualPaymentController::approve()`/`reject()`) already existed from an earlier sub-project and was explicitly not modified — this sub-project added only the `index()` listing method, its views (following the AJAX-fragment + KPI-card pattern from `admin/mata-pelajaran`), and the sidebar entry.

This sub-project was implemented directly by the user from the plan (not via subagent-driven-development's per-task review loop), then verified with a full whole-branch code review as the first quality gate — mirroring the rigor 6b's final review applied, since this touches the same money-critical surface (wallet crediting, payment reconciliation).

## The whole-branch review: what it found and fixed

**No Critical findings.** The reviewer independently traced the money paths (idempotency guard correctness, lock-release ordering before `Wallet::topup()`, the double-count fix, and — critically — confirmed the QRIS branch of `ReconcilePayments` got the identical treatment as VA, the exact kind of instruction that tends to get silently skipped) and re-ran the money-critical tests directly. All held up.

**8 Important findings, all fixed:**
1. `createVaPaymentWithTopup()`/`createQrisPaymentWithTopup()` were missing the plan-specified `PaymentException` guard for `$topupAmount <= 0 || $tagihans->isEmpty()`, and its 2 tests had been silently dropped during implementation. Restored both.
2. `topupSisaJikaAda()` caught `\Exception` instead of `\Throwable` (a `TypeError` would escape), and `ReconcilePayments::retryFailedTopups()` had lost its per-item try/catch — one bad record could abort the entire hourly retry sweep for every other pending record. Fixed both.
3. `topupSisaJikaAda()` used `$pembayaran->siswa->wallet` — unsafe because `Siswa` carries `TenantScope`, and this exact trap is already documented with an explanatory comment elsewhere in this codebase (`ManualPaymentController::approve()`). Switched to the safe `Wallet::where('siswa_id', ...)->first()` form.
4. Checkout re-submit with a *changed* `topup_amount` for the same tagihan silently redirected to the old (differently-priced) pending payment via the exact-tagihan-set idempotency guard, which had no awareness of top-up amount. Fixed by skipping that idempotency check entirely whenever `$topupAmount > 0` — a bundled request always creates a fresh payment rather than risk silently discarding the parent's stated intent.
5. A `$sisa <= 0` edge case (data-integrity anomaly — `amount` and the allocated sum disagree) silently returned with no log; and the `finance:reconcile-payments` schedule had no `->withoutOverlapping()` guard. Fixed both.
6. `KwitansiBundledTopupTest`'s only content check was a byte-length sanity assertion — it would stay green even if the "Top Up Saldo Wallet" line item were deleted from the template. Rewritten to render the Blade view directly and assert the real line item text and amount appear.

**5 Minor findings fixed**: a stray Playwright debug screenshot committed to repo root (deleted + gitignored), dead imports in `ManualPaymentController.php` and `ReconcilePayments.php`, and a missing third badge case ("Tagihan + Top Up") in the admin listing for a hypothetically-bundled manual-transfer row.

**2 Minor findings explicitly left as noted follow-ups, not fixed**: the admin listing's `_daftar.blade.php` doesn't render a "Diajukan Oleh" column despite `index()` eager-loading `requestedBy` for it (spec §4.4 called for one — a small product-decision gap, not a bug); and this handoff log itself (corrected above).

**Re-review of the fix wave found one real gap it introduced by omission, not regression**: fixing #4 above (bundled resubmit skips the old idempotency match) left its mirror case open — a *plain* (no-topup) resubmit could still match and redirect into an existing *bundled* pending payment for the same tagihan, silently charging the parent for someone else's top-up amount too. Closed directly with a one-line fix (`->where('topup_status', 'none')` added to `findPendingPembayaranFor()`'s candidate query, since that method is now only ever called from the `$topupAmount <= 0` branch) plus a regression test proving a plain resubmit creates its own distinct payment rather than redirecting into the bundled one.

## Process notes

- **The user implemented all 9 plan tasks directly**, without the per-task subagent-review loop used in 6a/6b/6c. The whole-branch review therefore served as the *only* quality gate this code received before shipping — and it found real, non-trivial issues (a dropped guard with undisclosed test deletions, an exception-narrowing bug, a tenant-scope trap, and a checkout idempotency gap), consistent with this project's established pattern that per-task review catches different things than a full-branch pass.
- **The reported test count in the original handoff log (236 passed) was accurate as a scoped-run number** — verified by independently re-running the same scope after the fix wave (240 passed after the review fixes, 241 after the follow-up fix) and confirming the counts are internally consistent with the diff's net new tests.
- **No full-suite (`php artisan test` with no path filter) was run anywhere in this sub-project**, per the user's explicit standing decision (token cost) made when this plan was written. This is a deliberate scope boundary, not a gap — but it does mean this sub-project's regression coverage against modules *outside* Keuangan/`ManualPayment*` (e.g. anything touching `routes/console.php`'s scheduler globally) was never re-verified against the full suite.

## Final scoped verification

Last isolated run (commit `c093f0e`): `tests/Feature/Keuangan/` + `tests/Feature/Admin/ManualPaymentControllerTest.php` + `tests/Feature/Admin/ManualPaymentNotificationTest.php` + `tests/Feature/Admin/ManualPaymentIndexControllerTest.php` + `tests/Feature/Admin/ManualPaymentIndexAuthorizationTest.php` — **241 passed** (plus the mirror-gap regression test added directly, bringing the file's local count to 6). No full-suite run performed (deliberate, see above).

## Explicitly out of scope for 6c2

- Any change to `PaymentService`'s pre-existing non-bundled methods (`createVaPayment`, `createQrisPayment`, `createWalletPayment`, `createManualPayment`, `createManualTopupPayment`, `createCashPayment`), `AutoAllocationEngine`, `Wallet::topup()`/`debit()`, or `ManualPaymentController::approve()`/`reject()` — confirmed untouched throughout this entire sub-project, including both review fix rounds.
- Preferences/pengaturan notifikasi (→ 6d).

## Open items carried forward (still unaddressed)

1. **`_daftar.blade.php`'s missing "Diajukan Oleh" column** (spec §4.4) despite the data being eager-loaded for it — small product-decision gap, flagged by the whole-branch review, not fixed.
2. Carried from 6c: the `'date'` Laravel validation rule on riwayat's date filters accepts relative strings like `?dari=now` (strtotime-based, not a strict format check) — still unaddressed, low-risk.
3. Carried from 6c: `CheckoutController::status()`'s polling endpoint uses full route-model binding rather than a minimal column-only lookup — still unaddressed, low-risk at current scale.
4. A hypothetically-bundled *manual-transfer* request (as opposed to VA/QRIS, which is where bundling actually lives today) is not currently reachable via any UI, but the admin listing's badge logic now has a case for it if that ever changes.

## Verification account

Manual/browser testing used the seeded demo account `ortu.demo@permatakraksaan.sch.id` / `password`, with a dummy tagihan created via tinker for the Playwright bundled-checkout check (Task 9).
