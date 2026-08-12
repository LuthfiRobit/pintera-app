# Handoff Log — Keuangan Sub-project 6b: Rekap Tagihan Aktif & Checkout Multi-Channel

**Status: SHIPPED.** All 8 plan tasks complete and verified. Branch `demo`, **not pushed to remote** per project convention.

- Spec: `.agents/specs/keuangan-06b-rekap-tagihan-checkout.md`
- Plan: `.agents/plans/keuangan-06b-rekap-tagihan-checkout.md`
- Base commit before Task 1: `862511b` (design spec commit; plan added at `29b764f`)
- Final commit: `e89ddca`
- 11 commits total (8 task commits + 2 in-task fix commits [VA idempotency guard, Task 7 vacuous-assertion strengthening] + the plan/spec docs commits already counted separately before Task 1).

## What this sub-project built

The second of 4 sub-plans decomposing Keuangan Sub-project 6 (Parent Dashboard & Kwitansi), following 6a's fondasi/dashboard. Wires Sub-project 04/05's payment backend to real UI for the first time, under the existing `/keuangan` portal (`web` guard, `orang_tua` role, `ResolveActiveSiswa` middleware from 6a):

- **`PaymentService::createWalletPayment()`** (Task 1): new service method, on-demand wallet-balance checkout for one or more tagihan, inside the same locked transaction as the balance check (via `Wallet::debitWithinTransaction`) to prevent double-spend from concurrent submissions. Throws `InsufficientBalanceException` on insufficient balance, rolling back atomically (no partial state).
- **Rekap Tagihan Aktif page** (Task 2): `GET /keuangan/tagihan` — lists a child's `belum_bayar`/`sebagian` tagihan ordered by `jatuh_tempo`, with an auto-debit-enabled banner (from `SystemSetting`) and a multi-select checkbox UI feeding "Bayar Terpilih" into checkout.
- **Checkout tab page** (Task 3): `GET /keuangan/checkout` — 4-channel tab UI (VA BRI, QRIS, Saldo Wallet, Transfer Manual) following the established Alpine tab pattern from `admin/guru/edit.blade.php`. Dashboard's disabled 6a placeholder CTAs ("+ Top Up", skip-alert top-up button) wired to real links.
- **VA & QRIS submit** (Task 4): `POST /keuangan/checkout/va` and `/qris`, a "menunggu pembayaran" waiting page with live countdown + AJAX status polling (`GET /keuangan/checkout/{pembayaran}/status`). Idempotency guard prevents creating a second VA while one is still pending for the same tagihan set (fixed mid-task to require an *exact* tagihan-set match, not just overlap — see below).
- **Wallet submit** (Task 5): `POST /keuangan/checkout/wallet` + success page, using Task 1's `createWalletPayment()`.
- **Transfer manual submit** (Task 6): `POST /keuangan/checkout/transfer` (multipart, proof-of-transfer upload, 2MB limit) + "menunggu verifikasi" pending page.
- **Cross-parent authorization suite** (Task 7): dedicated regression tests proving one parent cannot see, pay, or view another parent's child's tagihan/pembayaran across every new endpoint. Initial version had vacuous assertions (single-party fixtures that couldn't actually catch a leak); rewritten with genuine two-party fixtures in the same task.
- **Playwright verification + full-suite gate** (Task 8, this task): end-to-end browser check (tagihan list → select → checkout tabs → wallet payment → success page) plus scoped and full-suite regression confirmation.

## Task-by-task summary

| Task | What | Fix rounds |
|---|---|---|
| 1 | `PaymentService::createWalletPayment()` | clean |
| 2 | Rekap Tagihan Aktif page | clean |
| 3 | Checkout tab page (GET, channel selection) + dashboard CTA wiring | clean |
| 4 | VA & QRIS submit + waiting page | 1 round — VA idempotency guard changed from "any overlapping tagihan" to "exact tagihan-set match" (commit `b7bb9de`) |
| 5 | Wallet submit + success page | clean |
| 6 | Transfer manual submit + verification-pending page | clean |
| 7 | Cross-parent authorization regression suite | 1 round — initial assertions were vacuous (couldn't actually detect a cross-parent leak due to single-party fixtures); strengthened with genuine two-party fixtures (commit `5059e8f`) |
| 8 | Playwright verification + scoped regression + full-suite gate | clean, except the Playwright script itself needed one selector fix — see below |

No user-escalated design-decision contradictions arose in this sub-project (unlike 6a's `SkipAlertResolver` semantics question).

## Process notes

- **Task 8 Playwright selector bug caught live, not in the brief**: the brief's given `checkTagihanAndWalletCheckout()` code used `page.locator('button:has-text("Saldo Wallet")')` to find the wallet tab button. Against the real rendered checkout page this hit a Playwright strict-mode violation — it matched both the tab button *and* the wallet form's submit button (whose label "Bayar dari Saldo Wallet" also contains the substring "Saldo Wallet"). Fixed by switching to `page.getByRole('button', { name: 'Saldo Wallet', exact: true })`, which scopes to the tab by exact accessible name. This is the only deviation from the brief's literal given code across the whole task.
- **Global constraints held throughout**: `PaymentService`'s pre-existing methods (`createVaPayment`, `createQrisPayment`, `createCashPayment`, etc.), `AutoAllocationEngine`, and `Wallet::topup()`/`debit()` were never modified — confirmed by final diff review. Only `Wallet::debitWithinTransaction()` (pre-existing, not touched) was reused by the new `createWalletPayment()`.
- **IDOR discipline held**: every controller action loading a `Pembayaran` or `Tagihan` by route/query id verifies ownership against `Auth::user()->orangTua`'s children — the project's most-recurring bug class (10+ prior recurrences across Sub-projects up through Presensi/Asesmen). Task 7's dedicated suite exists specifically to regression-guard this across all 4 channels + the status/show/waiting/success/verification-pending pages.
- **Full-suite isolation discipline**: per 6a's lesson (concurrent `php artisan test` runs racing on the shared MySQL test DB caused false failures in that sub-project), Task 8 explicitly confirmed no other test process was running (checked via `Get-CimInstance Win32_Process` — found only two `php.exe` processes, both identified as the dev server, not test runners) before the isolated full-suite gate run.

## Final full-suite verification

Last isolated run (commit `e89ddca`): `tests/Feature/Keuangan/` — **180 passed** (473 assertions, up from 6a's 150 — +30 net new Keuangan tests across this sub-project). Project-wide `php artisan test` — **6 failed / 1559 passed** (4834 assertions), confirmed to be the exact same pre-existing baseline established in 6a's handoff log (`LembagaCrudTest` x1, `RoleBuilderTest` x4, `RoleFormAuditBannerTest` x1), zero new regressions from this sub-project.

## Explicitly out of scope for 6b (deferred to 6c/6d)

- Riwayat transaksi (transaction history) list and kwitansi PDF generation/download.
- Admin logo upload for kwitansi branding.
- Notification preference toggles.
- Cicilan (installment plan) UI/logic — deliberately excluded per this plan's global constraints even though `Tagihan::cicilan()`/`SkemaCicilan` exist in the data model.
- BRI webhook-driven status transitions for VA/QRIS (the waiting page polls `status` but the actual payment-confirmation webhook path is Sub-project 04/05 territory, unmodified here).
- Any change to `PaymentService`'s pre-existing methods, `AutoAllocationEngine`, or `Wallet`'s `topup()`/`debit()` — confirmed untouched throughout this entire sub-project.

## Open items carried forward from 6a (still unaddressed — re-surfacing per 6a's handoff log)

1. **"Notifikasi Terbaru" panel is still not filtered to the active child.** Still requires `siswa_id` on Finance notification payloads (`TagihanDiterbitkanNotification`, `PembayaranBerhasilNotification`, `SaldoTidakCukupNotification`, etc.) — not touched by 6b since it didn't add new notification types. Still recommended for whichever of 6c/6d picks up notification work.
2. **No mark-as-read mechanism exists anywhere.** Still explicitly 6d's territory (notification preferences).
3. **Topbar per-request cost** (notifications query, `orangTua` lookup, `$childOptions` query on every authenticated page load) — not worsened by 6b (no new topbar queries added), but still not optimized.
4. **`NotificationFeedResolver` still lives in `App\Services\Finance\`** despite being invoked from the shared topbar for every role. Still recommend `App\Services\Notifications\` before 6d builds preference logic on top of it.
5. Carried from Sub-project 05: `PaymentAllocationService::allocate()`'s `paid_amount +=` double-counting on re-call risk, and partial-allocation payments triggering no notification — both still unaddressed, still out of scope for 6b's checkout flow (checkout always fully allocates in one call per channel; the double-counting risk is specifically about re-invocation, which 6b's controllers don't do).
6. `Admin\DashboardController::lembagaViewData()` `TypeError` for a scope-less `User` on `GET /dashboard` — pre-existing, unrelated, not touched.

## New open items surfaced by 6b (for 6c/6d awareness)

- Manual-transfer proof files are stored but there is no admin verification UI shipped yet in this sub-project — the "menunggu verifikasi" page tells the parent to wait, but the admin-side approve/reject flow for transfer proofs is not part of 6b's scope (likely 6c, alongside riwayat/kwitansi, or a dedicated admin-verification sub-project).
- The checkout page's "Sekalian Top Up Wallet (opsional)" input (`topupAmount`, wired via Alpine `x-model` into VA/QRIS hidden fields) is present in the UI but its backend handling in `PaymentService::createVaPayment`/`createQrisPayment` was not re-verified as part of 6b's task list — it reuses Sub-project 04/05's existing method signatures unmodified, so this is presumed already-working prior behavior, not a 6b gap, but worth a note if a future top-up-specific bug surfaces here.

## Verification account

Browser/manual testing throughout this sub-project used the seeded demo account `ortu.demo@permatakraksaan.sch.id` / `password` and student "Eliana Putri" (from Sub-project 6a's manual verification setup, refreshed for 6b via Task 8's tinker fixture: wallet balance 500000, one `belum_bayar` tagihan of 50000).
