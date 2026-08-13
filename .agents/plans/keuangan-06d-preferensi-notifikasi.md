# Keuangan Sub-project 6d: Preferensi Notifikasi & Mark-as-Read — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make notification channel preferences actually take effect for parent-facing Finance notifications (currently a no-op), add a settings UI for them, add mark-as-read for the notification bell/panel, and relocate `NotificationFeedResolver` out of the `Finance` namespace it's outgrown.

**Architecture:** One targeted bug fix in `NotificationDispatcher::send()` (the dispatcher never resolves preferences for `OrangTua` notifiables, only `User` — every Finance notification is sent to `OrangTua`, so this is currently dead code). One new controller method on the existing `ProfileController` plus a new Blade partial. One new controller (`Keuangan\NotifikasiController`) with two JSON endpoints for mark-as-read, wired into two existing Blade views via Alpine. One pure file move for `NotificationFeedResolver`.

**Tech Stack:** Laravel 12, Eloquent, Blade + Alpine.js, Pest.

## Global Constraints

- This is the **last sub-project of the entire Keuangan module** — after this, Sub-project 1 through 6d are all shipped.
- Guard: `web` only throughout. The new `NotifikasiController` routes live inside the existing `keuangan.*` group (`auth`, `verified`, `permission:keuangan.akses`, `resolve.active.siswa` already applied at the group level). The new `ProfileController` method lives inside the existing `Route::middleware('auth')->group(...)` block.
- **Preferences are per-account (`User`), not per-child (`OrangTua`)** — resolved via `OrangTua->user_id`, no new tables/columns. `UserNotificationPreference` (existing model, unmodified) stays exactly as-is.
- `channel_push` is never exposed in any UI in this plan — it stays `false` forever (no push infrastructure exists in this codebase). Do not add a push toggle anywhere.
- Urgent notifications (`isUrgent() === true`) always get every channel regardless of preference — this is existing, unmodified behavior in `FinanceNotification`/`NotificationDispatcher`. No task in this plan changes it.
- Do **not** modify: `FinanceNotification`, any individual notification class (`PembayaranBerhasilNotification`, `SaldoTidakCukupNotification`, etc.), `NotificationLog`/`NotificationDispatcher::logAttempt()`, `AutoAllocationEngine`, `PaymentService`, `PaymentAllocationService`, or any controller/view from 6a/6b/6c/6c2 other than the two specific Blade files named in Tasks 4 and 5.
- **Exact exception/type discipline — read this before writing any code in this plan.** Every method signature, return type, and exception type given in a task's code block below is exact and must be transcribed verbatim, not approximated. If a task's code throws no exception, do not add one; if it specifies `abort_if($x, 403)`, do not substitute 404 or a different status code. This plan exists specifically to prevent the class of deviation (a specified guard or exact type silently narrowed/dropped) that a prior sub-project's whole-branch review had to catch after the fact.
- **Every task below lists the exact tests it must contain, by name.** Before marking a task's test-writing step complete, count the `it(...)` blocks you wrote against that list. If a task says "4 tests," there must be 4 `it(...)` blocks with matching descriptions (paraphrasing the description text is fine; skipping one of the four listed behaviors is not).
- Testing: no full-suite run anywhere in this plan (explicit standing user decision, made in Sub-project 6c2, still in force — token cost). Every task runs only its own covering tests plus `tests/Feature/Keuangan/`; the final task runs a broader but still scoped regression — never `php artisan test` with no path filter.

---

### Task 1: Fix `NotificationDispatcher` to resolve preferences for `OrangTua` notifiables

**Files:**
- Modify: `app/Services/Finance/NotificationDispatcher.php`
- Test: `tests/Feature/Keuangan/NotificationDispatcherTest.php` (existing file — add to it, do not create a new file)

**Interfaces:**
- No new public interface — `NotificationDispatcher::send(object $notifiable, Notification $notification, string $module = 'finance', array $payload = []): void`'s signature is unchanged. Only its internal preference-resolution logic changes.

This is the single most important fix in this sub-project: every Finance notification in this codebase is sent to an `OrangTua` object (via `$siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()`), never directly to a `User`. The current preference lookup only fires `if ($notifiable instanceof \App\Models\User)` — so for every real notification ever sent by this system, `$preference` is `null` and the `?? true` fallback always wins. The preference toggle (Task 2) will have **zero effect** until this is fixed.

- [ ] **Step 1: Write the failing tests**

Open `tests/Feature/Keuangan/NotificationDispatcherTest.php`. It already has 5 `it(...)` tests and a `TestableFinanceNotification` class at the top — do not touch either. Add these exact 2 new tests at the end of the file (after the last existing `it(...)` block, before the closing of the file):

```php
it('respects an OrangTua notifiable\'s linked User preference (channel_wa=false), not the "not a User" fallback', function () {
    // Desync trick: see the comment on the existing 'logs notification_logs.user_id...'
    // test above for why a standalone User::factory()->create() must come first.
    \App\Models\User::factory()->create();
    $orangTua = OrangTua::factory()->create();
    UserNotificationPreference::create([
        'user_id' => $orangTua->user_id, 'module' => 'finance', 'channel_wa' => false, 'channel_email' => true,
    ]);

    Notification::fake();

    app(NotificationDispatcher::class)->send($orangTua, new TestableFinanceNotification(isUrgent: false));

    Notification::assertSentTo($orangTua, TestableFinanceNotification::class, function ($notification) {
        $channels = $notification->via((object) []);

        return in_array('database', $channels, true)
            && ! in_array('whatsapp', $channels, true)
            && in_array('mail', $channels, true);
    });
});

it('defaults an OrangTua notifiable to WA+Email ON when no preference row exists for their linked User', function () {
    \App\Models\User::factory()->create();
    $orangTua = OrangTua::factory()->create();

    Notification::fake();

    app(NotificationDispatcher::class)->send($orangTua, new TestableFinanceNotification(isUrgent: false));

    Notification::assertSentTo($orangTua, TestableFinanceNotification::class, function ($notification) {
        $channels = $notification->via((object) []);

        return in_array('database', $channels, true) && in_array('mail', $channels, true) && in_array('whatsapp', $channels, true);
    });
});
```

This task's test file must contain **7 total `it(...)` blocks** when you're done: the 5 pre-existing ones, unmodified, plus these 2 new ones.

- [ ] **Step 2: Run tests to verify the 2 new ones fail**

Run: `php artisan test tests/Feature/Keuangan/NotificationDispatcherTest.php`
Expected: 5 pass (pre-existing), 2 FAIL — the "respects...channel_wa=false" test fails because `whatsapp` IS present in the sent channels (preference was never looked up for `OrangTua`); the "defaults...WA+Email ON" test may coincidentally pass already (since the no-preference-row default is `true` either way) — that's fine, it's there as a permanent regression guard for the fix, not required to fail pre-fix.

- [ ] **Step 3: Fix `NotificationDispatcher::send()`**

Open `app/Services/Finance/NotificationDispatcher.php`. Add this import to the `use` block at the top of the file:

```php
use App\Models\OrangTua;
```

Replace this exact block:

```php
        $preference = $notifiable instanceof \App\Models\User
            ? UserNotificationPreference::where('user_id', $notifiable->id)->where('module', $module)->first()
            : null;
```

With this exact block:

```php
        $userId = match (true) {
            $notifiable instanceof \App\Models\User => $notifiable->id,
            $notifiable instanceof OrangTua => $notifiable->user_id,
            default => null,
        };

        $preference = $userId !== null
            ? UserNotificationPreference::where('user_id', $userId)->where('module', $module)->first()
            : null;
```

Do not touch anything else in this method or in `logAttempt()` — both are correct already and are not part of this fix.

- [ ] **Step 4: Run tests to verify all 7 pass**

Run: `php artisan test tests/Feature/Keuangan/NotificationDispatcherTest.php`
Expected: PASS (7 tests). All 5 pre-existing tests must still pass with **zero assertion changes** — if any of them now fails, you have broken existing `User`-notifiable behavior; stop and re-check Step 3's edit before proceeding, do not modify a pre-existing test's assertions to make it pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/NotificationDispatcher.php tests/Feature/Keuangan/NotificationDispatcherTest.php
git commit -m "fix(keuangan): resolve notification preferences for OrangTua notifiables, not just User"
```

---

### Task 2: Notification preference settings on the `/profile` page

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Http/Controllers/ProfileController.php`
- Modify: `routes/web.php`
- Create: `resources/views/profile/partials/update-notification-preference-form.blade.php`
- Modify: `resources/views/profile/edit.blade.php`
- Test: `tests/Feature/ProfileNotificationPreferenceTest.php`

**Interfaces:**
- Consumes: `UserNotificationPreference` (existing model, unmodified — `$fillable = ['user_id', 'module', 'channel_push', 'channel_wa', 'channel_email']`, defaults `channel_wa=true`, `channel_email=true`, `channel_push=false`, `module='finance'`).
- Produces: `User::notificationPreference(): HasOne` (new relation method), route `profile.notification-preference.update` (`PATCH /profile/notification-preference`), controller method `ProfileController::updateNotificationPreference(Request $request): RedirectResponse`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/ProfileNotificationPreferenceTest.php`:

```php
<?php

use App\Models\OrangTua;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatOrangTuaUntukProfilNotifikasi(): array
{
    $user = User::factory()->create(['lembaga_id' => null]);
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Profil Notifikasi',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005555',
    ]);

    return [$user, $orangTua];
}

it('creates a new preference row on first save', function () {
    [$user] = buatOrangTuaUntukProfilNotifikasi();

    $response = $this->actingAs($user)->patch(route('profile.notification-preference.update'), [
        'channel_wa' => '1',
        'channel_email' => '1',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $preference = UserNotificationPreference::where('user_id', $user->id)->where('module', 'finance')->first();
    expect($preference)->not->toBeNull();
    expect($preference->channel_wa)->toBeTrue();
    expect($preference->channel_email)->toBeTrue();
});

it('stores an unchecked checkbox as false, not as omitted', function () {
    [$user] = buatOrangTuaUntukProfilNotifikasi();

    $response = $this->actingAs($user)->patch(route('profile.notification-preference.update'), [
        'channel_email' => '1',
        // channel_wa intentionally omitted, simulating an unchecked HTML checkbox
    ]);

    $response->assertRedirect();
    $preference = UserNotificationPreference::where('user_id', $user->id)->where('module', 'finance')->first();
    expect($preference->channel_wa)->toBeFalse();
    expect($preference->channel_email)->toBeTrue();
});

it('updates an existing preference row on a second save rather than creating a duplicate', function () {
    [$user] = buatOrangTuaUntukProfilNotifikasi();
    UserNotificationPreference::create(['user_id' => $user->id, 'module' => 'finance', 'channel_wa' => true, 'channel_email' => true]);

    $this->actingAs($user)->patch(route('profile.notification-preference.update'), ['channel_wa' => '0', 'channel_email' => '1']);

    expect(UserNotificationPreference::where('user_id', $user->id)->where('module', 'finance')->count())->toBe(1);
    $preference = UserNotificationPreference::where('user_id', $user->id)->where('module', 'finance')->first();
    expect($preference->channel_wa)->toBeFalse();
});

it('shows the notification preference section on /profile only for a user with a linked OrangTua', function () {
    [$userWithOrangTua] = buatOrangTuaUntukProfilNotifikasi();
    $userWithoutOrangTua = User::factory()->create();

    $this->actingAs($userWithOrangTua)->get(route('profile.edit'))->assertSee('Preferensi Notifikasi');
    $this->actingAs($userWithoutOrangTua)->get(route('profile.edit'))->assertDontSee('Preferensi Notifikasi');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ProfileNotificationPreferenceTest.php`
Expected: FAIL — route `profile.notification-preference.update` not defined, and `Preferensi Notifikasi` does not appear on `/profile`.

- [ ] **Step 3: Add the `notificationPreference()` relation to `User`**

In `app/Models/User.php`, add this method right after the existing `karyawan()` method (before `widestScopeLevel()`):

```php
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class)->where('module', 'finance');
    }
```

Add `use App\Models\UserNotificationPreference;` to the `use` block at the top of the file (the `HasOne` import already exists in this file — do not add a duplicate).

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the existing `Route::middleware('auth')->group(function () { ... });` block (the one currently containing `profile.edit`/`profile.update`/`profile.destroy`), add:

```php
    Route::patch('/profile/notification-preference', [ProfileController::class, 'updateNotificationPreference'])->name('profile.notification-preference.update');
```

- [ ] **Step 5: Add the controller method**

In `app/Http/Controllers/ProfileController.php`, add `use App\Models\UserNotificationPreference;` to the `use` block at the top. Add this method (place it after `update()`, before `destroy()`):

```php
    /**
     * Update the user's Finance notification channel preferences.
     */
    public function updateNotificationPreference(Request $request): RedirectResponse
    {
        $request->validate([
            'channel_wa' => ['sometimes', 'boolean'],
            'channel_email' => ['sometimes', 'boolean'],
        ]);

        UserNotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => 'finance'],
            [
                'channel_wa' => $request->boolean('channel_wa'),
                'channel_email' => $request->boolean('channel_email'),
            ]
        );

        return Redirect::route('profile.edit')->with('status', 'notification-preference-updated');
    }
```

- [ ] **Step 6: Create the Blade partial**

Create `resources/views/profile/partials/update-notification-preference-form.blade.php`:

```blade
<section>
    <header>
        <h2 class="font-display text-lg font-semibold text-ink">
            {{ __('Preferensi Notifikasi') }}
        </h2>

        <p class="mt-1 text-sm text-slate">
            {{ __('Atur channel notifikasi Keuangan yang ingin Anda terima. Notifikasi mendesak tetap dikirim lewat semua channel apa pun pengaturan ini.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.notification-preference.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('patch')

        <label class="flex items-center gap-3">
            <input type="checkbox" name="channel_wa" value="1" @checked(old('channel_wa', $preference->channel_wa ?? true)) class="rounded border-slate-300 text-ink focus:ring-ink">
            <span class="text-sm text-ink">{{ __('WhatsApp') }}</span>
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="channel_email" value="1" @checked(old('channel_email', $preference->channel_email ?? true)) class="rounded border-slate-300 text-ink focus:ring-ink">
            <span class="text-sm text-ink">{{ __('Email') }}</span>
        </label>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Simpan Preferensi') }}</x-primary-button>

            @if (session('status') === 'notification-preference-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate"
                >{{ __('Tersimpan.') }}</p>
            @endif
        </div>
    </form>
</section>
```

- [ ] **Step 7: Include the partial in `profile/edit.blade.php`**

Replace:

```blade
    <div class="mx-auto max-w-2xl space-y-6">
        <x-panel class="p-6">
            @include('profile.partials.update-profile-information-form')
        </x-panel>

        <x-panel class="p-6">
            @include('profile.partials.update-password-form')
        </x-panel>

        <x-panel class="p-6">
            @include('profile.partials.delete-user-form')
        </x-panel>
    </div>
```

With:

```blade
    <div class="mx-auto max-w-2xl space-y-6">
        <x-panel class="p-6">
            @include('profile.partials.update-profile-information-form')
        </x-panel>

        @if (Auth::user()->orangTua !== null)
            <x-panel class="p-6">
                @include('profile.partials.update-notification-preference-form', ['preference' => Auth::user()->notificationPreference])
            </x-panel>
        @endif

        <x-panel class="p-6">
            @include('profile.partials.update-password-form')
        </x-panel>

        <x-panel class="p-6">
            @include('profile.partials.delete-user-form')
        </x-panel>
    </div>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ProfileNotificationPreferenceTest.php`
Expected: PASS (4 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Models/User.php app/Http/Controllers/ProfileController.php routes/web.php resources/views/profile/partials/update-notification-preference-form.blade.php resources/views/profile/edit.blade.php tests/Feature/ProfileNotificationPreferenceTest.php
git commit -m "feat(profile): add notification channel preference settings for Finance notifications"
```

---

### Task 3: Move `NotificationFeedResolver` out of the `Finance` namespace

**Files:**
- Create: `app/Services/Notifications/NotificationFeedResolver.php`
- Delete: `app/Services/Finance/NotificationFeedResolver.php`
- Modify: `resources/views/layouts/topbar.blade.php`
- Modify: `app/Http/Controllers/Keuangan/DashboardController.php`
- Modify: `tests/Feature/Keuangan/NotificationFeedResolverTest.php`

**Interfaces:**
- Produces: `App\Services\Notifications\NotificationFeedResolver` with the exact same public API as before: `resolve(User $user): Collection`. This is a pure move — every line of logic inside the class is byte-for-byte identical, only the `namespace` declaration changes.

This is a zero-behavior-change refactor. Do not "improve" anything else in this file while moving it.

- [ ] **Step 1: Create the file in its new location**

Create `app/Services/Notifications/NotificationFeedResolver.php` with this exact content:

```php
<?php
// app/Services/Notifications/NotificationFeedResolver.php

namespace App\Services\Notifications;

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

- [ ] **Step 2: Delete the old file**

```bash
git rm app/Services/Finance/NotificationFeedResolver.php
```

- [ ] **Step 3: Update `topbar.blade.php`'s reference**

In `resources/views/layouts/topbar.blade.php`, find this line near the top of the `@php` block:

```php
    $notificationFeed = app(\App\Services\Finance\NotificationFeedResolver::class)->resolve(Auth::user());
```

Replace it with:

```php
    $notificationFeed = app(\App\Services\Notifications\NotificationFeedResolver::class)->resolve(Auth::user());
```

- [ ] **Step 4: Update `DashboardController.php`'s import**

In `app/Http/Controllers/Keuangan/DashboardController.php`, replace:

```php
use App\Services\Finance\NotificationFeedResolver;
```

With:

```php
use App\Services\Notifications\NotificationFeedResolver;
```

Do not change anything else in this file — the constructor parameter type-hint (`NotificationFeedResolver $notificationFeedResolver`) resolves correctly automatically since it's the same short class name, just a different `use` import.

- [ ] **Step 5: Update the test file's import**

In `tests/Feature/Keuangan/NotificationFeedResolverTest.php`, replace:

```php
use App\Services\Finance\NotificationFeedResolver;
```

With:

```php
use App\Services\Notifications\NotificationFeedResolver;
```

Keep the test file at its current path (`tests/Feature/Keuangan/NotificationFeedResolverTest.php`) — do not move the test file itself, only update the `use` statement inside it.

- [ ] **Step 6: Verify no stale references remain**

Run this search and confirm it returns zero matches before proceeding:

```bash
grep -rn 'Services\\Finance\\NotificationFeedResolver' --include=*.php .
```

Expected: no output (empty result). If anything matches, find and fix it before continuing — a stale reference here means a fatal "class not found" error at runtime.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/NotificationFeedResolverTest.php`
Expected: PASS (4 tests) — the exact same 4 tests, with the exact same assertions, as before this task. If any assertion needed to change to make this pass, you introduced a behavior change; stop and re-check Steps 1-5.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Notifications/NotificationFeedResolver.php app/Services/Finance/NotificationFeedResolver.php resources/views/layouts/topbar.blade.php app/Http/Controllers/Keuangan/DashboardController.php tests/Feature/Keuangan/NotificationFeedResolverTest.php
git commit -m "refactor(notifications): move NotificationFeedResolver out of the Finance namespace"
```

---

### Task 4: Mark-as-read backend + wire into the topbar bell

**Files:**
- Create: `app/Http/Controllers/Keuangan/NotifikasiController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/topbar.blade.php`
- Test: `tests/Feature/Keuangan/NotifikasiMarkAsReadTest.php`

**Interfaces:**
- Consumes: `$user->notifications()`, `$user->orangTua`, `$user->unreadNotifications` (all from Laravel's built-in `Notifiable` trait, already used by `User` and `OrangTua`), `DatabaseNotification::markAsRead()` (Laravel built-in).
- Produces: routes `keuangan.notifikasi.baca` (`POST /keuangan/notifikasi/{id}/baca`), `keuangan.notifikasi.baca-semua` (`POST /keuangan/notifikasi/baca-semua`). Both return JSON `{"unread_count": <int>}`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Keuangan/NotifikasiMarkAsReadTest.php`:

```php
<?php

use App\Models\OrangTua;
use App\Models\User;
use App\Notifications\Finance\FinanceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

class MarkAsReadTestNotification extends FinanceNotification
{
    public function __construct(private readonly string $label) {}

    public function isUrgent(): bool { return false; }

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array { return ['message' => $this->label]; }

    public function toMail(object $notifiable): MailMessage { return (new MailMessage())->line('test'); }

    public function toWhatsApp(object $notifiable): ?string { return null; }
}

function buatUserDenganOrangTuaUntukMarkAsRead(): array
{
    $user = User::factory()->create(['lembaga_id' => null]);
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Mark As Read',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200006666',
    ]);

    return [$user, $orangTua];
}

it('marks a single notification sent directly to the user as read', function () {
    [$user] = buatUserDenganOrangTuaUntukMarkAsRead();
    $user->notify(new MarkAsReadTestNotification('satu'));
    $notification = $user->notifications()->first();

    $response = $this->actingAs($user)->postJson(route('keuangan.notifikasi.baca', $notification->id));

    $response->assertOk();
    $response->assertJson(['unread_count' => 0]);
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks a single notification sent to the linked OrangTua as read', function () {
    [$user, $orangTua] = buatUserDenganOrangTuaUntukMarkAsRead();
    $orangTua->notify(new MarkAsReadTestNotification('ortu'));
    $notification = $orangTua->notifications()->first();

    $response = $this->actingAs($user)->postJson(route('keuangan.notifikasi.baca', $notification->id));

    $response->assertOk();
    $response->assertJson(['unread_count' => 0]);
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications (user and orangTua) as read at once', function () {
    [$user, $orangTua] = buatUserDenganOrangTuaUntukMarkAsRead();
    $user->notify(new MarkAsReadTestNotification('satu'));
    $orangTua->notify(new MarkAsReadTestNotification('dua'));

    $response = $this->actingAs($user)->postJson(route('keuangan.notifikasi.baca-semua'));

    $response->assertOk();
    $response->assertJson(['unread_count' => 0]);
    expect($user->notifications()->first()->fresh()->read_at)->not->toBeNull();
    expect($orangTua->notifications()->first()->fresh()->read_at)->not->toBeNull();
});

it('returns the correct remaining unread_count after marking one of several as read', function () {
    [$user] = buatUserDenganOrangTuaUntukMarkAsRead();
    $user->notify(new MarkAsReadTestNotification('satu'));
    $user->notify(new MarkAsReadTestNotification('dua'));
    $target = $user->notifications()->first();

    $response = $this->actingAs($user)->postJson(route('keuangan.notifikasi.baca', $target->id));

    $response->assertOk();
    $response->assertJson(['unread_count' => 1]);
});

it('blocks marking another user\'s notification as read with a 403', function () {
    [$userA] = buatUserDenganOrangTuaUntukMarkAsRead();
    [$userB] = buatUserDenganOrangTuaUntukMarkAsRead();
    $userB->notify(new MarkAsReadTestNotification('milik-b'));
    $notificationB = $userB->notifications()->first();

    $response = $this->actingAs($userA)->postJson(route('keuangan.notifikasi.baca', $notificationB->id));

    $response->assertForbidden();
    expect($notificationB->fresh()->read_at)->toBeNull();
});

it('returns a 404-safe response (not a crash) when marking a nonexistent notification id', function () {
    [$user] = buatUserDenganOrangTuaUntukMarkAsRead();

    $response = $this->actingAs($user)->postJson(route('keuangan.notifikasi.baca', (string) \Illuminate\Support\Str::uuid()));

    $response->assertForbidden();
});
```

This task's test file must contain **6 total `it(...)` blocks**.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/NotifikasiMarkAsReadTest.php`
Expected: FAIL — routes `keuangan.notifikasi.baca`/`keuangan.notifikasi.baca-semua` not defined.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, extend the existing `keuangan.*` group (add after the last existing `checkout.*` route line, still inside the same `->group(function () { ... });` closure):

```php
        Route::post('/notifikasi/{id}/baca', [\App\Http\Controllers\Keuangan\NotifikasiController::class, 'bacaSatu'])->name('notifikasi.baca');
        Route::post('/notifikasi/baca-semua', [\App\Http\Controllers\Keuangan\NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Keuangan/NotifikasiController.php`:

```php
<?php
// app/Http/Controllers/Keuangan/NotifikasiController.php

namespace App\Http\Controllers\Keuangan;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class NotifikasiController extends BaseController
{
    public function bacaSatu(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->find($id)
            ?? $user->orangTua?->notifications()->find($id);

        abort_if($notification === null, 403);

        $notification->markAsRead();

        return response()->json(['unread_count' => $this->hitungUnread($user)]);
    }

    public function bacaSemua(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->unreadNotifications->markAsRead();
        $user->orangTua?->unreadNotifications->markAsRead();

        return response()->json(['unread_count' => 0]);
    }

    private function hitungUnread(User $user): int
    {
        return $user->unreadNotifications()->count() + ($user->orangTua?->unreadNotifications()->count() ?? 0);
    }
}
```

The authorization here is the query scope itself: `bacaSatu()` only ever finds a notification inside `$user->notifications()` or `$user->orangTua->notifications()` — a UUID belonging to another user's notification, or a UUID that doesn't exist at all, both resolve to `$notification === null`, which `abort_if` turns into a 403. Do not add a separate ownership check after fetching by raw ID — the scoped query IS the check.

- [ ] **Step 5: Wire mark-as-read into the topbar bell**

In `resources/views/layouts/topbar.blade.php`, replace this exact block:

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

With this exact block:

```blade
        <div x-data="{
            unreadCount: {{ $unreadCount }},
            readIds: [],
            async tandaiSatu(id) {
                if (this.readIds.includes(id)) return;
                this.readIds.push(id);
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                await fetch(`{{ url('/keuangan/notifikasi') }}/${id}/baca`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                });
            },
            async tandaiSemua() {
                this.readIds = @js($notificationFeed->pluck('id')->all());
                this.unreadCount = 0;
                await fetch('{{ route('keuangan.notifikasi.baca-semua') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                });
            }
        }">
            <x-dropdown align="right" width="w-80">
                <x-slot name="trigger">
                    <button type="button" class="relative flex h-[38px] w-[38px] items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-50" aria-label="Notifikasi">
                        <x-icon name="notifications" class="h-4 w-4" />
                        <span x-show="unreadCount > 0" x-text="unreadCount > 9 ? '9+' : unreadCount" class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white" style="display: none;"></span>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 pb-3 pt-1">
                        <p class="font-display text-sm font-bold text-gray-900">Notifikasi</p>
                        <button type="button" x-show="unreadCount > 0" @click="tandaiSemua()" class="text-xs font-semibold text-brand-600 hover:text-brand-700" style="display: none;">Tandai semua terbaca</button>
                    </div>
                    @if ($notificationFeed->isEmpty())
                        <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                            <x-icon name="notifications" class="h-6 w-6 text-gray-300" />
                            <p class="text-sm text-gray-500">Belum ada notifikasi.</p>
                        </div>
                    @else
                        <div class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
                            @foreach ($notificationFeed as $notification)
                                <button
                                    type="button"
                                    @click="tandaiSatu('{{ $notification->id }}')"
                                    :class="readIds.includes('{{ $notification->id }}') ? '' : '{{ $notification->read_at === null ? 'bg-brand-50/40' : '' }}'"
                                    class="w-full px-4 py-3 text-left transition hover:bg-gray-50"
                                >
                                    <p class="text-sm text-gray-800">{{ $notification->data['message'] ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </x-slot>
            </x-dropdown>
        </div>
```

This markup relies on `<meta name="csrf-token" content="{{ csrf_token() }}">` already being present in `resources/views/layouts/app.blade.php` (confirmed present — do not add a duplicate).

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/NotifikasiMarkAsReadTest.php`
Expected: PASS (6 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Keuangan/NotifikasiController.php routes/web.php resources/views/layouts/topbar.blade.php tests/Feature/Keuangan/NotifikasiMarkAsReadTest.php
git commit -m "feat(keuangan): add mark-as-read for notifications, wired into the topbar bell"
```

---

### Task 5: Wire mark-as-read into the dashboard "Notifikasi Terbaru" panel

**Files:**
- Modify: `resources/views/keuangan/dashboard.blade.php`
- Test: `tests/Feature/Keuangan/DashboardNotificationMarkAsReadTest.php`

**Interfaces:**
- Consumes: `keuangan.notifikasi.baca`/`keuangan.notifikasi.baca-semua` routes (Task 4). No new backend — this task is view-only, reusing Task 4's endpoints from a second page.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Keuangan/DashboardNotificationMarkAsReadTest.php`:

```php
<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\Finance\FinanceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

class DashboardMarkAsReadTestNotification extends FinanceNotification
{
    public function isUrgent(): bool { return false; }

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array { return ['message' => 'dashboard-test']; }

    public function toMail(object $notifiable): MailMessage { return (new MailMessage())->line('test'); }

    public function toWhatsApp(object $notifiable): ?string { return null; }
}

it('shows a clickable mark-as-read control on each notification row in the dashboard panel', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Dashboard Notif',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTua->notify(new DashboardMarkAsReadTestNotification());

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('tandaiSatu', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/DashboardNotificationMarkAsReadTest.php`
Expected: FAIL — the dashboard panel's notification rows are plain `<div>`s with no Alpine click handler yet.

- [ ] **Step 3: Wire mark-as-read into the dashboard panel**

In `resources/views/keuangan/dashboard.blade.php`, replace this exact block:

```blade
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
```

With this exact block:

```blade
        <div
            class="rounded-2xl border border-gray-200 bg-white p-6"
            x-data="{
                readIds: [],
                unreadCount: {{ $notificationFeed->whereNull('read_at')->count() }},
                async tandaiSatu(id) {
                    if (this.readIds.includes(id)) return;
                    this.readIds.push(id);
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    await fetch(`{{ url('/keuangan/notifikasi') }}/${id}/baca`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    });
                },
                async tandaiSemua() {
                    this.readIds = @js($notificationFeed->pluck('id')->all());
                    this.unreadCount = 0;
                    await fetch('{{ route('keuangan.notifikasi.baca-semua') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    });
                }
            }"
        >
            <div class="flex items-center justify-between">
                <p class="font-display text-sm font-bold text-gray-900">Notifikasi Terbaru</p>
                <button type="button" x-show="unreadCount > 0" @click="tandaiSemua()" class="text-xs font-semibold text-brand-600 hover:text-brand-700" style="display: none;">Tandai semua terbaca</button>
            </div>
            @if ($notificationFeed->isEmpty())
                <p class="mt-3 text-sm text-gray-500">Belum ada notifikasi.</p>
            @else
                <div class="mt-3 divide-y divide-gray-100">
                    @foreach ($notificationFeed as $notification)
                        <button
                            type="button"
                            @click="tandaiSatu('{{ $notification->id }}')"
                            class="w-full py-3 text-left transition hover:bg-gray-50"
                        >
                            <p class="text-sm text-gray-800">{{ $notification->data['message'] ?? '-' }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/DashboardNotificationMarkAsReadTest.php tests/Feature/Keuangan/DashboardControllerTest.php`
Expected: PASS — the new test plus all pre-existing dashboard tests (confirms no regression to the dashboard's other content).

- [ ] **Step 5: Commit**

```bash
git add resources/views/keuangan/dashboard.blade.php tests/Feature/Keuangan/DashboardNotificationMarkAsReadTest.php
git commit -m "feat(keuangan): wire mark-as-read into the dashboard notification panel"
```

---

### Task 6: Playwright verification + scoped regression gate (no full suite)

**Files:**
- Modify: `scripts/keuangan-6a-browser-check.mjs`

**Interfaces:**
- Consumes: the live dev server (`http://localhost:8000`), demo account `ortu.demo@permatakraksaan.sch.id` / `password`.
- Produces: one new check function appended to the existing script.

- [ ] **Step 1: Prepare a dev-DB fixture with at least one unread notification**

Run against the real dev DB (not the test DB) via tinker:

```bash
php artisan tinker --execute="
\$siswa = \App\Models\Siswa::whereHas('orangTua.user', fn(\$q) => \$q->where('email', 'ortu.demo@permatakraksaan.sch.id'))->first();
\$orangTua = \$siswa->orangTua()->first();
\$orangTua->notify(new class extends \App\Notifications\Finance\FinanceNotification {
    public function isUrgent(): bool { return false; }
    public function via(object \$notifiable): array { return ['database']; }
    public function toDatabase(object \$notifiable): array { return ['message' => 'Notifikasi uji coba mark-as-read']; }
});
echo 'unread notification fixture ready for '.\$orangTua->nama_lengkap.PHP_EOL;
"
```

Expected output: `unread notification fixture ready for <nama orang tua>`

- [ ] **Step 2: Add `checkMarkAsRead()` to the Playwright script**

Read `scripts/keuangan-6a-browser-check.mjs` in full first to copy its exact login/navigation boilerplate and dispatch-block pattern, then append:

```javascript
async function checkMarkAsRead(page) {
  await page.goto(`${BASE_URL}/keuangan`);
  const bellButton = page.locator('button[aria-label="Notifikasi"]');
  await bellButton.waitFor({ state: 'visible', timeout: 3000 });
  await bellButton.click();

  const badge = page.locator('button[aria-label="Notifikasi"] span');
  await badge.waitFor({ state: 'visible', timeout: 3000 });
  const beforeCount = await badge.textContent();

  const firstNotification = page.locator('button:has-text("Notifikasi uji coba mark-as-read")').first();
  await firstNotification.waitFor({ state: 'visible', timeout: 3000 });
  await firstNotification.click();

  await page.waitForTimeout(500); // allow the fetch() call to complete
  const badgeStillVisible = await badge.isVisible().catch(() => false);
  if (badgeStillVisible) {
    const afterCount = await badge.textContent();
    if (afterCount === beforeCount) {
      throw new Error(`Expected unread badge to decrease after marking a notification read, stayed at ${afterCount}`);
    }
  }
  console.log('[mark-as-read] clicking a notification decreases the unread badge: OK');

  await page.goto(`${BASE_URL}/profile`);
  const waCheckbox = page.locator('input[name="channel_wa"]');
  await waCheckbox.waitFor({ state: 'visible', timeout: 3000 });
  await waCheckbox.uncheck();
  await page.locator('button:has-text("Simpan Preferensi")').click();
  await page.waitForURL(/\/profile/, { timeout: 5000 });
  await page.reload();
  await waCheckbox.waitFor({ state: 'visible', timeout: 3000 });
  const isChecked = await waCheckbox.isChecked();
  if (isChecked) {
    throw new Error('Expected channel_wa checkbox to remain unchecked after reload');
  }
  console.log('[mark-as-read] notification preference (WA off) persists after save+reload: OK');
}
```

Add `checkMarkAsRead` to the script's dispatch block under the flag name `mark-as-read`.

- [ ] **Step 3: Run the Playwright check against the live dev server**

Run: `KEUANGAN_CHECK_BASE_URL=http://localhost:8000 node scripts/keuangan-6a-browser-check.mjs --check=mark-as-read`
Expected: both `OK` lines printed, no thrown errors.

- [ ] **Step 4: Run the scoped regression suite (no full suite)**

Run: `php artisan test tests/Feature/Keuangan/ tests/Feature/ProfileNotificationPreferenceTest.php`
Expected: all pass, zero failures. This is the final gate for this plan — per the standing user decision (carried from Sub-project 6c2), do NOT run `php artisan test` with no path filter.

- [ ] **Step 5: Commit**

```bash
git add scripts/keuangan-6a-browser-check.mjs
git commit -m "test(keuangan): add mark-as-read + notification preference Playwright check, completing 6d verification"
```

---

## After all tasks: handoff log

Write `.agents/logs/keuangan-06d-preferensi-notifikasi.md` following the exact structure of `.agents/logs/keuangan-06c2-topup-bundling-verifikasi-admin.md` (status, what was built, task-by-task summary, process notes, final scoped-verification numbers — explicitly note that no full-suite run was performed per standing user decision, not an oversight — explicitly-out-of-scope items). **This is the final handoff log for the entire Keuangan module** — its closing section should note that Sub-project 1 through 6d are now all shipped, and list any items still explicitly deferred (from prior logs: `PaymentAllocationService::allocate()`'s re-call double-counting risk, the missing "Diajukan Oleh" column on the manual-payment admin listing, the `'date'` validation rule's relative-string gap) as a single consolidated "known follow-ups" list for whoever picks up work in this module next.
