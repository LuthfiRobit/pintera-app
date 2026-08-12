# Handoff Log — Keuangan Sub-project 6b: Rekap Tagihan Aktif & Checkout Multi-Channel

**Status: SHIPPED.** All 8 plan tasks complete, task-reviewed, and a final whole-branch review's findings (1 Critical + 6 Important + 8 Minor) fixed and re-verified. Branch `demo`, **not pushed to remote** per project convention.

> This log supersedes an earlier, premature version written by Task 8's implementer (commit `3e71d3d`) before the final whole-branch review ran. That earlier version's "presumed already-working" note about `topup_amount` was incorrect — see the Critical/Important findings below.

- Spec: `.agents/specs/keuangan-06b-rekap-tagihan-checkout.md`
- Plan: `.agents/plans/keuangan-06b-rekap-tagihan-checkout.md`
- Base commit before Task 1: `29b764f`
- Final commit: `69818ba`
- 13 commits total (8 task commits + 2 in-task fix commits [VA idempotency exact-match, Task 7 vacuous-assertion strengthening] + 1 premature docs commit + 1 final-review fix-wave commit + the plan/spec docs commits already counted separately before Task 1).

## What this sub-project built

The second of 4 sub-plans decomposing Keuangan Sub-project 6 (Parent Dashboard & Kwitansi), following 6a's fondasi/dashboard. Wires Sub-project 04/05's payment backend to real UI for the first time, under the existing `/keuangan` portal (`web` guard, `orang_tua` role, `ResolveActiveSiswa` middleware from 6a):

- **`PaymentService::createWalletPayment()`** (Task 1, hardened by the final review): on-demand wallet-balance checkout for one or more tagihan. Locks the wallet row, then **re-fetches the tagihan set fresh from the database inside the transaction** (this re-fetch was added by the final-review fix wave — see Critical finding below), throws `InsufficientBalanceException`/`PaymentException` on insufficient balance or a stale/changed tagihan set, rolling back atomically (no partial state).
- **Rekap Tagihan Aktif page** (Task 2): `GET /keuangan/tagihan` — lists a child's `belum_bayar`/`sebagian` tagihan ordered by `jatuh_tempo`, with an auto-debit-enabled banner (from `SystemSetting`) and a multi-select checkbox UI feeding "Bayar Terpilih" into checkout.
- **Checkout tab page** (Task 3): `GET /keuangan/checkout` — 4-channel tab UI (VA BRI, QRIS, Saldo Wallet, Transfer Manual) following the established Alpine tab pattern from `admin/guru/edit.blade.php`.
- **VA & QRIS submit** (Task 4, hardened by the final review): `POST /keuangan/checkout/va` and `/qris`, a "menunggu pembayaran" waiting page with live countdown + AJAX status polling. Idempotency guard (now generalized to `findPendingPembayaranFor(string $metode, ...)`, covers **both** VA and QRIS after the final review — originally only VA had it) requires an *exact* tagihan-set match, not just overlap.
- **Wallet submit** (Task 5): `POST /keuangan/checkout/wallet` + success page, using Task 1's `createWalletPayment()`.
- **Transfer manual submit** (Task 6): `POST /keuangan/checkout/transfer` (multipart, proof-of-transfer upload, 2MB limit) + "menunggu verifikasi" pending page.
- **Cross-parent authorization suite** (Task 7): dedicated regression tests proving one parent cannot see, pay, or view another parent's child's tagihan/pembayaran across every endpoint. Initial version had vacuous assertions (single-party fixtures, and one assertion checking a field a real leak would never populate); rewritten with genuine two-party fixtures and proven discriminating via red/green in the same task.
- **Playwright verification + full-suite gate** (Task 8): end-to-end browser check (tagihan list → select → checkout tabs → wallet payment → success page) plus scoped and full-suite regression confirmation.

## Task-by-task summary

| Task | What | Fix rounds |
|---|---|---|
| 1 | `PaymentService::createWalletPayment()` | clean at task-review time; hardened later by the final whole-branch review (see below) |
| 2 | Rekap Tagihan Aktif page | clean |
| 3 | Checkout tab page (GET, channel selection) + dashboard CTA wiring | clean |
| 4 | VA & QRIS submit + waiting page | 1 round — VA idempotency guard changed from "any overlapping tagihan" to "exact tagihan-set match" (commit `b7bb9de`) |
| 5 | Wallet submit + success page | clean |
| 6 | Transfer manual submit + verification-pending page | clean |
| 7 | Cross-parent authorization regression suite | 1 round — initial assertions were vacuous; strengthened with genuine two-party fixtures + red/green proof (commit `5059e8f`) |
| 8 | Playwright verification + scoped regression + full-suite gate | clean, one Playwright selector fix (strict-mode collision, see below) |
| Final review | whole-branch review (opus) | 1 Critical + 6 Important + 8 Minor findings, all fixed in one consolidated commit (`69818ba`), re-reviewed clean |

## The final whole-branch review: what it found and fixed

This is the most consequential part of this sub-project's process — read it in full before touching payment code in 6c.

**Critical — wallet checkout could double-charge under concurrent double-submission.** `PaymentService::createWalletPayment()` originally received its `$tagihans` Collection from the caller, loaded *before* the method's own `DB::transaction()`/wallet-`lockForUpdate()` began. The wallet-row lock correctly serialized two concurrent requests, but the loser still operated on its own **stale, pre-payment** copy of the tagihan data (Collections don't auto-refresh). Concretely: tagihan `net_amount=100000, paid_amount=0`; two concurrent wallet-checkout submissions (e.g. a parent double-tapping "Bayar dari Saldo Wallet"); request #1 commits (tagihan → `lunas`, wallet debited 100000); request #2's lock unblocks, still sees `paid_amount=0` in its stale Collection, debits *again*, and `PaymentAllocationService::allocate()` pushes `paid_amount` to 200000 on a 100000 bill — double-charged, no refund path. **Fixed**: the method now re-fetches the tagihan set fresh from the database, filtered to still-payable statuses, *after* acquiring the wallet lock, and throws `PaymentException` if the re-fetched set's count/IDs don't match what was requested. A new test proves this by reusing an already-consumed stale Collection across two calls, confirming the second throws and the wallet is not double-debited. Independently re-verified by the final reviewer: lock-then-refetch ordering is correct and load-bearing.

**Important findings, all fixed:**
- Partial-invalid tagihan selections (one of several selected tagihan settles via another channel between page-load and submit) were silently charged at a different total than the parent reviewed — all four checkout POST actions now compare resolved-tagihan-count against unique-requested-ID-count and redirect to the tagihan list with an explicit error on mismatch.
- **`topup_amount` was a dead field** — submitted by 3 Blade views, read by zero controller code. The plan never assigned backend wiring for it to any task despite the spec requiring it. Rather than rush a money-creating feature into a fix round, the "Sekalian Top Up Wallet" input was **removed** from the UI entirely; bundled top-up is deferred to 6c (see Explicitly Out of Scope below — this corrects the earlier, incorrect handoff-log claim that it was "presumed already-working prior behavior").
- Dashboard's top-up CTAs and the waiting page's "Buat Ulang" button all linked to the checkout page with no tagihan selected (a dead end) — repointed to the tagihan list.
- QRIS had no idempotency guard (only VA did) — generalized into `findPendingPembayaranFor(string $metode, ...)`, applied to both channels.
- A parent with zero linked children (`activeSiswa === null`) crashed every `CheckoutController` action with an uncaught `TypeError` — `TagihanController` already handled this correctly; `CheckoutController` now mirrors that pattern.
- The waiting page's 5-second status-polling `setInterval` never stopped, even after the payment settled or the countdown expired — now cleared on either terminal state.

**Minor findings**, all fixed or explicitly one-lined: orphaned upload file on service failure (moved inside try), one leftover wrong-field test assertion, 4 tab partials switched from hardcoded `url()` back to `route()` now that the named routes exist, a null-unsafe Blade dereference, `show()` now 404s for a non-VA/QRIS `Pembayaran`, an unused controller parameter removed, a vacuous test assertion removed.

Two findings were explicitly identified by the reviewer as **plan defects, not implementer errors**: the spec's mandated wallet-concurrency test and QRIS idempotency were never assigned to any task, and `topup_amount`'s backend was never assigned despite Task 3 shipping its UI. The implementers followed their briefs faithfully — this is a gap in how the spec was decomposed into tasks, worth remembering when writing 6c's plan.

## Process notes

- **Task 8 Playwright selector bug caught live, not in the brief**: the brief's given locator (`button:has-text("Saldo Wallet")`) hit a Playwright strict-mode violation — it matched both the wallet tab button and the wallet form's submit button ("Bayar dari Saldo Wallet" also contains that substring). Fixed with `page.getByRole('button', { name: 'Saldo Wallet', exact: true })`.
- **Global constraints held throughout, including the fix wave**: `PaymentService`'s pre-existing methods, `AutoAllocationEngine`, `Wallet::topup()`/`debit()`, and the BRI webhook controller were never modified — confirmed by the final reviewer's diff inspection. Only `Wallet::debitWithinTransaction()` (pre-existing) is reused by `createWalletPayment()`.
- **IDOR discipline held, and was independently proven, not just claimed**: the final reviewer traced `resolveSelectedTagihan()` and `authorizePembayaran()` from first principles across every endpoint and could not construct a cross-family money-movement path. Task 7's two-party regression suite (7 tests after the fix round) guards this going forward — the project's most-recurring bug class (10+ prior recurrences).
- **Full-suite isolation discipline maintained**: every full-suite check in this sub-project (Task 8, and the final post-fix-wave gate) confirmed no concurrent test process before running, per 6a's lesson about false failures from racing `php artisan test` processes on the shared MySQL test DB.
- A full-suite run *during* the final-review fix-wave verification hit one flaky, unrelated failure (`PembayaranWalletColumnsTest`, a Faker `lembaga_slug` unique-constraint collision) — confirmed to pass cleanly in isolation, not a regression from this sub-project. This is the same known recurring factory-unique-constraint flake class documented elsewhere in this project's memory.

## Final full-suite verification

Last isolated run, after the final-review fix wave (commit `69818ba`): `tests/Feature/Keuangan/` — **182 passed** (479 assertions). Project-wide `php artisan test` — **6 failed / 1561 passed** (4840 assertions), confirmed to be the exact same pre-existing baseline established in 6a's handoff log (`LembagaCrudTest` ×1, `RoleBuilderTest` ×4, `RoleFormAuditBannerTest` ×1), zero new regressions from this sub-project.

## Explicitly out of scope for 6b (deferred to 6c/6d)

- **Bundled wallet top-up during VA/QRIS checkout** (the "Sekalian Top Up Wallet" feature) — the UI field was built then removed in the final-review fix wave once it was found to have no backend. Whoever picks this up for 6c needs a real design: a second `Pembayaran` via `createManualTopupPayment()`-style flow, a second gateway call for VA/QRIS, and a way to show both the tagihan payment and the top-up in one waiting-page flow.
- Riwayat transaksi (transaction history) list and kwitansi PDF generation/download.
- Admin logo upload for kwitansi branding.
- Admin-side approve/reject UI for manual-transfer proofs — the "menunggu verifikasi" page tells the parent to wait, but there is no admin verification flow shipped yet.
- Notification preference toggles.
- Cicilan (installment plan) UI/logic — deliberately excluded per this plan's global constraints even though `Tagihan::cicilan()`/`SkemaCicilan` exist in the data model.
- BRI webhook-driven status transitions for VA/QRIS (the waiting page polls `status`, but the actual payment-confirmation webhook path is Sub-project 04/05 territory, unmodified here).
- Any change to `PaymentService`'s pre-existing methods, `AutoAllocationEngine`, or `Wallet`'s `topup()`/`debit()` — confirmed untouched throughout this entire sub-project, including the fix wave.

## Open items carried forward from 6a (still unaddressed — re-surfacing per 6a's handoff log)

1. **"Notifikasi Terbaru" panel is still not filtered to the active child.** Still requires `siswa_id` on Finance notification payloads — not touched by 6b since it didn't add new notification types.
2. **No mark-as-read mechanism exists anywhere.** Still explicitly 6d's territory.
3. **Topbar per-request cost** — not worsened by 6b, still not optimized.
4. **`NotificationFeedResolver` still lives in `App\Services\Finance\`** despite being invoked from the shared topbar for every role.
5. Carried from Sub-project 05: `PaymentAllocationService::allocate()`'s `paid_amount +=` double-counting risk on re-call, and partial-allocation payments triggering no notification — both still unaddressed. Note: 6b's own concurrency fix (re-fetching tagihan before `allocate()` is reached) reduces but does not eliminate this class of risk elsewhere in the codebase — `allocate()` itself still has no re-call guard.
6. `Admin\DashboardController::lembagaViewData()` `TypeError` for a scope-less `User` on `GET /dashboard` — pre-existing, unrelated, not touched.

## New open items surfaced by 6b (for 6c/6d awareness)

- **Bundled top-up needs a real design** (see "Explicitly out of scope" above) — this was the single largest scope gap found in this sub-project, caught only at final review.
- Manual-transfer proof files: a service-level failure after the file is already stored still orphans the file on disk (the fix wave moved `store()` inside the `try` block, closing the "orphan on early validation failure" case, but a thrown `PaymentException` from `createManualPayment()` itself still leaves the file — `Storage::disk('public')->delete($path)` in a `catch` block would close this fully; flagged, not fixed, low severity).
- `CheckoutController::status()`'s polling endpoint uses full route-model binding rather than a minimal `select(['id','siswa_id','status'])` lookup — the spec asked for the lighter form; not fixed in this fix wave (deemed acceptable risk at current scale), worth revisiting if `/keuangan` traffic grows meaningfully past the ~700-parent design target.
- The waiting page's polling only stops on `lunas` or countdown-expiry — not on other terminal statuses like `gagal`/`dibatalkan` if those are ever produced by this flow. Not currently reachable from any code path in 6b, but worth a note for whoever adds cancellation/failure handling later.

## Verification account

Browser/manual testing throughout this sub-project used the seeded demo account `ortu.demo@permatakraksaan.sch.id` / `password` and student "Eliana Putri" (from Sub-project 6a's manual verification setup, refreshed for 6b via Task 8's tinker fixture: wallet balance 500000, one `belum_bayar` tagihan of 50000).
