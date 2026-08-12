# Handoff Log — Keuangan Sub-project 6a: Fondasi Portal Orang Tua & Dashboard

**Status: SHIPPED.** All 8 plan tasks complete, task-reviewed, and a final whole-branch review's findings fixed and re-verified. Branch `demo`, **not pushed to remote** per instructions.

- Spec: `.agents/specs/keuangan-06a-fondasi-dashboard.md`
- Plan: `.agents/plans/keuangan-06a-fondasi-dashboard.md`
- Base commit before Task 1: `346212a`
- Final commit: `12fb095`
- 16 commits total (8 task commits + 3 fix-round commits + 1 controller-authored test-fixture fix + 1 stray docs commit + the final-review fix + its follow-up test).

## What this sub-project built

The first of 4 sub-plans decomposing Keuangan Sub-project 6 (Parent Dashboard & Kwitansi). Gives `orang_tua`-role users (parents) a working `/keuangan` dashboard:

- **Auth/permission**: one new permission, `keuangan.akses`, granted to the `orang_tua` role. Reuses the existing `web` guard + `orang_tua` Spatie role (already used by the Kasus/Pendampingan module) — **not** the `Portal`/`AkunPendaftar` system the original Sub-project 6 spec wrongly assumed (see the spec's "Temuan Arsitektural Penting" section for the full correction).
- **Child switcher**: new `ResolveActiveSiswa` middleware (modeled on `ResolveTenant`) resolves an `active_siswa_id` session key via `?switch_siswa=` query param, with a session → kontak-utama → first-child fallback chain. A topbar dropdown lets a parent with 2+ children switch between them.
- **Dashboard** (`GET /keuangan`, `keuangan.dashboard`): wallet balance + VA number card, a live "skip-alert" banner (genuine `AutoAllocationEngine` parity — see below), and a "Notifikasi Terbaru" panel.
- **Notification bell** (`resources/views/layouts/topbar.blade.php`): upgraded from a static "Belum ada notifikasi" placeholder to a real, functional feed — **for every role, not just Keuangan** — via a new `NotificationFeedResolver` service that merges `User`- and `OrangTua`-targeted database notifications, capped at 10.
- **Empty state**: a dedicated `keuangan.tanpa-anak` page for an `OrangTua` with zero linked children (not an error — handled distinctly from the 403 that fires when the `orang_tua`-role `User` has no `OrangTua` profile at all, a data-integrity case).

## Task-by-task summary

| Task | What | Fix rounds |
|---|---|---|
| 1 | `keuangan.akses` permission + role grant | clean |
| 2 | `NotificationFeedResolver` service | 1 round — dropped `->latest()` on both per-source queries (unordered `LIMIT` on a UUID-keyed table could silently exclude recent notifications) |
| 3 | Functional topbar bell (all roles) + Playwright script created | clean |
| 4 | `ResolveActiveSiswa` middleware | clean (implementer stalled on a background-wait loop, resumed by controller) |
| 5 | Dashboard route/controller/views + `SkipAlertResolver` | 1 round — **human-escalated design decision**, see below |
| 6 | Child-profile switcher dropdown | clean |
| 7 | Cross-tenant regression tests | 1 round — a test claimed to verify cross-lembaga wallet isolation but used a single-party fixture that couldn't catch a leak; rewritten with a genuine two-party fixture |
| 8 | Full Playwright walkthrough + suite verification | clean (implementer stalled twice, controller took over verification/commit directly) |
| Final review | whole-branch review (opus) | 4 Important + 2 Minor findings fixed in one combined commit, plus 1 follow-up test for a self-flagged gap in that fix |

## The one human decision this sub-project required

**`SkipAlertResolver`'s "skip" semantics.** Task 5's brief contained an internal contradiction: its given `SkipAlertResolver` algorithm was a verbatim copy of `AutoAllocationEngine`'s allocation walk (a partially-covered tagihan counts as "allocated," not "skipped" — matching production behavior exactly), but the brief's own test asserted skip-banner behavior for a single under-funded tagihan scenario that, under that same algorithm, would actually receive a partial allocation and NOT be skipped. The implementer initially resolved the contradiction by changing the algorithm to "full-or-skip" semantics (any tagihan not *fully* covered counts as skipped) to satisfy the given test. The task reviewer flagged this as a genuine, unresolved behavioral divergence from `AutoAllocationEngine`/`SaldoTidakCukupNotification`'s real production behavior (Sub-project 05) — not just a bug fix.

**Escalated to the user via `AskUserQuestion`. Decision: true `AutoAllocationEngine` parity governs** (zero-or-skip: only a tagihan receiving literally $0 allocation counts as "skipped"). Fixed: `SkipAlertResolver::resolve()` now matches `AutoAllocationEngine::run()`'s allocation walk line-for-line (independently verified by two separate reviewers at different points in this session). The plan's flawed single-tagihan test was rewritten into a two-tagihan zero-allocation scenario mirroring `SaldoTidakCukupNotificationTest`'s established pattern.

One deliberate, now-documented divergence remains: `AutoAllocationEngine::run()` returns early when `balance <= 0`, before `$skippedTagihan` is ever populated — so a zero-balance wallet with outstanding tagihan produces no backend notification. `SkipAlertResolver` has no such early return, so the dashboard banner correctly *does* fire at balance=0 (the right proactive-warning behavior for a parent viewing their own dashboard, even though the reactive notification system stays silent in the identical scenario). This is now stated explicitly in the resolver's docblock and locked in by a dedicated test — it was flagged in the final review as a case where the docblock overclaimed exact parity, and fixed.

## Process notes

- **Two implementer subagents stalled** (Tasks 4 and 8): both launched a background `php artisan test` process and then returned control saying "waiting for the notification" without ever actually resuming themselves — the harness has no mechanism to wake a stalled subagent on its own background child's completion the way it wakes the controller. Both were resumed once via `SendMessage`; Task 4's resume completed correctly, Task 8's stalled a second time and was killed via `TaskStop`, with the controller verifying its one real code change and completing verification/commit directly. **Recommendation for future plans**: avoid "run this in background and wait for it" instructions in implementer dispatches for full-suite verification steps — have them run synchronously instead.
- **Full-suite test flakiness from concurrent runs**: this session hit two separate incidents (157 reported failures, then 15) that were entirely artifacts of two `php artisan test` processes racing on the same shared MySQL test DB (`pintera_app_test`) under `RefreshDatabase` — not real regressions. Every isolated (non-concurrent) full-suite run throughout this plan's execution showed exactly the same 6 pre-existing, unrelated baseline failures (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest` — documented in Sub-project 05's handoff log, confirmed unrelated to this diff). **Lesson**: never run `php artisan test` with no path filter while any other test process might be running — serialize full-suite checks.
- **Task 1 side effect caught late**: adding `keuangan.akses` bumped the real permission count from 103 to 104, silently breaking 6 hardcoded `Permission::count()->toBe(103)` assertions across 3 unrelated test files (`PermissionSeederTest`, `RoleSeederTest`, `RolePermissionSeederTest`). Not caught by Task 1's own task-scoped review (correctly, since it only tested the new permission file); surfaced by Task 3's full-suite regression check and fixed directly by the controller in commit `6e005ea`.
- **Commit hygiene note**: commit `125635e` ("docs: update task-2-report.md...") accidentally committed a scratch report file under `.superpowers/sdd/` — that directory is `.gitignore`'d, but this one file was apparently already tracked from before the ignore rule existed, so `git add` picked it up. Harmless (it's just an intermediate fix-round report), but worth a `git rm --cached` cleanup at some point if the noise bothers you; not touched in this session to avoid an unrelated destructive-adjacent action mid-plan.

## Final full-suite verification

Last isolated run (after all fixes, commit `12fb095`): `tests/Feature/Keuangan/` — **150 passed**, project-wide `php artisan test` — **6 failed / 1527 passed**, exactly the established baseline, zero new regressions from this sub-project.

## Explicitly out of scope for 6a (deferred to 6b/6c/6d)

- Top-up/checkout form and multi-channel payment flow (VA BRI, QRIS, wallet, transfer manual) — the dashboard's "+ Top Up" button and skip-alert CTA are currently disabled placeholders with a tooltip pointing to 6b.
- Rekap tagihan detail with multi-select.
- Riwayat transaksi & kwitansi PDF, admin logo upload.
- Notification preference toggles.
- Any change to `PaymentAllocationService`, `AutoAllocationEngine`, or `Wallet`'s `topup()`/`debit()` — confirmed untouched throughout this entire sub-project.

## Open items for 6b/6c/6d (from the final whole-branch review, deliberately deferred — not blocking)

1. **"Notifikasi Terbaru" panel is not filtered to the active child**, though the spec explicitly calls for this. The dashboard passes the same unfiltered `NotificationFeedResolver::resolve($user)` the topbar bell uses. Filtering requires a `siswa_id` on Finance notification payloads (currently they carry `tagihan_id` but not `siswa_id`) — **recommend 6b/6c add `siswa_id` to `toDatabase()` payloads** in `TagihanDiterbitkanNotification`, `PembayaranBerhasilNotification`, `SaldoTidakCukupNotification`, etc., to make this cheap when someone picks it up.
2. **No mark-as-read mechanism exists anywhere in 6a.** The unread badge is derived from the capped 10-item feed (not a true unread count), and nothing ever transitions a notification to read — the first-ever unread notification pins the badge indefinitely. This is explicitly 6d's territory (notification preferences); flagging now so it isn't mistaken for a fresh bug later.
3. **Topbar per-request cost**: the `@php` block now runs a notifications query, an `orangTua` lookup, and a `$childOptions` query on every authenticated page load for every role (the pre-existing lembaga-switcher query already uses `once()`; the new additions don't). Low absolute cost today, worth revisiting if the topbar grows further.
4. **`NotificationFeedResolver` lives in `App\Services\Finance\`** but is now invoked from the shared topbar for every role, including non-Finance ones (e.g. Kasus notifications). The namespace will mislead whoever debugs a non-Finance notification path later — consider `App\Services\Notifications\` before 6d builds preference logic on top of it.
5. Carried over from Sub-project 05's own open items (re-confirmed still relevant, not touched here): `PaymentAllocationService::allocate()`'s `paid_amount +=` double-counting on re-call (pre-existing, unrelated to any Keuangan sub-project since); partial-allocation payments don't trigger any notification (explicitly confirmed out of scope for 6a's dashboard too — a `status = 'sebagian'` badge is sufficient presentation).
6. A pre-existing, unrelated bug was discovered (not touched, out of scope): `Admin\DashboardController::lembagaViewData()` throws a `TypeError` for a scope-less `User` (no role, `lembaga_id = null`) hitting `GET /dashboard`. Worth a dedicated bug ticket.

## Verification account

Browser/manual testing throughout this sub-project used the seeded demo account `ortu.demo@permatakraksaan.sch.id` / `password` (from `OrangTuaKaryawanSeeder`).
