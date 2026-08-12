# Keuangan Sub-project 6a: Fondasi Portal Orang Tua & Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give `orang_tua`-role users a working `/keuangan` dashboard on the existing `web` guard — wallet balance, a live skip-alert banner, a functional cross-module notification bell/panel, and a child switcher — with zero changes to Sub-project 1-5 business logic.

**Architecture:** Reuse the `web` guard + the existing `orang_tua` Spatie role (already used by the Kasus/Pendampingan module) instead of the `portal`/`AkunPendaftar` system. A new `ResolveActiveSiswa` middleware (modeled on `ResolveTenant`) resolves which child's data is in view via an `active_siswa_id` session key. A new `NotificationFeedResolver` service merges `User`- and `OrangTua`-targeted database notifications for the topbar bell (used by ALL roles, not just Keuangan) and the dashboard's "Notifikasi Terbaru" panel.

**Tech Stack:** Laravel 12, Eloquent, Spatie Laravel-permission, Blade + Alpine.js (`<x-app-layout>`), Pest, Playwright (already a dependency, extends `scripts/manual-book-screenshots.mjs`'s pattern).

## Global Constraints

- Guard: `web` only. No route, controller, or view in this plan may reference the `portal` guard or `AkunPendaftar`.
- Permission model: exactly one permission, `keuangan.akses`, gates all of `/keuangan/*`. Do not create per-action Keuangan permissions in this plan.
- Layout: reuse `<x-app-layout>` (`resources/views/layouts/app.blade.php` + `topbar.blade.php` + `sidebar.blade.php`). Do not create a new layout.
- Any query touching `Siswa`, `Tagihan`, `Wallet`, or `OrangTua` for the acting `orang_tua` user MUST use `withoutGlobalScope(\App\Models\Scopes\TenantScope::class)` plus explicit relation-membership authorization — never `lembaga_id` matching (that pattern is for admin-side controllers only, e.g. `ManualPaymentController::siswaLembagaId()`).
- Do not modify `app/Services/Finance/AutoAllocationEngine.php`, `app/Services/Finance/PaymentAllocationService.php`, or `app/Models/Wallet.php`'s `topup()`/`debit()` methods — this sub-project is presentation-only.
- Notification bell/panel: capped at 10 most recent items, no pagination, no "view all" page.
- Browser verification via Playwright is mandatory per task for any task that adds or changes Alpine.js interactivity (child switcher dropdown, bell dropdown) — not deferred to a final step.
- Demo account for all browser verification: `ortu.demo@permatakraksaan.sch.id` / `password` (seeded by `OrangTuaKaryawanSeeder`).

---

## File Structure

- `database/seeders/PermissionSeeder.php` — add `keuangan.akses` to the flat permission list (Task 1).
- `database/seeders/RoleSeeder.php` — grant `keuangan.akses` to the `orang_tua` role (Task 1).
- `app/Services/Finance/NotificationFeedResolver.php` — new. Merges `$notifiable1->notifications` + `$notifiable2?->notifications` (both `MorphMany` via Laravel's `Notifiable` trait), sorts by `created_at` desc, caps at 10 (Task 2).
- `resources/views/layouts/topbar.blade.php` — modify: replace the static "Belum ada notifikasi" bell panel with a real feed + unread badge (Task 3).
- `app/Http/Middleware/ResolveActiveSiswa.php` — new. Resolves `active_siswa_id` session for `/keuangan/*` routes (Task 4).
- `bootstrap/app.php` — modify: register `resolve.active.siswa` middleware alias (Task 4).
- `app/Services/Finance/SkipAlertResolver.php` — new. Read-only replica of `AutoAllocationEngine`'s priority-ordering to compute the "would be skipped" tagihan for a siswa (Task 5).
- `app/Http/Controllers/Keuangan/DashboardController.php` — new. `index()` action for `GET /keuangan` (Task 5).
- `resources/views/keuangan/dashboard.blade.php` — new (Task 5).
- `resources/views/keuangan/tanpa-anak.blade.php` — new (Task 5).
- `routes/web.php` — modify: add the `keuangan.*` route group (Task 5).
- `resources/views/layouts/sidebar.blade.php` — modify: add a `keuangan.akses`-gated nav item to the existing "Keuangan" group (Task 5).
- `resources/views/layouts/topbar.blade.php` — modify again: add the child-profile switcher dropdown (Task 6).
- `scripts/keuangan-6a-browser-check.mjs` — new Playwright script, modeled on `scripts/manual-book-screenshots.mjs`, for interactive verification (introduced in Task 3, extended in Tasks 5/6/8).
- `tests/Feature/Keuangan/NotificationFeedResolverTest.php` — new (Task 2).
- `tests/Feature/Keuangan/TopbarNotificationBellTest.php` — new (Task 3).
- `tests/Feature/Keuangan/ResolveActiveSiswaMiddlewareTest.php` — new (Task 4).
- `tests/Feature/Keuangan/DashboardControllerTest.php` — new (Task 5).
- `tests/Feature/Keuangan/ChildSwitcherTest.php` — new (Task 6).
- `tests/Feature/Keuangan/DashboardAuthorizationTest.php` — new (Task 8, cross-cutting scoping/permission tests).

---

### Task 1: `keuangan.akses` permission + role grant

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/Keuangan/KeuanganPermissionSeedTest.php`

**Interfaces:**
- Produces: permission string `keuangan.akses` (guard `web`), granted to role `orang_tua`. Later tasks gate routes with `permission:keuangan.akses` and sidebar visibility with `Auth::user()->can('keuangan.akses')`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/KeuanganPermissionSeedTest.php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('grants keuangan.akses to the orang_tua role after seeding', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');

    expect($user->can('keuangan.akses'))->toBeTrue();
});

it('does not grant keuangan.akses to the guru role', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('guru');

    expect($user->can('keuangan.akses'))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/KeuanganPermissionSeedTest.php`
Expected: FAIL — `keuangan.akses` permission does not exist yet, `$user->can('keuangan.akses')` returns `false` for both tests (first assertion fails).

- [ ] **Step 3: Add the permission and grant it**

In `database/seeders/PermissionSeeder.php`, add `'keuangan.akses',` to the `$permissions` array (put it near the other Keuangan permissions, after the `cicilan.kelola` line):

```php
            'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
            'tagihan.view', 'tagihan.buat-susulan',
            'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual',
            'cicilan.kelola',
            'keuangan.akses',
```

In `database/seeders/RoleSeeder.php`, change the `orang_tua` block:

```php
            if ($name === 'orang_tua') {
                $role->givePermissionTo([
                    'kasus.ajukan', 'kasus.view', 'kasus.consent',
                    'keuangan.akses',
                ]);
            }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/KeuanganPermissionSeedTest.php`
Expected: PASS (2 passed)

- [ ] **Step 5: Re-seed the real dev database**

Run: `php artisan db:seed --class=PermissionSeeder` then `php artisan db:seed --class=RoleSeeder` (both are `firstOrCreate`/idempotent `givePermissionTo` calls — safe to re-run against the existing dev DB `pintera_app`).

- [ ] **Step 6: Commit**

```bash
git add database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php tests/Feature/Keuangan/KeuanganPermissionSeedTest.php
git commit -m "feat(keuangan): add keuangan.akses permission for orang_tua role"
```

---

### Task 2: `NotificationFeedResolver` service

**Files:**
- Create: `app/Services/Finance/NotificationFeedResolver.php`
- Test: `tests/Feature/Keuangan/NotificationFeedResolverTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `NotificationFeedResolver::resolve(\App\Models\User $user): \Illuminate\Support\Collection` — returns up to 10 `Illuminate\Notifications\DatabaseNotification` models (Laravel's standard `database` channel model, table `notifications`), merged from `$user->notifications` and `$user->orangTua?->notifications`, sorted `created_at` desc. Later tasks (3, 5) call this exact signature.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/NotificationFeedResolverTest.php

use App\Models\OrangTua;
use App\Models\User;
use App\Notifications\Finance\FinanceNotification;
use App\Services\Finance\NotificationFeedResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

class FeedTestNotification extends FinanceNotification
{
    public function __construct(private readonly string $label) {}

    public function isUrgent(): bool { return false; }

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array { return ['message' => $this->label]; }

    public function toMail(object $notifiable): MailMessage { return (new MailMessage())->line('test'); }

    public function toWhatsApp(object $notifiable): ?string { return null; }
}

it('merges notifications sent to the User directly and to their linked OrangTua', function () {
    $user = User::factory()->create(['lembaga_id' => null]);
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Feed',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200001111',
    ]);

    $user->notify(new FeedTestNotification('ke-user'));
    $orangTua->notify(new FeedTestNotification('ke-orangtua'));

    $feed = app(NotificationFeedResolver::class)->resolve($user);

    expect($feed)->toHaveCount(2);
    expect($feed->pluck('data.message')->all())->toContain('ke-user', 'ke-orangtua');
});

it('caps the merged feed at 10 items, newest first', function () {
    $user = User::factory()->create(['lembaga_id' => null]);
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Feed',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200001112',
    ]);

    foreach (range(1, 6) as $i) {
        $user->notify(new FeedTestNotification("user-{$i}"));
    }
    foreach (range(1, 6) as $i) {
        $orangTua->notify(new FeedTestNotification("ortu-{$i}"));
    }

    $feed = app(NotificationFeedResolver::class)->resolve($user);

    expect($feed)->toHaveCount(10);
    expect($feed->first()->data['message'])->toBe('ortu-6');
});

it('returns only the User notifications when the user has no linked OrangTua', function () {
    $user = User::factory()->create();
    $user->notify(new FeedTestNotification('solo'));

    $feed = app(NotificationFeedResolver::class)->resolve($user);

    expect($feed)->toHaveCount(1);
    expect($feed->first()->data['message'])->toBe('solo');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/NotificationFeedResolverTest.php`
Expected: FAIL with "Class \"App\Services\Finance\NotificationFeedResolver\" not found"

- [ ] **Step 3: Write the implementation**

```php
<?php
// app/Services/Finance/NotificationFeedResolver.php

namespace App\Services\Finance;

use App\Models\User;
use Illuminate\Support\Collection;

class NotificationFeedResolver
{
    private const LIMIT = 10;

    /**
     * @return Collection<int, \Illuminate\Notifications\DatabaseNotification>
     */
    public function resolve(User $user): Collection
    {
        $userNotifications = $user->notifications()->latest()->limit(self::LIMIT)->get();

        $orangTua = $user->orangTua;
        $orangTuaNotifications = $orangTua !== null
            ? $orangTua->notifications()->latest()->limit(self::LIMIT)->get()
            : collect();

        return $userNotifications
            ->concat($orangTuaNotifications)
            ->sortByDesc('created_at')
            ->values()
            ->take(self::LIMIT);
    }
}
```

Note: each source query is independently capped at 10 before merging (so a user with 6 of each source still only pulls 6+6=12 rows, not thousands), then the merged collection is truncated to the final 10 in memory — this keeps the query cheap without needing a UNION across two morph-target queries.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/NotificationFeedResolverTest.php`
Expected: PASS (3 passed)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/NotificationFeedResolver.php tests/Feature/Keuangan/NotificationFeedResolverTest.php
git commit -m "feat(keuangan): add NotificationFeedResolver merging User+OrangTua notifications"
```

---

### Task 3: Functional topbar notification bell (all roles)

**Files:**
- Modify: `resources/views/layouts/topbar.blade.php`
- Create: `scripts/keuangan-6a-browser-check.mjs`
- Test: `tests/Feature/Keuangan/TopbarNotificationBellTest.php`

**Interfaces:**
- Consumes: `NotificationFeedResolver::resolve(User $user): Collection` (Task 2).
- Produces: topbar bell shows real notifications for every role (not Keuangan-specific), each `DatabaseNotification`'s `data['message']` rendered as the row text, `created_at->diffForHumans()` as the timestamp, and an unread-count badge from `$user->unreadNotifications()->count()`. `data['message']` is the shared free-text key already written by `FinanceNotification::toDatabase()` implementations (Sub-project 05) and by `KasusDiajukanNotification` (checked — Kasus notifications also write `['message' => ...]` to `toDatabase()`), so no per-notification-type branching is needed in the view.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/TopbarNotificationBellTest.php

use App\Models\User;
use App\Notifications\Finance\FinanceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

class BellTestNotification extends FinanceNotification
{
    public function isUrgent(): bool { return false; }

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array { return ['message' => 'Tagihan SPP Agustus terbit.']; }

    public function toMail(object $notifiable): MailMessage { return (new MailMessage())->line('test'); }

    public function toWhatsApp(object $notifiable): ?string { return null; }
}

it('shows real notifications in the topbar bell instead of the static placeholder', function () {
    $user = User::factory()->create();
    $user->notify(new BellTestNotification());

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Tagihan SPP Agustus terbit.');
    $response->assertDontSee('Belum ada notifikasi.');
});

it('still shows the empty state when there are zero notifications', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Belum ada notifikasi.');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/TopbarNotificationBellTest.php`
Expected: FAIL on the first test — the bell panel is still the static placeholder, `assertSee('Tagihan SPP Agustus terbit.')` fails.

- [ ] **Step 3: Wire the bell to real data**

`resources/views/layouts/topbar.blade.php` currently starts with a single `@php` block (lines 1-7). Extend it to also resolve the notification feed, then replace the static panel body:

```blade
@php
    $isYayasan = Auth::user()->widestScopeLevel() === 'yayasan';
    $activeLembagaId = session('active_lembaga_id');
    $lembagaOptions = $isYayasan ? once(fn () => \App\Models\Lembaga::query()->select('id', 'nama')->orderBy('nama')->get()) : collect();
    $activeLembaga = $activeLembagaId ? $lembagaOptions->firstWhere('id', $activeLembagaId) : null;
    $sealLabel = $activeLembaga ? Str::of($activeLembaga->nama)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : 'YY';
    $notificationFeed = app(\App\Services\Finance\NotificationFeedResolver::class)->resolve(Auth::user());
    $unreadCount = $notificationFeed->whereNull('read_at')->count();
@endphp
```

Replace the bell trigger button (lines 33-35 in the original) to show the unread badge:

```blade
        <x-dropdown align="right" width="w-80">
            <x-slot name="trigger">
                <button type="button" class="relative flex h-[38px] w-[38px] items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-50" aria-label="Notifikasi">
                    <x-icon name="notifications" class="h-4 w-4" />
                    @if ($unreadCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                    @endif
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 pb-3 pt-1">
                    <p class="font-display text-sm font-bold text-gray-900">Notifikasi</p>
                </div>
                @if ($notificationFeed->isEmpty())
                    <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                        <x-icon name="notifications" class="h-6 w-6 text-gray-300" />
                        <p class="text-sm text-gray-500">Belum ada notifikasi.</p>
                    </div>
                @else
                    <div class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
                        @foreach ($notificationFeed as $notification)
                            <div class="px-4 py-3 {{ $notification->read_at === null ? 'bg-brand-50/40' : '' }}">
                                <p class="text-sm text-gray-800">{{ $notification->data['message'] ?? '-' }}</p>
                                <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-slot>
        </x-dropdown>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/TopbarNotificationBellTest.php`
Expected: PASS (2 passed)

- [ ] **Step 5: Create the reusable Playwright verification script**

```javascript
// scripts/keuangan-6a-browser-check.mjs
// Dev-only tool: interactive Playwright verification for Keuangan Sub-project 6a
// (orang tua dashboard, child switcher, notification bell). Unlike
// manual-book-screenshots.mjs, this clicks/asserts DOM state rather than only
// capturing screenshots — needed because Alpine.js interactivity bugs (e.g. a
// @js() escaping bug inside @click) do not show up in Pest HTTP tests.
// Usage: node scripts/keuangan-6a-browser-check.mjs [--check=<bell|switcher|dashboard|all>]

import { chromium } from 'playwright';

const BASE_URL = process.env.KEUANGAN_CHECK_BASE_URL || 'http://localhost';
const DEMO_ACCOUNT = { email: 'ortu.demo@permatakraksaan.sch.id', password: 'password' };

async function login(page) {
  await page.goto(`${BASE_URL}/login`);
  await page.fill('input[name=email]', DEMO_ACCOUNT.email);
  await page.fill('input[name=password]', DEMO_ACCOUNT.password);
  await page.click('button[type=submit]');
  await page.waitForURL(/\/(dashboard|keuangan)/);
}

async function checkBell(page) {
  await page.goto(`${BASE_URL}/dashboard`);
  const bellButton = page.locator('button[aria-label="Notifikasi"]');
  await bellButton.click();
  const panel = page.locator('text=Notifikasi').first();
  await panel.waitFor({ state: 'visible', timeout: 3000 });
  console.log('[bell] dropdown opened and panel visible: OK');
}

const args = process.argv.slice(2);
const checkArg = args.find((a) => a.startsWith('--check='))?.split('=')[1] ?? 'all';

const browser = await chromium.launch();
const page = await browser.newPage();

try {
  await login(page);
  if (checkArg === 'all' || checkArg === 'bell') {
    await checkBell(page);
  }
} finally {
  await browser.close();
}
```

- [ ] **Step 6: Run the browser check against the local dev server**

Ensure the local Laravel dev server is running (`php artisan serve` or Laragon's vhost), then run:

Run: `node scripts/keuangan-6a-browser-check.mjs --check=bell`
Expected output: `[bell] dropdown opened and panel visible: OK`

If it fails, read the Playwright error (element not found, timeout) — this is exactly the class of bug (Alpine dropdown not opening, `@click.outside` firing prematurely) that Pest's `assertSee` cannot catch, per this plan's Global Constraints.

- [ ] **Step 7: Commit**

```bash
git add resources/views/layouts/topbar.blade.php scripts/keuangan-6a-browser-check.mjs tests/Feature/Keuangan/TopbarNotificationBellTest.php
git commit -m "feat(keuangan): make topbar notification bell functional for all roles"
```

---

### Task 4: `ResolveActiveSiswa` middleware

**Files:**
- Create: `app/Http/Middleware/ResolveActiveSiswa.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/Keuangan/ResolveActiveSiswaMiddlewareTest.php`

**Interfaces:**
- Consumes: `keuangan.akses` permission (Task 1) — this middleware is only ever applied behind that gate (wired in Task 5's route group), so it does not re-check the permission itself.
- Produces: on every request through the `/keuangan/*` group, sets `$request->attributes->set('activeSiswa', ?Siswa $siswa)` — `null` when the `OrangTua` has no linked `Siswa` at all. Aborts `403` when `auth()->user()->orangTua === null` (data-integrity guard). Task 5's `DashboardController` reads `$request->attributes->get('activeSiswa')`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/ResolveActiveSiswaMiddlewareTest.php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaWithChildren(array $children = ['utama' => true]): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Test',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200002222',
    ]);

    $siswaList = [];
    foreach ($children as $key => $isKontakUtama) {
        $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
        $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => $isKontakUtama]);
        $siswaList[] = $siswa;
    }

    return [$user, $orangTua, $siswaList];
}

it('defaults active siswa to the kontak utama child', function () {
    [$user, , $siswaList] = actingAsOrangTuaWithChildren(['utama' => true, 'lainnya' => false]);
    [$kontakUtama] = $siswaList;

    $response = $this->actingAs($user)->get('/keuangan/__test-probe');

    $response->assertContent((string) $kontakUtama->id);
    expect(session('active_siswa_id'))->toBeNull(); // resolved per-request, not persisted until switched
});

it('lets a valid switch_siswa update the session', function () {
    [$user, , $siswaList] = actingAsOrangTuaWithChildren(['satu' => true, 'dua' => false]);
    [, $keduaAnak] = $siswaList;

    $this->actingAs($user)->get('/keuangan/__test-probe?switch_siswa='.$keduaAnak->id);

    expect(session('active_siswa_id'))->toBe($keduaAnak->id);
});

it('silently ignores a switch_siswa id that does not belong to this orang tua', function () {
    [$user] = actingAsOrangTuaWithChildren(['punya' => true]);
    $strangerSiswa = Siswa::factory()->create();

    $this->actingAs($user)->get('/keuangan/__test-probe?switch_siswa='.$strangerSiswa->id);

    expect(session('active_siswa_id'))->toBeNull();
});

it('aborts 403 for an orang_tua-role user with no linked OrangTua profile', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');

    $response = $this->actingAs($user)->get('/keuangan/__test-probe');

    $response->assertForbidden();
});
```

- [ ] **Step 2: Register a temporary test-only probe route**

Add to `routes/web.php` (kept permanently — Task 5 replaces `__test-probe` usage in tests with the real `keuangan.dashboard` route, but a lightweight probe route is harmless to leave for future middleware-only tests; **actually**, to avoid a permanent throwaway route, register it only inside the test file instead):

Replace the test's route dependency: since `routes/web.php` should not carry test-only routes, define the probe route inside the test file itself via `Route::middleware(...)->get(...)` in a `beforeEach()`. Update the test file's top to add:

```php
<?php
// tests/Feature/Keuangan/ResolveActiveSiswaMiddlewareTest.php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'auth', 'permission:keuangan.akses', 'resolve.active.siswa'])
        ->get('/keuangan/__test-probe', function (\Illuminate\Http\Request $request) {
            $activeSiswa = $request->attributes->get('activeSiswa');

            return response($activeSiswa?->id !== null ? (string) $activeSiswa->id : 'none');
        });
});
```

(keep the rest of the file — `actingAsOrangTuaWithChildren()` and the four `it(...)` blocks — exactly as written in Step 1, appended after this `beforeEach`).

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/ResolveActiveSiswaMiddlewareTest.php`
Expected: FAIL — `Target class [resolve.active.siswa] does not exist` (middleware alias not registered yet).

- [ ] **Step 4: Write the middleware**

```php
<?php
// app/Http/Middleware/ResolveActiveSiswa.php

namespace App\Http\Middleware;

use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveSiswa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $orangTua = $user->orangTua;

        abort_if($orangTua === null, 403, 'Akun ini tidak memiliki profil orang tua yang valid.');

        if ($request->has('switch_siswa')) {
            $value = $request->query('switch_siswa');

            if ($orangTua->siswa()->withoutGlobalScope(TenantScope::class)->whereKey($value)->exists()) {
                session(['active_siswa_id' => (int) $value]);
            }
            // Invalid/foreign id: silently ignored, matching ResolveTenant's
            // active_lembaga_id pattern — no error surfaced to the user.
        }

        $activeSiswaId = session('active_siswa_id');
        $activeSiswa = null;

        if ($activeSiswaId !== null) {
            $activeSiswa = $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->whereKey($activeSiswaId)->first();
        }

        if ($activeSiswa === null) {
            $activeSiswa = $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->wherePivot('is_kontak_utama', true)->first()
                ?? $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->first();
        }

        $request->attributes->set('activeSiswa', $activeSiswa);

        return $next($request);
    }
}
```

- [ ] **Step 5: Register the middleware alias**

In `bootstrap/app.php`, find the `withMiddleware` closure's alias block (where `'portal.verified' => \App\Http\Middleware\EnsureAkunPendaftarVerified::class,` is registered) and add:

```php
            'portal.verified' => \App\Http\Middleware\EnsureAkunPendaftarVerified::class,
            'resolve.active.siswa' => \App\Http\Middleware\ResolveActiveSiswa::class,
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/ResolveActiveSiswaMiddlewareTest.php`
Expected: PASS (4 passed)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/ResolveActiveSiswa.php bootstrap/app.php tests/Feature/Keuangan/ResolveActiveSiswaMiddlewareTest.php
git commit -m "feat(keuangan): add ResolveActiveSiswa middleware for child-switcher session"
```

---

### Task 5: Dashboard route, controller, skip-alert banner, views, sidebar entry

**Files:**
- Create: `app/Services/Finance/SkipAlertResolver.php`
- Create: `app/Http/Controllers/Keuangan/DashboardController.php`
- Create: `resources/views/keuangan/dashboard.blade.php`
- Create: `resources/views/keuangan/tanpa-anak.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Keuangan/DashboardControllerTest.php`

**Interfaces:**
- Consumes: `ResolveActiveSiswa` middleware's `$request->attributes->get('activeSiswa')` (Task 4); `NotificationFeedResolver::resolve()` (Task 2).
- Produces: `SkipAlertResolver::resolve(\App\Models\Siswa $siswa): ?array` returning `['tagihan' => Tagihan, 'selisih' => float]` or `null` when nothing would be skipped. Route name `keuangan.dashboard`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/DashboardControllerTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForDashboard(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Dashboard']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Dashboard',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200003333',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return [$user, $orangTua, $siswa];
}

it('shows the wallet balance and VA number on the dashboard', function () {
    [$user, , $siswa] = actingAsOrangTuaForDashboard();
    $siswa->wallet->update(['balance' => 250000, 'va_number' => '8808081234567890']);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('250.000', false);
    $response->assertSee('8808081234567890');
});

it('shows the skip-alert banner when balance cannot cover the highest-priority tagihan', function () {
    [$user, , $siswa] = actingAsOrangTuaForDashboard();
    $siswa->wallet->update(['balance' => 50000]);
    $jenis = JenisTagihan::factory()->create(['priority_score' => 1, 'nama' => 'SPP']);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 200000, 'net_amount' => 200000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(5),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('150.000', false); // shortfall = 200000 - 50000
});

it('does not show the skip-alert banner when balance fully covers all tagihan', function () {
    [$user, , $siswa] = actingAsOrangTuaForDashboard();
    $siswa->wallet->update(['balance' => 500000]);
    $jenis = JenisTagihan::factory()->create(['priority_score' => 1]);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 200000, 'net_amount' => 200000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(5),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertDontSee('Top-up Rp');
});

it('shows the "tanpa anak" page for an orang tua with zero linked siswa', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Tanpa Anak',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200004444',
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('belum ada anak terdaftar', false);
});

it('blocks a user without keuangan.akses permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/DashboardControllerTest.php`
Expected: FAIL — `route('keuangan.dashboard')` throws `RouteNotFoundException` (route doesn't exist yet).

- [ ] **Step 3: Write `SkipAlertResolver`**

```php
<?php
// app/Services/Finance/SkipAlertResolver.php

namespace App\Services\Finance;

use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\Tagihan;

class SkipAlertResolver
{
    /**
     * Read-only replica of AutoAllocationEngine::run()'s priority ordering and
     * allocation walk, used ONLY to compute what the banner should show — never
     * touches the wallet or any tagihan row. Does not call AutoAllocationEngine
     * itself, per this plan's Global Constraints (6a does not modify or invoke
     * that engine's write path from the dashboard).
     *
     * @return array{tagihan: Tagihan, selisih: float}|null
     */
    public function resolve(Siswa $siswa): ?array
    {
        $wallet = $siswa->wallet;

        if ($wallet === null) {
            return null;
        }

        $tagihans = $siswa->tagihan()
            ->withoutGlobalScope(TenantScope::class)
            ->join('jenis_tagihan', 'tagihan.jenis_tagihan_id', '=', 'jenis_tagihan.id')
            ->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
            ->orderBy('jenis_tagihan.priority_score', 'asc')
            ->orderBy('tagihan.jatuh_tempo', 'asc')
            ->orderBy('tagihan.id', 'asc')
            ->select('tagihan.*')
            ->get();

        if ($tagihans->isEmpty()) {
            return null;
        }

        $saldo = (float) $wallet->balance;
        $allocatedIds = [];

        foreach ($tagihans as $tagihan) {
            if ($saldo <= 0) {
                break;
            }

            $sisaTagihan = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;
            $amountToPay = min($saldo, $sisaTagihan);

            if ($amountToPay > 0) {
                $saldo -= $amountToPay;
                $allocatedIds[] = $tagihan->id;
            }
        }

        $skipped = $tagihans->whereNotIn('id', $allocatedIds)->values();

        if ($skipped->isEmpty()) {
            return null;
        }

        $tagihan = $skipped->first();
        $selisih = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;

        return ['tagihan' => $tagihan->load('jenisTagihan'), 'selisih' => $selisih];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php
// app/Http/Controllers/Keuangan/DashboardController.php

namespace App\Http\Controllers\Keuangan;

use App\Services\Finance\NotificationFeedResolver;
use App\Services\Finance\SkipAlertResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function __construct(
        private readonly SkipAlertResolver $skipAlertResolver,
        private readonly NotificationFeedResolver $notificationFeedResolver,
    ) {
    }

    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        $wallet = $activeSiswa->wallet;
        $skipAlert = $this->skipAlertResolver->resolve($activeSiswa);
        $notificationFeed = $this->notificationFeedResolver->resolve($request->user());

        return view('keuangan.dashboard', [
            'activeSiswa' => $activeSiswa,
            'wallet' => $wallet,
            'skipAlert' => $skipAlert,
            'notificationFeed' => $notificationFeed,
        ]);
    }
}
```

- [ ] **Step 5: Write the views**

```blade
{{-- resources/views/keuangan/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Dompet &amp; Tagihan — {{ $activeSiswa->nama_lengkap }}</h2>
    </x-slot>

    <div class="space-y-6">
        @if ($skipAlert !== null)
            <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-display text-sm font-bold text-amber-800">Saldo tidak cukup untuk {{ $skipAlert['tagihan']->jenisTagihan->nama }}</p>
                    <p class="mt-1 text-sm text-amber-700">Kekurangan Rp{{ number_format($skipAlert['selisih'], 0, ',', '.') }} agar tagihan prioritas tertinggi ini bisa terbayar otomatis.</p>
                </div>
                <button type="button" disabled title="Checkout top-up akan tersedia di Sub-project 6b" class="inline-flex cursor-not-allowed items-center justify-center rounded-xl bg-amber-600/50 px-4 py-2 text-sm font-semibold text-white">
                    Top-up Rp{{ number_format($skipAlert['selisih'], 0, ',', '.') }} Sekarang
                </button>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <p class="text-sm text-gray-500">Saldo Wallet</p>
            <p class="mt-1 font-display text-3xl font-bold text-gray-900">Rp{{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}</p>
            @if ($wallet?->va_number)
                <p class="mt-2 text-sm text-gray-500">No. VA: <span class="font-mono">{{ $wallet->va_number }}</span></p>
            @endif
            <button type="button" disabled title="Halaman top-up akan tersedia di Sub-project 6b" class="mt-4 inline-flex cursor-not-allowed items-center justify-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-400">
                + Top Up
            </button>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <p class="font-display text-sm font-bold text-gray-900">Notifikasi Terbaru</p>
            @if ($notificationFeed->isEmpty())
                <p class="mt-3 text-sm text-gray-500">Belum ada notifikasi.</p>
            @else
                <div class="mt-3 divide-y divide-gray-100">
                    @foreach ($notificationFeed as $notification)
                        <div class="py-3">
                            <p class="text-sm text-gray-800">{{ $notification->data['message'] ?? '-' }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
```

```blade
{{-- resources/views/keuangan/tanpa-anak.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Dompet &amp; Tagihan</h2>
    </x-slot>

    <div class="flex flex-col items-center gap-3 rounded-2xl border border-gray-200 bg-white p-10 text-center">
        <x-icon name="user-x" class="h-8 w-8 text-gray-300" />
        <p class="font-display text-base font-bold text-gray-900">Belum ada anak terdaftar</p>
        <p class="max-w-md text-sm text-gray-500">Akun Anda belum terhubung dengan data siswa manapun. Silakan hubungi admin lembaga untuk menautkan profil anak Anda.</p>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Register the route**

In `routes/web.php`, add after the `require __DIR__.'/admin.php';` line (before `require __DIR__.'/spmb.php';`):

```php
Route::middleware(['auth', 'verified', 'permission:keuangan.akses', 'resolve.active.siswa'])
    ->prefix('keuangan')->name('keuangan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Keuangan\DashboardController::class, 'index'])->name('dashboard');
    });
```

- [ ] **Step 7: Add the sidebar nav entry**

In `resources/views/layouts/sidebar.blade.php`, add one line to the existing "Keuangan" group's `items` array (after the `pembayaran.view` line):

```php
                Auth::user()->can('pembayaran.view') ? ['route' => 'admin.pembayaran.index', 'pattern' => 'admin.pembayaran.*', 'label' => 'Verifikasi Pembayaran', 'icon' => 'banknote'] : null,
                Auth::user()->can('keuangan.akses') ? ['route' => 'keuangan.dashboard', 'pattern' => 'keuangan.*', 'label' => 'Dompet & Tagihan Saya', 'icon' => 'wallet'] : null,
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/DashboardControllerTest.php`
Expected: PASS (5 passed)

- [ ] **Step 9: Manual browser verification (admin side first, per this plan's workflow note)**

Before verifying the parent-facing dashboard, confirm from the admin side that the demo account's child link is intact — this catches seed/data drift before it's misdiagnosed as a dashboard bug. Log in as `superadmin@sistem.test` / `password`, visit `/admin/siswa`, and confirm a student is linked to orang tua `ortu.demo@permatakraksaan.sch.id` (search by the parent's name/phone from `OrangTuaKaryawanSeeder`, or check `/admin/orang-tua` if that listing exists). If no student is linked, run `php artisan db:seed --class=OrangTuaKaryawanSeeder` against the local dev DB first.

Then verify the parent-facing dashboard: log in as `ortu.demo@permatakraksaan.sch.id` / `password` in a browser, visit `/keuangan`, and confirm the wallet card and (if applicable) skip-alert banner render without errors.

- [ ] **Step 10: Commit**

```bash
git add app/Services/Finance/SkipAlertResolver.php app/Http/Controllers/Keuangan/DashboardController.php resources/views/keuangan/dashboard.blade.php resources/views/keuangan/tanpa-anak.blade.php routes/web.php resources/views/layouts/sidebar.blade.php tests/Feature/Keuangan/DashboardControllerTest.php
git commit -m "feat(keuangan): add orang tua dashboard with wallet card and skip-alert banner"
```

---

### Task 6: Child switcher dropdown

**Files:**
- Modify: `resources/views/layouts/topbar.blade.php`
- Test: `tests/Feature/Keuangan/ChildSwitcherTest.php`

**Interfaces:**
- Consumes: `ResolveActiveSiswa` middleware's session/query-param mechanism (Task 4); only renders when `Auth::user()->orangTua !== null`.
- Produces: nothing consumed by later tasks in this plan (6a is the last task set that touches the topbar).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/ChildSwitcherTest.php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('shows the child switcher dropdown when the orang tua has more than one child', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Pertama']);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Kedua']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Dua Anak',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005555',
    ]);
    $orangTua->siswa()->attach($siswaA->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTua->siswa()->attach($siswaB->id, ['hubungan' => 'ayah', 'is_kontak_utama' => false]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Anak Pertama');
    $response->assertSee('Anak Kedua');
});

it('does not show the child switcher for a single-child orang tua', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Tunggal']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Satu Anak',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200006666',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Pilih Profil Anak');
});

it('does not show the child switcher for non-orang_tua roles', function () {
    $user = User::factory()->create();
    $user->assignRole('guru');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Pilih Profil Anak');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/ChildSwitcherTest.php`
Expected: FAIL on the first test — child names not present in the topbar yet.

- [ ] **Step 3: Add the switcher to the topbar**

Extend the `@php` block at the top of `resources/views/layouts/topbar.blade.php` (added to in Task 3) with the child list:

```blade
    $orangTua = Auth::user()->orangTua;
    $childOptions = $orangTua !== null
        ? $orangTua->siswa()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->select('siswa.id', 'siswa.nama_lengkap')->orderBy('siswa.nama_lengkap')->get()
        : collect();
    $activeSiswaId = session('active_siswa_id');
```

Add the switcher markup right after the closing `@endif` of the existing `$isYayasan` lembaga-switcher block (before the profile `<x-dropdown>`):

```blade
        @if ($orangTua !== null && $childOptions->count() > 1)
            <div x-data="{ open: false }" class="relative">
                <button
                    @click="open = !open"
                    class="flex items-center gap-2.5 rounded-full border border-brand-100 bg-brand-50 py-1 pl-1 pr-3 transition hover:bg-brand-100"
                >
                    <x-icon name="users" class="h-4 w-4 text-brand-600" />
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-[10px] uppercase tracking-[0.12em] text-gray-400">Pilih Profil Anak</span>
                        <span class="block max-w-[10rem] truncate text-sm font-medium text-gray-900">{{ $childOptions->firstWhere('id', $activeSiswaId)?->nama_lengkap ?? $childOptions->first()->nama_lengkap }}</span>
                    </span>
                    <x-icon name="expand_more" class="h-[18px] w-[18px] text-gray-500" />
                </button>

                <div
                    x-show="open" @click.outside="open = false" x-transition
                    class="absolute right-0 z-30 mt-2 w-64 rounded-2xl border border-gray-200 bg-white py-2 shadow-elevated"
                    style="display: none;"
                >
                    @foreach ($childOptions as $option)
                        <a
                            href="{{ request()->fullUrlWithQuery(['switch_siswa' => $option->id]) }}"
                            class="flex items-center justify-between px-4 py-2 text-sm {{ $activeSiswaId === $option->id ? 'font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}"
                        >
                            {{ $option->nama_lengkap }}
                            @if ($activeSiswaId === $option->id)<span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Keuangan/ChildSwitcherTest.php`
Expected: PASS (3 passed)

- [ ] **Step 5: Extend the Playwright script and verify interactively**

Add to `scripts/keuangan-6a-browser-check.mjs`, after the `checkBell` function:

```javascript
async function checkSwitcher(page) {
  await page.goto(`${BASE_URL}/keuangan`);
  const switcherButton = page.locator('button:has-text("Pilih Profil Anak")');
  const count = await switcherButton.count();
  if (count === 0) {
    console.log('[switcher] demo account has 1 child (or none) — dropdown correctly hidden: OK');
    return;
  }
  await switcherButton.click();
  const firstOption = page.locator('a[href*="switch_siswa"]').first();
  await firstOption.waitFor({ state: 'visible', timeout: 3000 });
  await firstOption.click();
  await page.waitForLoadState('networkidle');
  console.log('[switcher] dropdown opened, option clicked, page reloaded: OK');
}
```

And extend the dispatch block:

```javascript
  if (checkArg === 'all' || checkArg === 'bell') {
    await checkBell(page);
  }
  if (checkArg === 'all' || checkArg === 'switcher') {
    await checkSwitcher(page);
  }
```

Run: `node scripts/keuangan-6a-browser-check.mjs --check=switcher`
Expected output: either `[switcher] demo account has 1 child (or none) — dropdown correctly hidden: OK` or `[switcher] dropdown opened, option clicked, page reloaded: OK`, depending on how many children the seeded demo account has. Either output is a pass; a Playwright timeout/error is not.

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/topbar.blade.php scripts/keuangan-6a-browser-check.mjs tests/Feature/Keuangan/ChildSwitcherTest.php
git commit -m "feat(keuangan): add child profile switcher dropdown to topbar"
```

---

### Task 7: Cross-siswa access guard (belt-and-suspenders on `withoutGlobalScope`)

**Files:**
- Test: `tests/Feature/Keuangan/DashboardAuthorizationTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1, 4, 5 — this task adds no new production code, only regression tests proving the `withoutGlobalScope` + relation-membership pattern actually prevents cross-parent data leaks (the project's most-recurring bug class).

- [ ] **Step 1: Write the tests**

```php
<?php
// tests/Feature/Keuangan/DashboardAuthorizationTest.php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('never resolves another parent\'s child as the active siswa via switch_siswa', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswaMine = Siswa::factory()->create(['lembaga_id' => $lembagaA->id, 'nama_lengkap' => 'Anak Saya']);
    $siswaOther = Siswa::factory()->create(['lembaga_id' => $lembagaB->id, 'nama_lengkap' => 'Anak Orang Lain']);

    $me = User::factory()->create(['lembaga_id' => null]);
    $me->assignRole('orang_tua');
    $ortuMe = OrangTua::create([
        'user_id' => $me->id, 'nama_lengkap' => 'Saya', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
    ]);
    $ortuMe->siswa()->attach($siswaMine->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $otherUser = User::factory()->create(['lembaga_id' => null]);
    $otherUser->assignRole('orang_tua');
    $ortuOther = OrangTua::create([
        'user_id' => $otherUser->id, 'nama_lengkap' => 'Orang Lain', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200008888',
    ]);
    $ortuOther->siswa()->attach($siswaOther->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    // Try to hijack the session into showing the other parent's child.
    $response = $this->actingAs($me)->get(route('keuangan.dashboard', ['switch_siswa' => $siswaOther->id]));

    $response->assertOk();
    $response->assertSee('Anak Saya');
    $response->assertDontSee('Anak Orang Lain');
});

it('scopes the skip-alert and wallet data to the correct tenant across two lembaga', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $siswa->wallet->update(['balance' => 999000, 'va_number' => '8808089999999999']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Lintas Lembaga', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009999',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    // No active_lembaga_id session set (this admin-side concept is irrelevant for an
    // orang_tua user, who has no lembaga_id of their own) — the dashboard must still
    // resolve the wallet correctly via the relation-membership path, not lembaga_id.
    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('999.000', false);
    $response->assertSee('8808089999999999');
});
```

- [ ] **Step 2: Run tests**

Run: `php artisan test tests/Feature/Keuangan/DashboardAuthorizationTest.php`
Expected: PASS (2 passed) — no production code changes needed if Tasks 4/5 were implemented correctly; a failure here means the `withoutGlobalScope` + relation-membership pattern in `ResolveActiveSiswa` or `SkipAlertResolver` was implemented incorrectly and must be fixed before continuing.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Keuangan/DashboardAuthorizationTest.php
git commit -m "test(keuangan): add cross-tenant regression tests for orang tua dashboard scoping"
```

---

### Task 8: Full end-to-end Playwright walkthrough + whole-task test suite run

**Files:**
- Modify: `scripts/keuangan-6a-browser-check.mjs`

**Interfaces:**
- Consumes: everything from Tasks 1-7. This is the final verification task for the 6a sub-plan — no new production code.

- [ ] **Step 1: Add a full-dashboard interactive check**

Add to `scripts/keuangan-6a-browser-check.mjs`, after `checkSwitcher`:

```javascript
async function checkDashboard(page) {
  await page.goto(`${BASE_URL}/keuangan`);
  const walletCard = page.locator('text=Saldo Wallet');
  await walletCard.waitFor({ state: 'visible', timeout: 3000 });
  const topupButton = page.locator('button:has-text("+ Top Up")');
  await topupButton.waitFor({ state: 'visible', timeout: 3000 });
  console.log('[dashboard] wallet card and top-up button rendered: OK');
}
```

Extend the dispatch block:

```javascript
  if (checkArg === 'all' || checkArg === 'dashboard') {
    await checkDashboard(page);
  }
```

- [ ] **Step 2: Run the full interactive walkthrough**

Ensure the local dev server and MySQL (Laragon) are running, and the demo account (`ortu.demo@permatakraksaan.sch.id`) has at least one linked child (verified already in Task 5 Step 9).

Run: `node scripts/keuangan-6a-browser-check.mjs --check=all`
Expected output (three lines, one per check):
```
[bell] dropdown opened and panel visible: OK
[switcher] demo account has 1 child (or none) — dropdown correctly hidden: OK
[dashboard] wallet card and top-up button rendered: OK
```
(the switcher line may instead read "dropdown opened, option clicked, page reloaded: OK" if the seeded demo account has more than one child — both are passing outcomes, see Task 6).

If any check times out or throws, fix the underlying view/Alpine issue before proceeding — do not defer.

- [ ] **Step 3: Run the full Keuangan 6a test surface together**

Run: `php artisan test tests/Feature/Keuangan/`
Expected: all tests from Tasks 1-7 pass together (no order-dependent failures, no leftover state from `RefreshDatabase` misuse). Record the exact pass count in the handoff log (Task 9 of the whole-plan review, if this plan follows the same subagent-driven-development + final review pattern as Sub-project 05).

- [ ] **Step 4: Run the project's full test suite as a regression gate**

Run: `php artisan test`
Expected: the same pre-existing baseline failure count established in Sub-project 05's handoff log (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest` — 6 total, unrelated to Keuangan), plus zero new failures from this plan's changes to the shared `topbar.blade.php` and `sidebar.blade.php` (both touched by every authenticated page in the app — a regression there would show up broadly, not just in `tests/Feature/Keuangan/`).

- [ ] **Step 5: Commit**

```bash
git add scripts/keuangan-6a-browser-check.mjs
git commit -m "test(keuangan): add full dashboard Playwright check, completing 6a verification"
```
