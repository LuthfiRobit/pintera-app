# Handoff Log — Keuangan Sub-project 6d: Preferensi Notifikasi & Mark-as-Read

**Status: SHIPPED. This is the final sub-project of the entire Keuangan module — Sub-project 1 through 6d are now all shipped.** All 6 plan tasks implemented, plus a whole-branch code review found 1 Critical + 6 Important findings, fixed and re-reviewed clean. Branch `demo`, **not pushed to remote** per project convention (`git status` clean apart from this log itself).

> This log supersedes an earlier version written directly after implementation, before the whole-branch review ran. That earlier version had three inaccuracies, corrected here: (1) it claimed `FinanceNotification` was refactored "untuk memisahkan logic dari dispatcher" — this did not happen and was explicitly out of scope per the plan's Global Constraints; (2) it reported a full-suite (`php artisan test`, no path filter) run and its 7 failures as this sub-project's verification evidence — that run was explicitly out of scope (a standing user decision from Sub-project 6c2 to avoid full-suite runs for token cost); the correct final gate is the scoped run reported below; (3) it did not follow the plan's required "After all tasks" structure (task-by-task summary, consolidated known-follow-ups list for the whole module).

- Spec: `.agents/specs/keuangan-06d-preferensi-notifikasi.md`
- Plan: `.agents/plans/keuangan-06d-preferensi-notifikasi.md`
- Base commit before implementation: `6107031` (plan commit)
- Final commit: `2d9a04c`
- 8 commits total (6 task commits by the user's own direct implementation + 1 whole-branch-review fix wave + this log correction).

## What this sub-project built

Closed three open items tracked since Sub-project 6a, plus one bug found while investigating them:

- **Fixed `NotificationDispatcher::send()`'s dead preference lookup.** Every Finance notification in this codebase is sent to an `OrangTua` object, never a `User` directly — but the dispatcher's preference resolution only fired `if ($notifiable instanceof User)`, so the channel-preference toggle (below) would have been a complete no-op for every real notification ever sent. Fixed by resolving `$userId` via `OrangTua->user_id` too, with `default => null` preserving prior behavior for any other notifiable type.
- **Notification channel preferences** — a new partial on the existing `/profile` page (not a Keuangan-specific page, since preferences are per-account not per-module), letting a parent toggle WhatsApp/Email for Finance notifications, backed by the pre-existing `UserNotificationPreference` model (unmodified).
- **Mark-as-read** — two new endpoints, `POST /notifikasi/{id}/baca` and `POST /notifikasi/baca-semua`, wired via Alpine.js into both the topbar notification bell and the dashboard's "Notifikasi Terbaru" panel. Uses Laravel's built-in `DatabaseNotification::markAsRead()`.
- **`NotificationFeedResolver` namespace move** — relocated from `App\Services\Finance` to `App\Services\Notifications`, a pure zero-behavior-change refactor closing technical debt tracked since 6a (the resolver serves the topbar bell for every role, not just Keuangan).

## Task-by-task summary

| Task | What | Fix rounds |
|---|---|---|
| 1 | `NotificationDispatcher` fix (OrangTua preference resolution) | clean at task-review time; both new tests present, all 5 pre-existing tests initially unmodified |
| 2 | Notification preference settings on `/profile` | clean — all 4 specified tests present |
| 3 | `NotificationFeedResolver` namespace move | clean at task-review time; regressed in the fix wave (see below) |
| 4 | Mark-as-read backend + topbar wiring | **this task had the most drift from its brief** — see whole-branch review findings |
| 5 | Mark-as-read wired into the dashboard panel | clean |
| 6 | Playwright + scoped regression gate | clean — but its commit message ("fix topbar alpine scope") revealed Task 4's Alpine structure had already deviated from the plan and needed a workaround, which itself needed re-fixing in the review round |
| Final review | whole-branch review (opus) | 1 Critical + 6 Important findings → one fix wave → re-reviewed clean |

## The whole-branch review: what it found and fixed

This sub-project was implemented directly by the user from the plan (same as 6c2), so the whole-branch review was its only quality gate. The plan for 6d was explicitly written with extra precision — every task listed its exact required test count up front — specifically to prevent the class of silent drop that 6c2's review had caught. **The precision worked for Tasks 1, 2, 3, and 5, which were followed essentially verbatim. It did not work for Task 4**, where the pattern recurred concentrated in one task instead of scattered across several.

**1 Critical finding, fixed:** mark-as-read routes sat inside the `keuangan.*` route group (gated by `permission:keuangan.akses` + `resolve.active.siswa`), but the notification bell renders for every role in this app (admin, guru, yayasan — not just parents). A non-parent clicking a notification got a silent 403 that neither Alpine `fetch()` handler ever checked, so the UI reported success while the backend never marked anything read. Fixed by moving both routes to the plain `auth`-only group (their real authorization was always the controller's own scoped queries, not the route middleware) and adding `response.ok` guards before any optimistic UI state change in both handlers.

**6 Important findings, all fixed:**
1. The cross-user 403 test used an acting user with no real `OrangTua` record — middleware, not the controller, produced the 403, so the controller's own authorization had zero real test coverage. Rewritten with two genuine two-party users.
2. 3 of the mark-as-read feature's 6 originally-specified tests were missing (direct-to-user, partial-unread-count, nonexistent-id), replaced with a near-duplicate of a dashboard test. Restored all 3 with real assertions; the duplicate was rewritten to be topbar-specific rather than deleted.
3. The JSON response contract was silently changed from `{"unread_count": N}` to `{"status":"ok"}`, and the `hitungUnread()` helper was dropped entirely. Restored both, with explicit `JsonResponse` return types, and both Alpine handlers now set their local unread count from the server's response rather than a pure client-side decrement.
4. A pre-existing test assertion (`expect($log->user_id)->not->toBe($orangTua->id)`) was deleted from `NotificationDispatcherTest.php` without justification — the line that makes the test's desync-fixture trick actually meaningful. Restored verbatim.
5. `NotificationFeedResolverTest.php` was moved to a new directory (`tests/Feature/Notifications/`) against the plan's explicit "do not move the test file" instruction, silently narrowing what the standing `tests/Feature/Keuangan/` regression command covers. Moved back.
6. The topbar's `x-data` was nested inside `<x-slot name="content">` instead of wrapping the whole `<x-dropdown>` element, breaking Alpine reactivity for the unread badge (patched over with a fragile `document.querySelector` DOM hack) and meaning the dashboard panel could never keep the topbar badge in sync. Restructured so `x-data` wraps the entire dropdown, badge driven by `x-show`/`x-text` again — independently verified by the re-reviewer that Alpine 3's nested-scope inheritance genuinely supports this structure, it wasn't a guess.

**4 non-blocking notes from the fix-wave's own re-review, left as-is** (none warranted a third round):
- The two mark-as-read routes lost the `verified` middleware they had under the old `keuangan.*` group (now `auth` only) — consistent with the sibling `profile.*` routes in the same block, reads as intentional but worth flagging.
- The rewritten topbar test (replacing the vacuous duplicate) now only asserts the bell renders, not that a mark-as-read control exists — narrower coverage than intended, though not vacuous.
- The topbar/dashboard's initial `unreadCount` is derived from the 10-item-capped notification feed, while `hitungUnread()` returns the true total — a user with >10 unread notifications sees a badge that undercounts until their next mark-as-read/reload. Both display sites cap the number at "9+" so this is currently invisible, but it's a latent inconsistency.
- One trailing-whitespace line remains at `topbar.blade.php:7` (a minor cleanup item missed in the fix wave).

## Process notes

- **IDOR discipline held, independently verified by first-principles trace**: the reviewer confirmed `NotifikasiController::bacaSatu()`'s authorization is genuinely scoped-query-only (`$user->notifications()->find($id) ?? $user->orangTua?->notifications()->find($id)`, `abort_if` on null) — no existence oracle, no cross-tenant leak possible. This is a first for this module: every other Keuangan sub-project's review found at least one real IDOR-class gap somewhere.
- **Scope discipline held**: the whole-branch review confirmed the diff touches nothing in `PaymentService`, `PaymentAllocationService`, `AutoAllocationEngine`, `CheckoutController`, or `ManualPaymentController` — this sub-project is genuinely presentation/notification-layer only, as scoped.

## Final scoped verification

Last isolated run (commit `2d9a04c`): `tests/Feature/Keuangan/` + `tests/Feature/ProfileNotificationPreferenceTest.php` — **237 passed** (623 assertions). No full-suite (`php artisan test` with no path filter) run performed anywhere in this sub-project, per the standing user decision made in Sub-project 6c2 (deliberate scope boundary, not an oversight — see the corrected-log note above for why the earlier draft of this log incorrectly reported one).

## Explicitly out of scope for 6d

- Push notifications (`channel_push` stays `false` — no push infrastructure exists in this codebase).
- Preferences scoped per-child (`OrangTua`) rather than per-account (`User`) — considered and explicitly rejected during brainstorming as unnecessary scope (YAGNI), no business need identified.
- Preferences for any module other than `finance` (the `module` column supports it, nothing uses it yet).
- Any change to `FinanceNotification`, individual notification classes, `NotificationLog`/`logAttempt()`, or any payment/checkout controller from 6a/6b/6c/6c2.

## Known follow-ups for the whole Keuangan module (consolidated)

With 6d shipped, Sub-project 1 through 6d are complete. These items were each deferred at least once across the module's sub-projects and remain open for whoever picks up Keuangan-adjacent work next:

1. **`PaymentAllocationService::allocate()`'s `paid_amount +=` double-counting risk if re-called on an already-allocated `Pembayaran`** — pre-existing, first flagged in Sub-project 05, confirmed unrelated to every sub-project's diff since. Needs a dedicated idempotency guard or a ticket of its own.
2. **Admin manual-transfer verification listing (`_daftar.blade.php`) is missing a "Diajukan Oleh" column** despite `index()` eager-loading `requestedBy` for it — spec gap from Sub-project 6c2, small product decision, not a bug.
3. **Riwayat transaction date filters accept relative date strings** (`?dari=now`, `?dari=+1 week`) because the `'date'` Laravel validation rule is `strtotime()`-based, not a strict format check — flagged in 6c2's review, low-risk, would need `date_format:Y-m-d` to close fully.
4. **The topbar/dashboard unread badge undercounts past 10** (this sub-project's own note, above) — cosmetic today given the "9+" cap, but would need `hitungUnread()`'s true count wired into the initial page-load state to fully close.
5. **No admin-facing bundled-topup receipt distinction** — carried from 6c2, cosmetic, the admin manual-payment badge logic has a case for a hypothetically-bundled manual-transfer request that isn't currently reachable via any UI (bundling only exists for VA/QRIS today).

## Verification account

Manual/browser testing used the seeded demo account `ortu.demo@permatakraksaan.sch.id` / `password`, with an unread notification fixture created via tinker for the Playwright mark-as-read + preference-persistence check (Task 6).
