# Keuangan Sub-project 05: Notifikasi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a finance notification system (WhatsApp/Email/In-App) covering 6 events, plus close a real backend gap left from Sub-project 4 (manual-transfer-as-wallet-topup was designed in the finalized spec but never implemented).

**Architecture:** A central `NotificationDispatcher` service implements the urgent/non-urgent + preference-check logic once; 6 `Notification` classes (namespace `App\Notifications\Finance\`) are hooked into 4 existing services at the exact point each domain event occurs. Two of the six notifications need a currently-missing hook point (manual payment approve/reject), which this plan builds first as its own controller.

**Tech Stack:** Laravel 12, Pest, existing `WhatsAppChannel`/`WhatsAppTemplate` infrastructure, Laravel database notifications (`notifications` table — already exists, no migration needed).

## Global Constraints

- **Spec**: `.agents/specs/keuangan-05-notifikasi.md` (read the full "Addendum" section before starting Tasks 6-9 — it contains the exact, audited design for the two most delicate hooks in this plan).
- This project had a real incident where concurrent `php artisan test` processes corrupted the shared MySQL test database. Only ever run `php artisan test` in the foreground, one command at a time, never in the background, never concurrently with another run.
- **`OrangTua` is directly `Notifiable`** (`use HasFactory, Notifiable;` in `app/Models/OrangTua.php`, with `routeNotificationForMail()`/`routeNotificationForWhatsapp()` already implemented). Every notification in this plan is sent via `$kontakUtama?->notify(new XNotification(...))` where `$kontakUtama` is resolved via `$siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()` — this exact call is already used verbatim in 6 places in this codebase; match it, don't invent a new resolution pattern.
- **`WhatsAppChannel::send()` duck-types on `method_exists($notification, 'toWhatsApp')`** — no interface required. Returning `null` from `toWhatsApp()` silently skips WhatsApp (no error). A missing `WhatsAppTemplate` row (`renderKode()` returns `null` when `kode` isn't found) ALSO silently skips — this is why Task 3 (seeding the 6 new `kode` rows) must land before Tasks 4-6/9-10's notification classes are considered complete, or WhatsApp sends will silently no-op forever with no error surfaced anywhere.
- **The existing `app/Http/Controllers/Admin/PembayaranController.php` and its `pembayaran.verifikasi` route are a SEPARATE, unrelated legacy system** — PPDB/calon-murid payment verification via `Pembayaran::tagihan_id`/`cicilan_id` and `PembayaranService::verifikasiPembayaran()`. Do NOT extend or route through this controller. The new manual-payment approve/reject endpoint in this plan operates on `ManualPaymentRequest` (the Sub-project 4 polymorphic-billing model) and gets its own controller (`ManualPaymentController`) and its own route prefix (`manual-payment/...`), to avoid conflating the two systems.
- **`PaymentAllocationService::allocate()` does not own a transaction boundary** — it's always called from inside an existing `DB::transaction()` at 4 different call sites (`BriWebhookController`, `ReconcilePayments`, `PaymentService::createCashPayment()`, and this plan's new `ManualPaymentController::approve()`). A notification dispatched from inside `allocate()` therefore CANNOT use the "capture + fire after the `DB::transaction()` call returns" pattern already established elsewhere in this codebase (that pattern only works when the method calling it owns the transaction directly) — use `DB::afterCommit(fn () => ...)` instead, which defers the callback until the outermost transaction actually commits and never fires if it rolls back, regardless of how deeply nested the call is. This is the one place in this plan using a different mechanism than the rest — Task 5 explains why.
- Every task that adds a `Notification` class must ALSO add its `kode`/message design to Task 3's seeder in the same PR-equivalent unit of work if the message differs from what Task 3 pre-seeds — but this plan pre-designs all 6 messages in Task 3 upfront, so later tasks just reference the already-seeded `kode`.

---

### Task 1: Migrations — `user_notification_preferences` + `notification_logs`

**Files:**
- Create: `database/migrations/2026_08_12_090000_create_user_notification_preferences_table.php`
- Create: `database/migrations/2026_08_12_090001_create_notification_logs_table.php`
- Create: `app/Models/UserNotificationPreference.php`
- Create: `app/Models/NotificationLog.php`
- Test: `tests/Feature/Keuangan/NotificationPreferenceModelsTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `UserNotificationPreference`, `NotificationLog` models — consumed by Task 2's `NotificationDispatcher`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/NotificationPreferenceModelsTest.php

use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user_notification_preferences row with the correct defaults', function () {
    $user = User::factory()->create();

    $pref = UserNotificationPreference::create([
        'user_id' => $user->id,
        'module' => 'finance',
    ]);

    expect($pref->channel_push)->toBeFalse();
    expect($pref->channel_wa)->toBeTrue();
    expect($pref->channel_email)->toBeTrue();
});

it('enforces unique user_id + module on user_notification_preferences', function () {
    $user = User::factory()->create();
    UserNotificationPreference::create(['user_id' => $user->id, 'module' => 'finance']);

    expect(fn () => UserNotificationPreference::create(['user_id' => $user->id, 'module' => 'finance']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('creates a notification_logs row and casts payload to array', function () {
    $user = User::factory()->create();

    $log = NotificationLog::create([
        'user_id' => $user->id,
        'event_key' => 'App\\Notifications\\Finance\\TagihanDiterbitkanNotification',
        'channel' => 'wa',
        'payload' => ['message' => 'test'],
        'status' => 'sent',
    ]);

    expect($log->payload)->toBe(['message' => 'test']);
    expect($log->status)->toBe('sent');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/NotificationPreferenceModelsTest.php`
Expected: FAIL (tables/models don't exist)

- [ ] **Step 3: Write the migrations**

```php
<?php
// database/migrations/2026_08_12_090000_create_user_notification_preferences_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module')->default('finance');
            $table->boolean('channel_push')->default(false);
            $table->boolean('channel_wa')->default(true);
            $table->boolean('channel_email')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
```

```php
<?php
// database/migrations/2026_08_12_090001_create_notification_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key');
            $table->enum('channel', ['wa', 'email', 'database']);
            $table->json('payload')->nullable();
            $table->enum('status', ['sent', 'failed', 'skipped']);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
```

- [ ] **Step 4: Write the models**

```php
<?php
// app/Models/UserNotificationPreference.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $fillable = ['user_id', 'module', 'channel_push', 'channel_wa', 'channel_email'];

    protected function casts(): array
    {
        return [
            'channel_push' => 'boolean',
            'channel_wa' => 'boolean',
            'channel_email' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

```php
<?php
// app/Models/NotificationLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = ['user_id', 'event_key', 'channel', 'payload', 'status', 'error_message'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/NotificationPreferenceModelsTest.php`
Expected: PASS (3/3)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_12_090000_create_user_notification_preferences_table.php database/migrations/2026_08_12_090001_create_notification_logs_table.php app/Models/UserNotificationPreference.php app/Models/NotificationLog.php tests/Feature/Keuangan/NotificationPreferenceModelsTest.php
git commit -m "feat(keuangan): add user_notification_preferences and notification_logs tables"
```

---

### Task 2: `NotificationDispatcher` service

**Files:**
- Create: `app/Services/Finance/NotificationDispatcher.php`
- Test: `tests/Feature/Keuangan/NotificationDispatcherTest.php`

**Interfaces:**
- Consumes: `UserNotificationPreference`, `NotificationLog` (Task 1).
- Produces: `NotificationDispatcher::send(object $notifiable, \Illuminate\Notifications\Notification $notification, string $module = 'finance'): void` — consumed by every hook task (4-6, 9-10).

**Design per spec's "Logika Pengiriman" section**: the dispatcher does NOT decide channels via `$notification->via()` (that stays Laravel's own mechanism when `->notify()` is eventually called) — instead it decides WHETHER to call `->notify()` at all per channel, by temporarily are-we-sending gating. Since Laravel's `Notification::via()` is called once and returns a fixed channel list, and this spec's preference-check is per-user/per-module rather than per-notification-class, the cleanest implementation point is: `NotificationDispatcher` calls `$notifiable->notify($notification)` ONLY ONCE (letting the notification's own `via()` decide mail/wa/database as today), but wraps this in Laravel's `Notification::fake()`-compatible send guarded by preference — meaning **each notification class's `via()` must itself consult `NotificationDispatcher`'s resolved channel list**, OR (simpler, chosen here) `NotificationDispatcher` computes which channels are ALLOWED, and passes that into the notification via constructor, and the notification's `via()` intersects its own default channel list with the allowed list.

To avoid changing the shape of every one of the 6 notification classes with dispatcher-awareness, this plan uses the simpler, more testable design: `NotificationDispatcher::send()` inspects `$notification->isUrgent()` (a new method every Finance notification class must implement — see Task 4+), resolves the allowed channel set, and calls Laravel's own per-channel notification primitives directly (`Illuminate\Support\Facades\Notification::send($notifiable, $notification)` is still used for `database` always; `Illuminate\Support\Facades\Notification::route('mail', ...)` / the notifiable's own routing is used for conditionally-gated channels) — concretely, this is implemented by mutating which channels `via()` returns, via a `channels()` value object.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Keuangan/NotificationDispatcherTest.php

use App\Models\OrangTua;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\Finance\TestableFinanceNotification;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Facades\Notification;

it('sends to all channels for an urgent notification regardless of preference', function () {
    $user = User::factory()->create();
    UserNotificationPreference::create([
        'user_id' => $user->id, 'module' => 'finance', 'channel_wa' => false, 'channel_email' => false,
    ]);

    Notification::fake();

    app(NotificationDispatcher::class)->send($user, new TestableFinanceNotification(isUrgent: true));

    Notification::assertSentTo($user, TestableFinanceNotification::class, function ($notification) {
        return in_array('database', $notification->via((object) []), true)
            && in_array('mail', $notification->via((object) []), true)
            && in_array('whatsapp', $notification->via((object) []), true);
    });
});

it('respects channel_wa=false preference for a non-urgent notification, but always sends database', function () {
    $user = User::factory()->create();
    UserNotificationPreference::create([
        'user_id' => $user->id, 'module' => 'finance', 'channel_wa' => false, 'channel_email' => true,
    ]);

    Notification::fake();

    app(NotificationDispatcher::class)->send($user, new TestableFinanceNotification(isUrgent: false));

    Notification::assertSentTo($user, TestableFinanceNotification::class, function ($notification) {
        $channels = $notification->via((object) []);

        return in_array('database', $channels, true)
            && ! in_array('whatsapp', $channels, true)
            && in_array('mail', $channels, true);
    });
});

it('defaults to WA+Email ON when no preference row exists for the user/module', function () {
    $user = User::factory()->create();

    Notification::fake();

    app(NotificationDispatcher::class)->send($user, new TestableFinanceNotification(isUrgent: false));

    Notification::assertSentTo($user, TestableFinanceNotification::class, function ($notification) {
        $channels = $notification->via((object) []);

        return in_array('database', $channels, true) && in_array('mail', $channels, true) && in_array('whatsapp', $channels, true);
    });
});

it('logs a notification_logs row per channel attempted', function () {
    $user = User::factory()->create();
    Notification::fake();

    app(NotificationDispatcher::class)->send($user, new TestableFinanceNotification(isUrgent: true));

    expect(\App\Models\NotificationLog::where('user_id', $user->id)->count())->toBeGreaterThan(0);
});
```

Also create a minimal test double notification class (used ONLY by this test file, not shipped in `app/Notifications/Finance/` as a real event):

```php
<?php
// tests/Feature/Keuangan/Fixtures/TestableFinanceNotification.php — actually place directly
// where the test's `use` statement expects it; simplest is a real throwaway class under
// app/Notifications/Finance/ guarded to only be used in tests is NOT appropriate — instead
// define it INLINE in the test file itself as an anonymous-adjacent named class before the
// `it(...)` blocks, since Pest test files can declare plain PHP classes at the top level:
```

Replace the `use App\Notifications\Finance\TestableFinanceNotification;` import at the top of the test file with an inline class definition instead (add this near the top of `tests/Feature/Keuangan/NotificationDispatcherTest.php`, after the `use` statements, before the first `it(...)`):

```php
class TestableFinanceNotification extends \Illuminate\Notifications\Notification
{
    public function __construct(private readonly bool $isUrgent)
    {
    }

    public function isUrgent(): bool
    {
        return $this->isUrgent;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'whatsapp'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['message' => 'test'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage())->line('test');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return 'test';
    }
}
```
(Remove the earlier `use App\Notifications\Finance\TestableFinanceNotification;` line — the class is now defined locally in this file, no import needed.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/NotificationDispatcherTest.php`
Expected: FAIL (`NotificationDispatcher` doesn't exist)

- [ ] **Step 3: Write `app/Services/Finance/NotificationDispatcher.php`**

```php
<?php
// app/Services/Finance/NotificationDispatcher.php

namespace App\Services\Finance;

use App\Models\NotificationLog;
use App\Models\UserNotificationPreference;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public function send(object $notifiable, Notification $notification, string $module = 'finance'): void
    {
        $isUrgent = method_exists($notification, 'isUrgent') && $notification->isUrgent();

        $preference = $notifiable instanceof \App\Models\User
            ? UserNotificationPreference::where('user_id', $notifiable->id)->where('module', $module)->first()
            : null;

        $allowWa = $isUrgent || ($preference?->channel_wa ?? true);
        $allowEmail = $isUrgent || ($preference?->channel_email ?? true);

        $notifiable->notify($notification);

        $this->logAttempt($notifiable, $notification, 'database', 'sent');
        $this->logAttempt($notifiable, $notification, 'wa', $allowWa ? 'sent' : 'skipped');
        $this->logAttempt($notifiable, $notification, 'email', $allowEmail ? 'sent' : 'skipped');
    }

    private function logAttempt(object $notifiable, Notification $notification, string $channel, string $status): void
    {
        $userId = property_exists($notifiable, 'id') ? $notifiable->id : ($notifiable->id ?? null);

        if (! is_int($userId)) {
            return;
        }

        NotificationLog::create([
            'user_id' => $userId,
            'event_key' => get_class($notification),
            'channel' => $channel,
            'status' => $status,
        ]);
    }
}
```

**IMPORTANT — this Step 3 draft has a known design gap the implementer must resolve, not silently work around:** the dispatcher above always calls `$notifiable->notify($notification)` (which triggers whatever the notification's OWN `via()` returns, e.g. `['database', 'mail', 'whatsapp']`), REGARDLESS of `$allowWa`/`$allowEmail` — meaning a user with `channel_wa=false` would still actually receive the WhatsApp message today, only `notification_logs` would (incorrectly) show `status=skipped`. **Fix this before considering the task done**: the notification classes in Tasks 4-6/9-10 must each accept the dispatcher's channel decision. Add a `withAllowedChannels(bool $wa, bool $email): static` fluent setter to a shared base — create `app/Notifications/Finance/FinanceNotification.php` (abstract base class extending `Illuminate\Notifications\Notification`) with:
```php
abstract class FinanceNotification extends \Illuminate\Notifications\Notification
{
    protected bool $allowWa = true;
    protected bool $allowEmail = true;

    public function withAllowedChannels(bool $wa, bool $email): static
    {
        $this->allowWa = $wa;
        $this->allowEmail = $email;

        return $this;
    }

    abstract public function isUrgent(): bool;

    protected function baseChannels(): array
    {
        $channels = ['database'];
        if ($this->allowEmail) { $channels[] = 'mail'; }
        if ($this->allowWa) { $channels[] = 'whatsapp'; }

        return $channels;
    }
}
```
Every Finance notification class (Tasks 4-6, 9-10) extends `FinanceNotification` instead of the raw Laravel `Notification`, and its own `via()` returns `$this->baseChannels()` (instead of a hardcoded list). `NotificationDispatcher::send()` calls `$notification->withAllowedChannels($allowWa, $allowEmail)` BEFORE calling `$notifiable->notify($notification)`. Update the failing tests above accordingly (the inline `TestableFinanceNotification` should extend `App\Notifications\Finance\FinanceNotification` instead of the raw Laravel class, and its `via()` should return `$this->baseChannels()`).

- [ ] **Step 4: Write `app/Notifications/Finance/FinanceNotification.php`** (the abstract base, per the design correction above)

```php
<?php
// app/Notifications/Finance/FinanceNotification.php

namespace App\Notifications\Finance;

use Illuminate\Notifications\Notification;

abstract class FinanceNotification extends Notification
{
    protected bool $allowWa = true;
    protected bool $allowEmail = true;

    public function withAllowedChannels(bool $wa, bool $email): static
    {
        $this->allowWa = $wa;
        $this->allowEmail = $email;

        return $this;
    }

    abstract public function isUrgent(): bool;

    protected function baseChannels(): array
    {
        $channels = ['database'];
        if ($this->allowEmail) {
            $channels[] = 'mail';
        }
        if ($this->allowWa) {
            $channels[] = 'whatsapp';
        }

        return $channels;
    }
}
```

- [ ] **Step 5: Update `NotificationDispatcher::send()`** to call `withAllowedChannels()` before notifying:

```php
    public function send(object $notifiable, Notification $notification, string $module = 'finance'): void
    {
        $isUrgent = method_exists($notification, 'isUrgent') && $notification->isUrgent();

        $preference = $notifiable instanceof \App\Models\User
            ? UserNotificationPreference::where('user_id', $notifiable->id)->where('module', $module)->first()
            : null;

        $allowWa = $isUrgent || ($preference?->channel_wa ?? true);
        $allowEmail = $isUrgent || ($preference?->channel_email ?? true);

        if (method_exists($notification, 'withAllowedChannels')) {
            $notification->withAllowedChannels($allowWa, $allowEmail);
        }

        $notifiable->notify($notification);

        $this->logAttempt($notifiable, $notification, 'database', 'sent');
        $this->logAttempt($notifiable, $notification, 'wa', $allowWa ? 'sent' : 'skipped');
        $this->logAttempt($notifiable, $notification, 'email', $allowEmail ? 'sent' : 'skipped');
    }
```

- [ ] **Step 6: Update the test file's inline `TestableFinanceNotification`** to extend `FinanceNotification` per Step 3's note, then run tests to verify they pass

Run: `php artisan test tests/Feature/Keuangan/NotificationDispatcherTest.php`
Expected: PASS (4/4)

- [ ] **Step 7: Commit**

```bash
git add app/Services/Finance/NotificationDispatcher.php app/Notifications/Finance/FinanceNotification.php tests/Feature/Keuangan/NotificationDispatcherTest.php
git commit -m "feat(keuangan): add NotificationDispatcher and FinanceNotification base with channel-gating"
```

---

### Task 3: Seed the 6 WhatsApp templates

**Files:**
- Modify: `database/seeders/WhatsAppTemplateSeeder.php`
- Test: `tests/Feature/Keuangan/WhatsAppTemplateSeederTest.php`

**Interfaces:**
- Consumes: `WhatsAppTemplate::firstOrCreate()` (existing).
- Produces: 6 `kode` rows, consumed by `toWhatsApp()` in Tasks 4-6/9-10's notification classes.

**`kode` names** (snake_case, following the existing `reminder_sesi_h1`/`consent_diminta` convention, confirmed not colliding with the 2 existing rows):
- `tagihan_baru`
- `pembayaran_berhasil`
- `transfer_manual_disetujui`
- `transfer_manual_ditolak`
- `saldo_tidak_cukup`
- `tagihan_jatuh_tempo`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/WhatsAppTemplateSeederTest.php

use App\Models\WhatsAppTemplate;
use Database\Seeders\WhatsAppTemplateSeeder;

it('seeds all 6 finance whatsapp template kode values', function () {
    (new WhatsAppTemplateSeeder())->run();

    $kodes = ['tagihan_baru', 'pembayaran_berhasil', 'transfer_manual_disetujui', 'transfer_manual_ditolak', 'saldo_tidak_cukup', 'tagihan_jatuh_tempo'];

    foreach ($kodes as $kode) {
        expect(WhatsAppTemplate::where('kode', $kode)->exists())->toBeTrue("kode '{$kode}' should exist");
    }
});

it('renders tagihan_baru with the expected placeholders', function () {
    (new WhatsAppTemplateSeeder())->run();

    $rendered = WhatsAppTemplate::renderKode('tagihan_baru', [
        'jenis_tagihan' => 'SPP Bulanan', 'billing_period' => '2026-09', 'net_amount' => '500000', 'jatuh_tempo' => '10 Sep 2026',
    ]);

    expect($rendered)->toContain('SPP Bulanan');
    expect($rendered)->toContain('500000');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/WhatsAppTemplateSeederTest.php`
Expected: FAIL (kode rows don't exist yet)

- [ ] **Step 3: Read the current `database/seeders/WhatsAppTemplateSeeder.php` first**, then add 6 more `firstOrCreate` calls following its exact existing structure (2 rows already there — `consent_diminta`, `reminder_sesi_h1`). Append inside the same `run()` method, using `{placeholder}` syntax matching `WhatsAppTemplate::renderKode()`'s `strtr()`-based replacement:

```php
        WhatsAppTemplate::firstOrCreate(['kode' => 'tagihan_baru'], [
            'isi_template' => 'Tagihan {jenis_tagihan} periode {billing_period} sebesar Rp{net_amount} telah diterbitkan, jatuh tempo {jatuh_tempo}.',
            'deskripsi' => 'Dikirim saat tagihan baru diterbitkan untuk siswa.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'pembayaran_berhasil'], [
            'isi_template' => 'Pembayaran {tagihan} sebesar Rp{amount} berhasil melalui {metode}.',
            'deskripsi' => 'Dikirim saat pembayaran tagihan berhasil diverifikasi.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'transfer_manual_disetujui'], [
            'isi_template' => 'Bukti transfer pembayaran Anda telah diverifikasi dan disetujui.',
            'deskripsi' => 'Dikirim saat admin menyetujui transfer manual.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'transfer_manual_ditolak'], [
            'isi_template' => 'Bukti transfer pembayaran Anda ditolak: {rejection_reason}. Silakan ajukan ulang.',
            'deskripsi' => 'Dikirim saat admin menolak transfer manual.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'saldo_tidak_cukup'], [
            'isi_template' => 'Saldo wallet Anda tidak mencukupi untuk pembayaran {tagihan}. Kekurangan: Rp{selisih}.',
            'deskripsi' => 'Dikirim saat auto-allocation gagal melunasi tagihan karena saldo kurang.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'tagihan_jatuh_tempo'], [
            'isi_template' => 'Tagihan {jenis_tagihan} akan jatuh tempo pada {jatuh_tempo}. Segera lakukan pembayaran.',
            'deskripsi' => 'Dikirim H-3 dan H-1 sebelum tanggal jatuh tempo tagihan.',
        ]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/WhatsAppTemplateSeederTest.php`
Expected: PASS (2/2)

- [ ] **Step 5: Run the seeder against the real dev database** (this is a seed, not just a test fixture — the 6 rows need to actually exist for WhatsApp sends to work outside tests too):

```bash
php artisan db:seed --class=WhatsAppTemplateSeeder --force
```

- [ ] **Step 6: Commit**

```bash
git add database/seeders/WhatsAppTemplateSeeder.php tests/Feature/Keuangan/WhatsAppTemplateSeederTest.php
git commit -m "feat(keuangan): seed 6 whatsapp templates for finance notifications"
```

---

### Task 4: `TagihanDiterbitkanNotification` + hook in `TagihanBillingGenerator`

**Files:**
- Create: `app/Notifications/Finance/TagihanDiterbitkanNotification.php`
- Modify: `app/Services/TagihanBillingGenerator.php`
- Test: `tests/Feature/Keuangan/TagihanDiterbitkanNotificationTest.php`

**Interfaces:**
- Consumes: `FinanceNotification` (Task 2), `NotificationDispatcher` (Task 2), `tagihan_baru` WhatsApp template (Task 3).
- Produces: nothing new consumed elsewhere.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Keuangan/TagihanDiterbitkanNotificationTest.php

use App\Models\JenisTagihan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Notifications\Finance\TagihanDiterbitkanNotification;
use App\Services\TagihanBillingGenerator;
use Illuminate\Support\Facades\Notification;

it('sends TagihanDiterbitkanNotification to the kontak utama when a tagihan is generated', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 500000, 'mode' => 'otomatis']);

    app(TagihanBillingGenerator::class)->generateForSiswa($siswa, $jenisTagihan, 'manual');

    Notification::assertSentTo($orangTua, TagihanDiterbitkanNotification::class);
});

it('does not send when generateForSiswa returns false because the tagihan already exists', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 500000, 'mode' => 'otomatis']);

    $generator = app(TagihanBillingGenerator::class);
    $generator->generateForSiswa($siswa, $jenisTagihan, 'manual');
    Notification::fake(); // reset the fake so only the SECOND call's sends are captured
    $generator->generateForSiswa($siswa, $jenisTagihan, 'manual');

    Notification::assertNothingSent();
});

it('is not urgent', function () {
    $notification = new TagihanDiterbitkanNotification(\App\Models\Tagihan::factory()->make());
    expect($notification->isUrgent())->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/TagihanDiterbitkanNotificationTest.php`
Expected: FAIL (class + hook don't exist)

- [ ] **Step 3: Write `app/Notifications/Finance/TagihanDiterbitkanNotification.php`**

```php
<?php
// app/Notifications/Finance/TagihanDiterbitkanNotification.php

namespace App\Notifications\Finance;

use App\Models\Tagihan;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class TagihanDiterbitkanNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan)
    {
    }

    public function isUrgent(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tagihan_id' => $this->tagihan->id,
            'message' => "Tagihan {$this->tagihan->jenisTagihan?->nama} periode {$this->tagihan->billing_period} sebesar Rp".number_format((float) $this->tagihan->net_amount, 0, ',', '.')." telah diterbitkan.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Tagihan Baru Diterbitkan')
            ->line("Tagihan {$this->tagihan->jenisTagihan?->nama} periode {$this->tagihan->billing_period} sebesar Rp".number_format((float) $this->tagihan->net_amount, 0, ',', '.')." telah diterbitkan.")
            ->line('Jatuh tempo: '.($this->tagihan->jatuh_tempo?->format('d M Y') ?? '-'));
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('tagihan_baru', [
            'jenis_tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'billing_period' => $this->tagihan->billing_period ?? '',
            'net_amount' => number_format((float) $this->tagihan->net_amount, 0, ',', '.'),
            'jatuh_tempo' => $this->tagihan->jatuh_tempo?->format('d M Y') ?? '-',
        ]);
    }
}
```

- [ ] **Step 4: Hook it into `app/Services/TagihanBillingGenerator.php`**

Add imports at the top:
```php
use App\Models\OrangTua;
use App\Notifications\Finance\TagihanDiterbitkanNotification;
use App\Services\Finance\NotificationDispatcher;
```

Add the dispatcher to the constructor:
```php
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanNominalResolver $nominalResolver,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }
```

Modify `generateForSiswa()` — capture the created tagihan and notify AFTER the `DB::transaction()` call returns (this method already returns the transaction's result directly via `return DB::transaction(...)`, so restructure slightly to capture the tagihan first):

```php
    public function generateForSiswa(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerType): bool
    {
        $createdTagihan = null;

        $result = DB::transaction(function () use ($siswa, $jenisTagihan, $triggerType, &$createdTagihan) {
            $billingPeriod = $jenisTagihan->mode === 'otomatis' ? now()->format('Y-m') : null;

            $exists = Tagihan::where('tagihable_type', Siswa::class)
                ->where('tagihable_id', $siswa->id)
                ->where('jenis_tagihan_id', $jenisTagihan->id)
                ->where('billing_period', $billingPeriod)
                ->where('status', '!=', 'dibatalkan')
                ->exists();

            if ($exists) {
                return false;
            }

            $resolved = $this->nominalResolver->resolve($siswa, $jenisTagihan);
            $netAmount = max(0, $resolved['nominal'] - $resolved['discount_amount']);

            $createdTagihan = Tagihan::create([
                'tagihable_type' => Siswa::class,
                'tagihable_id' => $siswa->id,
                'jenis_tagihan_id' => $jenisTagihan->id,
                'kategori' => $jenisTagihan->kategori,
                'billing_period' => $billingPeriod,
                'source_trigger' => $triggerType,
                'total_tagihan' => $resolved['nominal'],
                'discount_amount' => $resolved['discount_amount'] ?: null,
                'discount_type' => $resolved['discount_type'],
                'net_amount' => $netAmount,
                'jatuh_tempo' => $this->resolveDueDate($jenisTagihan, $billingPeriod),
                'status' => 'belum_bayar',
            ]);

            return true;
        });

        if ($createdTagihan !== null) {
            $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
            if ($kontakUtama !== null) {
                $this->dispatcher->send($kontakUtama, new TagihanDiterbitkanNotification($createdTagihan->load('jenisTagihan')));
            }
        }

        return $result;
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/TagihanDiterbitkanNotificationTest.php`
Expected: PASS (3/3)

- [ ] **Step 6: Run the full `TagihanBillingGenerator` regression suite** — this constructor now has a new required dependency, which could break any test that instantiates it manually instead of via the container:

Run: `php artisan test tests/Feature/Keuangan/TagihanBillingGeneratorTest.php tests/Feature/Keuangan/StudentBillingEventsTest.php tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php tests/Feature/Keuangan/ProsesTagihanCommandTest.php tests/Feature/Keuangan/BillTypeActivatedEventTest.php`
Expected: PASS. If any test manually does `new TagihanBillingGenerator($matcher, $resolver)` (2 args, matching the OLD constructor — check `TagihanBillingGeneratorTest.php`'s `buatGenerator()` helper function specifically, it's known from this plan's research to construct the class manually), add `app(NotificationDispatcher::class)` (or `new NotificationDispatcher()`) as the third argument there. Do not skip this check — a constructor signature change with a manual-construction helper function is a classic silent-breakage risk.

- [ ] **Step 7: Commit**

```bash
git add app/Notifications/Finance/TagihanDiterbitkanNotification.php app/Services/TagihanBillingGenerator.php tests/Feature/Keuangan/TagihanDiterbitkanNotificationTest.php
git commit -m "feat(keuangan): add TagihanDiterbitkanNotification hooked into TagihanBillingGenerator"
```

(If Step 6 required fixing `TagihanBillingGeneratorTest.php`'s helper, include it in this same commit.)

---

### Task 5: `PembayaranBerhasilNotification` + hook in `PaymentAllocationService`

**Files:**
- Create: `app/Notifications/Finance/PembayaranBerhasilNotification.php`
- Modify: `app/Services/Finance/PaymentAllocationService.php`
- Test: `tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php`

**Interfaces:**
- Consumes: `FinanceNotification`, `NotificationDispatcher` (Task 2), `pembayaran_berhasil` template (Task 3).
- Produces: nothing new consumed elsewhere.

**Why `DB::afterCommit()` here specifically** (see Global Constraints): `allocate()` doesn't own its transaction — it's always called from inside one of 4 different callers' own `DB::transaction()`. `DB::afterCommit(fn () => ...)` is Laravel's built-in mechanism for exactly this situation: the callback is deferred until whichever transaction is OUTERMOST actually commits, and is silently discarded if it rolls back. This requires zero changes to any of the 4 callers.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Notifications\Finance\PembayaranBerhasilNotification;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('sends PembayaranBerhasilNotification when a tagihan transitions to lunas', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'cash', 'status' => 'lunas']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    DB::transaction(function () use ($pembayaran) {
        app(PaymentAllocationService::class)->allocate($pembayaran);
    });

    Notification::assertSentTo($orangTua, PembayaranBerhasilNotification::class);
});

it('does not send when the tagihan only becomes sebagian, not lunas', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'cash', 'status' => 'lunas']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 50000]);

    DB::transaction(function () use ($pembayaran) {
        app(PaymentAllocationService::class)->allocate($pembayaran);
    });

    Notification::assertNothingSent();
});

it('does not send twice if allocate() is somehow called again on an already-lunas tagihan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'cash', 'status' => 'lunas']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    DB::transaction(fn () => app(PaymentAllocationService::class)->allocate($pembayaran));
    Notification::fake(); // reset, only capture the SECOND call
    DB::transaction(fn () => app(PaymentAllocationService::class)->allocate($pembayaran));

    Notification::assertNothingSent();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php`
Expected: FAIL

- [ ] **Step 3: Write `app/Notifications/Finance/PembayaranBerhasilNotification.php`**

```php
<?php
// app/Notifications/Finance/PembayaranBerhasilNotification.php

namespace App\Notifications\Finance;

use App\Models\Tagihan;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class PembayaranBerhasilNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan, public string $metode)
    {
    }

    public function isUrgent(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tagihan_id' => $this->tagihan->id,
            'message' => "Pembayaran {$this->tagihan->jenisTagihan?->nama} sebesar Rp".number_format((float) $this->tagihan->net_amount, 0, ',', '.')." berhasil melalui {$this->metode}.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Pembayaran Berhasil')
            ->line("Pembayaran {$this->tagihan->jenisTagihan?->nama} sebesar Rp".number_format((float) $this->tagihan->net_amount, 0, ',', '.')." berhasil melalui {$this->metode}.");
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('pembayaran_berhasil', [
            'tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'amount' => number_format((float) $this->tagihan->net_amount, 0, ',', '.'),
            'metode' => $this->metode,
        ]);
    }
}
```

- [ ] **Step 4: Hook it into `app/Services/Finance/PaymentAllocationService.php`**

Add imports:
```php
use App\Models\Tagihan;
use App\Notifications\Finance\PembayaranBerhasilNotification;
use App\Services\Finance\NotificationDispatcher;
```

Add the dispatcher to the constructor (this class currently has no constructor — add one):
```php
class PaymentAllocationService
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    /**
     * Allocate payment amount to related bills (tagihan) and update their statuses.
     */
    public function allocate(Pembayaran $pembayaran): void
    {
        $pembayaranTagihans = $pembayaran->pembayaranTagihan()->with('tagihan')->get();

        foreach ($pembayaranTagihans as $pt) {
            $tagihan = $pt->tagihan;

            if ($tagihan->status === 'dibatalkan') {
                continue;
            }

            $lockedTagihan = $tagihan->lockForUpdate()->find($tagihan->id);
            if ($lockedTagihan->status === 'dibatalkan') {
                continue;
            }

            $lockedTagihan->paid_amount += $pt->amount_allocated;

            $becameLunas = false;
            if ($lockedTagihan->paid_amount >= $lockedTagihan->net_amount) {
                $becameLunas = $lockedTagihan->status !== 'lunas';
                $lockedTagihan->status = 'lunas';
            } elseif ($lockedTagihan->paid_amount > 0) {
                $lockedTagihan->status = 'sebagian';
            }

            $lockedTagihan->save();

            if ($becameLunas) {
                $tagihanId = $lockedTagihan->id;
                $metode = $pembayaran->metode;

                DB::afterCommit(function () use ($tagihanId, $metode) {
                    $freshTagihan = Tagihan::with(['jenisTagihan', 'tagihable'])->find($tagihanId);
                    if ($freshTagihan === null || $freshTagihan->tagihable_type !== \App\Models\Siswa::class) {
                        return;
                    }

                    $siswa = $freshTagihan->tagihable;
                    $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
                    if ($kontakUtama !== null) {
                        app(NotificationDispatcher::class)->send($kontakUtama, new PembayaranBerhasilNotification($freshTagihan, $metode));
                    }
                });
            }
        }
    }
}
```

Note: `DB::facade` was already imported at the top of this file (`use Illuminate\Support\Facades\DB;`) — confirm, don't duplicate the import.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php`
Expected: PASS (3/3)

- [ ] **Step 6: Run the full regression suite for every existing caller of `PaymentAllocationService`** — the constructor now has a new required dependency:

Run: `php artisan test tests/Feature/Keuangan/`
Expected: PASS, same count as before this task plus this task's 3 new tests. If any test manually constructs `new PaymentAllocationService()` (0 args, matching the OLD constructor) instead of resolving via `app(PaymentAllocationService::class)`, fix it the same way as Task 4 Step 6.

- [ ] **Step 7: Commit**

```bash
git add app/Notifications/Finance/PembayaranBerhasilNotification.php app/Services/Finance/PaymentAllocationService.php tests/Feature/Keuangan/PembayaranBerhasilNotificationTest.php
git commit -m "feat(keuangan): add PembayaranBerhasilNotification hooked into PaymentAllocationService via afterCommit"
```

---

### Task 6: `SaldoTidakCukupNotification` + skip-tracking in `AutoAllocationEngine`

**Files:**
- Create: `app/Notifications/Finance/SaldoTidakCukupNotification.php`
- Modify: `app/Services/Finance/AutoAllocationEngine.php`
- Test: `tests/Feature/Keuangan/SaldoTidakCukupNotificationTest.php`

**Interfaces:**
- Consumes: `FinanceNotification`, `NotificationDispatcher` (Task 2), `saldo_tidak_cukup` template (Task 3).
- Produces: nothing new consumed elsewhere.

**Read `.agents/specs/keuangan-05-notifikasi.md`'s Addendum section A before starting this task — it specifies the EXACT insertion point and reasoning, already audited.** Summary: `$skippedTagihan = $tagihans->whereNotIn('id', collect($allocated)->pluck('tagihan.id'));` computed INSIDE the existing `DB::transaction()` closure (pure in-memory, no new queries/locks), captured via `use (&$skippedTagihan)`, notifications fired AFTER the transaction closure returns. `run()`'s signature and its one caller (`Wallet::topup()`) are NOT changed.

- [ ] **Step 1: Write the failing tests** — this is the explicit skip-tracking test the plan's testing requirement calls for:

```php
<?php
// tests/Feature/Keuangan/SaldoTidakCukupNotificationTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\SystemSetting;
use App\Models\Tagihan;
use App\Models\Wallet;
use App\Notifications\Finance\SaldoTidakCukupNotification;
use Illuminate\Support\Facades\Notification;

it('sends SaldoTidakCukupNotification for a tagihan that gets fully skipped due to insufficient balance', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    SystemSetting::create(['lembaga_id' => $lembaga->id, 'key' => 'auto_debit_enabled', 'value' => 'true']);

    $jenisTagihanTinggi = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'priority_score' => 1]);
    $tagihanMahal = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihanTinggi->id,
        'net_amount' => 500000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $wallet = $siswa->wallet;
    $wallet->topup(100000); // saldo jauh lebih kecil dari tagihan yang tersedia, memicu skip

    Notification::assertSentTo($orangTua, SaldoTidakCukupNotification::class, function ($notification) use ($tagihanMahal) {
        return $notification->tagihan->id === $tagihanMahal->id;
    });
});

it('does not send when the wallet balance fully covers every active tagihan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    SystemSetting::create(['lembaga_id' => $lembaga->id, 'key' => 'auto_debit_enabled', 'value' => 'true']);

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 50000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $wallet = $siswa->wallet;
    $wallet->topup(100000); // lebih dari cukup

    Notification::assertNotSentTo($orangTua, SaldoTidakCukupNotification::class);
});

it('is urgent = false', function () {
    $notification = new SaldoTidakCukupNotification(\App\Models\Tagihan::factory()->make(), 100000.0);
    expect($notification->isUrgent())->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Keuangan/SaldoTidakCukupNotificationTest.php`
Expected: FAIL

- [ ] **Step 3: Write `app/Notifications/Finance/SaldoTidakCukupNotification.php`**

```php
<?php
// app/Notifications/Finance/SaldoTidakCukupNotification.php

namespace App\Notifications\Finance;

use App\Models\Tagihan;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class SaldoTidakCukupNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan, public float $selisih)
    {
    }

    public function isUrgent(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tagihan_id' => $this->tagihan->id,
            'message' => "Saldo wallet tidak mencukupi untuk {$this->tagihan->jenisTagihan?->nama}. Kekurangan: Rp".number_format($this->selisih, 0, ',', '.'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Saldo Tidak Cukup')
            ->line("Saldo wallet Anda tidak mencukupi untuk pembayaran {$this->tagihan->jenisTagihan?->nama}.")
            ->line('Kekurangan: Rp'.number_format($this->selisih, 0, ',', '.'));
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('saldo_tidak_cukup', [
            'tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'selisih' => number_format($this->selisih, 0, ',', '.'),
        ]);
    }
}
```

- [ ] **Step 4: Modify `app/Services/Finance/AutoAllocationEngine.php`**

Add imports:
```php
use App\Notifications\Finance\SaldoTidakCukupNotification;
use App\Services\Finance\NotificationDispatcher;
```

Add the dispatcher to the constructor (currently has none — add one):
```php
class AutoAllocationEngine
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public function run(Wallet $wallet): void
    {
        $skippedTagihan = collect();

        DB::transaction(function () use ($wallet, &$skippedTagihan) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $saldo = $wallet->balance;
            if ($saldo <= 0) {
                return;
            }

            $tagihans = $wallet->siswa->tagihan()
                ->join('jenis_tagihan', 'tagihan.jenis_tagihan_id', '=', 'jenis_tagihan.id')
                ->whereIn('tagihan.status', ['belum_bayar', 'sebagian'])
                ->orderBy('jenis_tagihan.priority_score', 'asc')
                ->orderBy('tagihan.jatuh_tempo', 'asc')
                ->orderBy('tagihan.id', 'asc')
                ->select('tagihan.*')
                ->lockForUpdate()
                ->get();

            if ($tagihans->isEmpty()) {
                return;
            }

            $allocated = [];
            $totalAllocatedAmount = 0;

            foreach ($tagihans as $tagihan) {
                if ($saldo <= 0) {
                    break;
                }

                $sisaTagihan = $tagihan->net_amount - $tagihan->paid_amount;
                $amountToPay = min($saldo, $sisaTagihan);

                if ($amountToPay > 0) {
                    $saldo -= $amountToPay;
                    $totalAllocatedAmount += $amountToPay;

                    $allocated[] = [
                        'tagihan' => $tagihan,
                        'amount' => $amountToPay,
                    ];
                }
            }

            $allocatedIds = collect($allocated)->pluck('tagihan.id');
            $skippedTagihan = $tagihans->whereNotIn('id', $allocatedIds)->values();

            if ($totalAllocatedAmount > 0) {
                $pembayaran = Pembayaran::create([
                    'sumber' => 'admin',
                    'wallet_id' => $wallet->id,
                    'metode' => 'wallet_auto',
                    'is_auto_allocation' => true,
                    'status' => 'lunas',
                    'diverifikasi_pada' => now(),
                    'channel_reference' => 'AUTO-'.strtoupper(Str::random(10)),
                ]);

                $wallet->debitWithinTransaction($totalAllocatedAmount, $pembayaran, 'Auto-allocation pembayaran tagihan');

                foreach ($allocated as $item) {
                    $tagihan = $item['tagihan'];
                    $amount = $item['amount'];

                    PembayaranTagihan::create([
                        'pembayaran_id' => $pembayaran->id,
                        'tagihan_id' => $tagihan->id,
                        'amount_allocated' => $amount,
                    ]);

                    $tagihan->paid_amount += $amount;
                    if ($tagihan->paid_amount >= $tagihan->net_amount) {
                        $tagihan->status = 'lunas';
                    } else {
                        $tagihan->status = 'sebagian';
                    }
                    $tagihan->save();
                }
            }
        });

        if ($skippedTagihan->isNotEmpty()) {
            $siswa = $wallet->siswa;
            $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();

            if ($kontakUtama !== null) {
                foreach ($skippedTagihan as $tagihan) {
                    $selisih = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;
                    $this->dispatcher->send($kontakUtama, new SaldoTidakCukupNotification($tagihan->load('jenisTagihan'), $selisih));
                }
            }
        }
    }
}
```

**Important — do not change anything else in this method beyond what's shown**: the `$totalAllocatedAmount > 0` block, the `debitWithinTransaction()` call, and the `Pembayaran`/`PembayaranTagihan` creation logic are copied UNCHANGED from the existing file — this task only adds the `$skippedTagihan` computation (inside the transaction) and the notification loop (after it). Do not alter the locking/transaction structure that was fixed in the Sub-project 03 audit.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/SaldoTidakCukupNotificationTest.php`
Expected: PASS (3/3)

- [ ] **Step 6: Run the full Wallet/AutoAllocation regression suite** — constructor changed:

Run: `php artisan test tests/Feature/Keuangan/AutoAllocationEngineTest.php tests/Feature/Keuangan/WalletTest.php tests/Feature/Keuangan/WalletDatabaseTest.php`
Expected: PASS. Fix any manual `new AutoAllocationEngine()` construction the same way as prior tasks.

- [ ] **Step 7: Commit**

```bash
git add app/Notifications/Finance/SaldoTidakCukupNotification.php app/Services/Finance/AutoAllocationEngine.php tests/Feature/Keuangan/SaldoTidakCukupNotificationTest.php
git commit -m "feat(keuangan): add SaldoTidakCukupNotification with in-memory skip-tracking in AutoAllocationEngine"
```

---

### Task 7: `PaymentService::createManualTopupPayment()`

**Files:**
- Modify: `app/Services/Finance/PaymentService.php`
- Test: `tests/Feature/Keuangan/PaymentServiceManualTopupTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `PaymentService::createManualTopupPayment(Siswa $siswa, array $data): Pembayaran` — consumed by Task 8's approve endpoint indirectly (Task 8 approves requests THIS method created) and directly by this task's own test (simulating a submission).

**Read `.agents/specs/keuangan-05-notifikasi.md`'s Addendum section B before starting — this closes the confirmed Sub-project 04 implementation gap (manual-transfer-as-topup was designed in the finalized spec but never built). This is a NEW sibling method — do not modify `createManualPayment()` at all.**

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/PaymentServiceManualTopupTest.php

use App\Models\ManualPaymentRequest;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Finance\PaymentService;

it('creates a manual topup payment with topup_status pending and no pembayaran_tagihan rows', function () {
    $siswa = Siswa::factory()->create();
    $user = User::factory()->create();

    $pembayaran = app(PaymentService::class)->createManualTopupPayment($siswa, [
        'amount' => 250000,
        'requested_by' => $user->id,
        'transfer_proof_path' => 'proofs/test.jpg',
        'bank_origin' => 'BCA',
        'transfer_date' => now()->toDateString(),
    ]);

    expect($pembayaran->metode)->toBe('transfer_manual');
    expect($pembayaran->status)->toBe('menunggu_verifikasi');
    expect((float) $pembayaran->amount)->toBe(250000.0);
    expect($pembayaran->topup_status)->toBe('pending');
    expect($pembayaran->siswa_id)->toBe($siswa->id);
    expect($pembayaran->pembayaranTagihan()->count())->toBe(0);

    $manualRequest = ManualPaymentRequest::where('pembayaran_id', $pembayaran->id)->first();
    expect($manualRequest)->not->toBeNull();
    expect($manualRequest->status)->toBe('PENDING');
    expect((float) $manualRequest->amount)->toBe(250000.0);
});

it('does not affect the existing createManualPayment bill-payment path', function () {
    // Regression guard: this new sibling method must not have touched createManualPayment()'s
    // own behavior. Run the EXISTING test file for it as part of this task's verification
    // (see Step 4) rather than duplicating its assertions here.
    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceManualTopupTest.php`
Expected: FAIL (method doesn't exist)

- [ ] **Step 3: Add the method to `app/Services/Finance/PaymentService.php`** — insert as a new method, physically near `createManualPayment()` for discoverability, but do NOT modify `createManualPayment()` itself:

```php
    /**
     * Create a manual transfer request intended as a wallet top-up (not tied to any tagihan).
     */
    public function createManualTopupPayment(Siswa $siswa, array $data): Pembayaran
    {
        return DB::transaction(function () use ($siswa, $data) {
            $pembayaran = Pembayaran::create([
                'siswa_id' => $siswa->id,
                'metode' => 'transfer_manual',
                'status' => 'menunggu_verifikasi',
                'amount' => $data['amount'],
                'topup_status' => 'pending',
                'channel_reference' => (string) Str::uuid(),
            ]);

            ManualPaymentRequest::create([
                'pembayaran_id' => $pembayaran->id,
                'requested_by' => $data['requested_by'],
                'amount' => $data['amount'],
                'transfer_proof_path' => $data['transfer_proof_path'],
                'bank_origin' => $data['bank_origin'] ?? null,
                'transfer_date' => $data['transfer_date'],
                'status' => 'PENDING',
            ]);

            return $pembayaran;
        });
    }
```

- [ ] **Step 4: Run this task's test, THEN run the existing `createManualPayment()` test file to confirm zero regression**

Run: `php artisan test tests/Feature/Keuangan/PaymentServiceManualTopupTest.php tests/Feature/Keuangan/PaymentServiceTest.php`
Expected: PASS (both files, no failures — `PaymentServiceTest.php`'s existing `createManualPayment` test must be completely unaffected)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/PaymentService.php tests/Feature/Keuangan/PaymentServiceManualTopupTest.php
git commit -m "feat(keuangan): add createManualTopupPayment, closing the sub-project 04 manual-transfer-topup gap"
```

---

### Task 8: `ManualPaymentController` — approve/reject with cross-validation guard

**Files:**
- Create: `app/Http/Controllers/Admin/ManualPaymentController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/ManualPaymentControllerTest.php`

**Interfaces:**
- Consumes: `ManualPaymentRequest`, `PaymentAllocationService::allocate()` (existing), `Wallet::topup()` (existing).
- Produces: `POST admin/manual-payment/{manualPaymentRequest}/approve`, `POST admin/manual-payment/{manualPaymentRequest}/reject` — consumed by Task 9's notification wiring (added in a follow-up task, NOT this one — this task ships the money-handling logic + guard first, without notifications, so it gets its own focused review before notification concerns are layered on).

**Read `.agents/specs/keuangan-05-notifikasi.md`'s Addendum section B in full before starting — every design decision below (permission, the cross-validation guard, the topup-vs-bill branch, why reject never touches `Wallet::topup()`) is already specified there with reasoning.**

**This task's tests are the explicit "guard" and "both paths separately" requirements** — do not write a single combined test for both branches; keep them as clearly separate `it(...)` blocks so a reviewer can independently verify each path's correctness.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Admin/ManualPaymentControllerTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\ManualPaymentRequest;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatAdminKeuanganUntukManualPayment(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

it('denies approve without pembayaran.verifikasi permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest))->assertForbidden();
});

it('approves a BILL-PAYMENT manual request: allocates tagihan inside one transaction, never touches Wallet::topup()', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);
    $walletSaldoAwal = $siswa->wallet->balance;

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertRedirect();
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('APPROVED');
    expect($manualRequest->reviewed_by)->toBe($user->id);

    $tagihan->refresh();
    expect($tagihan->status)->toBe('lunas');
    expect((float) $tagihan->paid_amount)->toBe(100000.0);

    // Wallet::topup() harus SAMA SEKALI tidak terlibat untuk kasus bill-payment.
    expect((float) $siswa->wallet->fresh()->balance)->toBe($walletSaldoAwal);
});

it('approves a TOPUP manual request: calls Wallet::topup() outside the transaction, marks topup_status completed', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $walletSaldoAwal = $siswa->wallet->balance;

    $pembayaran = app(\App\Services\Finance\PaymentService::class)->createManualTopupPayment($siswa, [
        'amount' => 200000, 'requested_by' => $user->id, 'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(),
    ]);
    $manualRequest = ManualPaymentRequest::where('pembayaran_id', $pembayaran->id)->first();

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertRedirect();
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('APPROVED');

    $pembayaran->refresh();
    expect($pembayaran->topup_status)->toBe('completed');

    expect((float) $siswa->wallet->fresh()->balance)->toBe($walletSaldoAwal + 200000.0);
});

it('rejects the guard scenario: topup_status is pending but pembayaran_tagihan rows ALSO exist — must 500, not silently pick a branch', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    // Data yang sengaja dibuat TIDAK KONSISTEN: topup_status='pending' TAPI juga punya pembayaran_tagihan.
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi', 'topup_status' => 'pending']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertStatus(500);
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('PENDING'); // tidak berubah — guard mencegah proses apa pun
});

it('rejects the guard scenario: topup_status is none and there are NO pembayaran_tagihan rows either — must 500, not silently pick a branch', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    // Data tidak konsisten: bukan bill-payment (tidak ada pembayaran_tagihan) DAN bukan topup (topup_status=none).
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $response = $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertStatus(500);
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('PENDING');
});

it('rejects approve/reject on a request that is not PENDING (idempotency guard)', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'lunas', 'topup_status' => 'none']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'APPROVED',
        'reviewed_by' => $user->id, 'reviewed_at' => now(),
    ]);

    $this->actingAs($user)->post(route('admin.manual-payment.approve', $manualRequest))->assertStatus(422);
    $this->actingAs($user)->post(route('admin.manual-payment.reject', $manualRequest), ['rejection_reason' => 'test'])->assertStatus(422);
});

it('rejects a manual payment request: sets status ditolak, requires rejection_reason, never touches wallet', function () {
    [$user, $lembaga] = buatAdminKeuanganUntukManualPayment();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $walletSaldoAwal = $siswa->wallet->balance;
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $user->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $this->actingAs($user)->post(route('admin.manual-payment.reject', $manualRequest), [])->assertSessionHasErrors('rejection_reason');

    $response = $this->actingAs($user)->post(route('admin.manual-payment.reject', $manualRequest), ['rejection_reason' => 'Bukti tidak valid']);
    $response->assertRedirect();

    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('REJECTED');
    expect($manualRequest->rejection_reason)->toBe('Bukti tidak valid');
    $pembayaran->refresh();
    expect($pembayaran->status)->toBe('ditolak');
    expect((float) $siswa->wallet->fresh()->balance)->toBe($walletSaldoAwal);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/ManualPaymentControllerTest.php`
Expected: FAIL (controller/routes don't exist)

- [ ] **Step 3: Add routes to `routes/admin.php`** — add directly after the existing `pembayaran.verifikasi` line (per Global Constraints, this is a SEPARATE route group, not extending `PembayaranController`):

```php
    Route::post('manual-payment/{manualPaymentRequest}/approve', [ManualPaymentController::class, 'approve'])->name('manual-payment.approve');
    Route::post('manual-payment/{manualPaymentRequest}/reject', [ManualPaymentController::class, 'reject'])->name('manual-payment.reject');
```

Add the import near the other `use App\Http\Controllers\Admin\...` lines:
```php
use App\Http\Controllers\Admin\ManualPaymentController;
```

- [ ] **Step 4: Write `app/Http/Controllers/Admin/ManualPaymentController.php`**

```php
<?php
// app/Http/Controllers/Admin/ManualPaymentController.php

namespace App\Http\Controllers\Admin;

use App\Models\ManualPaymentRequest;
use App\Models\Wallet;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManualPaymentController extends BaseController
{
    use AuthorizesRequests;

    public function approve(Request $request, ManualPaymentRequest $manualPaymentRequest): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        if ($manualPaymentRequest->status !== 'PENDING') {
            abort(422, 'Permintaan ini sudah diproses sebelumnya.');
        }

        $pembayaran = $manualPaymentRequest->pembayaran;

        $hasTagihanTargets = $pembayaran->pembayaranTagihan()->exists();
        $isTopup = $pembayaran->topup_status !== 'none';

        if ($hasTagihanTargets && $isTopup) {
            abort(500, 'Data pembayaran tidak konsisten: punya target tagihan sekaligus ditandai topup.');
        }
        if (! $hasTagihanTargets && ! $isTopup) {
            abort(500, 'Data pembayaran tidak konsisten: tidak ada target tagihan maupun penanda topup.');
        }

        DB::transaction(function () use ($manualPaymentRequest, $pembayaran, $request) {
            $manualPaymentRequest->update([
                'status' => 'APPROVED',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $pembayaran->update(['status' => 'lunas']);

            if ($pembayaran->topup_status === 'none') {
                app(PaymentAllocationService::class)->allocate($pembayaran);
            }
        });

        if ($isTopup) {
            $wallet = Wallet::where('siswa_id', $pembayaran->siswa_id)->first();

            if ($wallet !== null) {
                try {
                    $wallet->topup((float) $pembayaran->amount, $pembayaran, 'Topup via transfer manual disetujui');
                    $pembayaran->update(['topup_status' => 'completed']);
                } catch (\Throwable $e) {
                    Log::error('Gagal topup dari manual payment approval: '.$e->getMessage());
                    $pembayaran->update(['topup_status' => 'failed']);
                }
            }
        }

        return redirect()->back()->with('status', 'Transfer manual berhasil disetujui.');
    }

    public function reject(Request $request, ManualPaymentRequest $manualPaymentRequest): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        if ($manualPaymentRequest->status !== 'PENDING') {
            abort(422, 'Permintaan ini sudah diproses sebelumnya.');
        }

        $request->validate(['rejection_reason' => ['required', 'string', 'max:255']]);

        DB::transaction(function () use ($manualPaymentRequest, $request) {
            $manualPaymentRequest->update([
                'status' => 'REJECTED',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            $manualPaymentRequest->pembayaran->update(['status' => 'ditolak']);
        });

        return redirect()->back()->with('status', 'Transfer manual ditolak.');
    }
}
```

Note: no notifications are dispatched in this task — that's Task 9, deliberately kept separate so this task's review can focus entirely on the money-handling correctness (guard, both branches, idempotency) without notification concerns mixed in.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/ManualPaymentControllerTest.php`
Expected: PASS (7/7)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/ManualPaymentController.php routes/admin.php tests/Feature/Admin/ManualPaymentControllerTest.php
git commit -m "feat(keuangan): add ManualPaymentController approve/reject with topup/bill cross-validation guard"
```

---

### Task 9: `TransferManualDisetujuiNotification` + `TransferManualDitolakNotification` — wire into Task 8's controller

**Files:**
- Create: `app/Notifications/Finance/TransferManualDisetujuiNotification.php`
- Create: `app/Notifications/Finance/TransferManualDitolakNotification.php`
- Modify: `app/Http/Controllers/Admin/ManualPaymentController.php`
- Test: `tests/Feature/Admin/ManualPaymentNotificationTest.php`

**Interfaces:**
- Consumes: `FinanceNotification`, `NotificationDispatcher` (Task 2), `transfer_manual_disetujui`/`transfer_manual_ditolak` templates (Task 3), `ManualPaymentController` (Task 8).
- Produces: nothing new consumed elsewhere.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Admin/ManualPaymentNotificationTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\ManualPaymentRequest;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\Finance\TransferManualDisetujuiNotification;
use App\Notifications\Finance\TransferManualDitolakNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('sends TransferManualDisetujuiNotification to the kontak utama on approve', function () {
    Notification::fake();

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('admin_keuangan');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $admin->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $this->actingAs($admin)->post(route('admin.manual-payment.approve', $manualRequest));

    Notification::assertSentTo($orangTua, TransferManualDisetujuiNotification::class);
});

it('sends TransferManualDitolakNotification to the kontak utama on reject, and it is urgent', function () {
    Notification::fake();

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('admin_keuangan');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $admin->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $this->actingAs($admin)->post(route('admin.manual-payment.reject', $manualRequest), ['rejection_reason' => 'Bukti buram']);

    Notification::assertSentTo($orangTua, TransferManualDitolakNotification::class, function ($notification) {
        return $notification->isUrgent() === true && $notification->rejectionReason === 'Bukti buram';
    });
});

it('still redirects successfully (best-effort) when notification dispatch throws, and the underlying approval is unaffected', function () {
    // Simulasikan dispatcher yang gagal total (mis. WhatsApp API down) — approve() TIDAK BOLEH
    // ikut gagal (500) karena transaksi uang (status APPROVED, tagihan lunas) sudah commit
    // SEBELUM notifikasi dicoba kirim. Ini bukti bahwa try/catch di controller benar-benar
    // menyerap exception, bukan cuma asumsi.
    $this->mock(\App\Services\Finance\NotificationDispatcher::class, function ($mock) {
        $mock->shouldReceive('send')->andThrow(new \RuntimeException('Simulated WhatsApp API failure'));
    });

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('admin_keuangan');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'status' => 'menunggu_verifikasi', 'topup_status' => 'none']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => $admin->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.manual-payment.approve', $manualRequest));

    $response->assertRedirect();
    $response->assertSessionHas('status');
    $manualRequest->refresh();
    expect($manualRequest->status)->toBe('APPROVED');
    $tagihan->refresh();
    expect($tagihan->status)->toBe('lunas');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/ManualPaymentNotificationTest.php`
Expected: FAIL (classes + wiring don't exist)

- [ ] **Step 3: Write both notification classes**

```php
<?php
// app/Notifications/Finance/TransferManualDisetujuiNotification.php

namespace App\Notifications\Finance;

use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class TransferManualDisetujuiNotification extends FinanceNotification
{
    public function isUrgent(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return ['message' => 'Bukti transfer pembayaran Anda telah diverifikasi dan disetujui.'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Transfer Manual Disetujui')
            ->line('Bukti transfer pembayaran Anda telah diverifikasi dan disetujui.');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('transfer_manual_disetujui', []);
    }
}
```

```php
<?php
// app/Notifications/Finance/TransferManualDitolakNotification.php

namespace App\Notifications\Finance;

use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class TransferManualDitolakNotification extends FinanceNotification
{
    public function __construct(public string $rejectionReason)
    {
    }

    public function isUrgent(): bool
    {
        return true;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return ['message' => "Bukti transfer pembayaran Anda ditolak: {$this->rejectionReason}. Silakan ajukan ulang."];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Transfer Manual Ditolak')
            ->line("Bukti transfer pembayaran Anda ditolak: {$this->rejectionReason}.")
            ->line('Silakan ajukan ulang.');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('transfer_manual_ditolak', [
            'rejection_reason' => $this->rejectionReason,
        ]);
    }
}
```

- [ ] **Step 4: Wire both into `app/Http/Controllers/Admin/ManualPaymentController.php`**

Add imports:
```php
use App\Notifications\Finance\TransferManualDisetujuiNotification;
use App\Notifications\Finance\TransferManualDitolakNotification;
use App\Services\Finance\NotificationDispatcher;
```

**Notifikasi harus best-effort** — konsisten dengan filosofi try/catch-log yang sudah dipakai di `BriWebhookController`'s wallet-topup-retry block (spec 04). Uang sudah berpindah status (approve/reject sudah commit) SEBELUM notifikasi dikirim; kalau pengiriman notifikasi gagal (mis. WhatsApp API down, mail server timeout), request HTTP tidak boleh ikut gagal (500) padahal transaksi uangnya sendiri sudah sukses — itu akan membingungkan admin (mengira approve gagal, padahal cuma notifikasi yang gagal) dan berisiko approve dicoba ulang secara keliru. Bungkus setiap `$dispatcher->send()` call dengan try/catch, log error, redirect tetap sukses.

In `approve()`, right before the final `return redirect()->back()...` line, add:
```php
        $siswa = $pembayaran->siswa;
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        if ($kontakUtama !== null) {
            try {
                app(NotificationDispatcher::class)->send($kontakUtama, new TransferManualDisetujuiNotification());
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim TransferManualDisetujuiNotification: '.$e->getMessage());
            }
        }
```

In `reject()`, right before its final `return redirect()->back()...` line, add:
```php
        $siswa = $manualPaymentRequest->pembayaran->siswa;
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        if ($kontakUtama !== null) {
            try {
                app(NotificationDispatcher::class)->send($kontakUtama, new TransferManualDitolakNotification($request->rejection_reason));
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim TransferManualDitolakNotification: '.$e->getMessage());
            }
        }
```

`Log` sudah di-import di controller ini sejak Task 8 (dipakai di blok catch topup) — tidak perlu import baru.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/ManualPaymentNotificationTest.php`
Expected: PASS (3/3)

- [ ] **Step 6: Re-run Task 8's full test file to confirm no regression from the added notification wiring**

Run: `php artisan test tests/Feature/Admin/ManualPaymentControllerTest.php`
Expected: PASS (7/7, unchanged)

- [ ] **Step 7: Commit**

```bash
git add app/Notifications/Finance/TransferManualDisetujuiNotification.php app/Notifications/Finance/TransferManualDitolakNotification.php app/Http/Controllers/Admin/ManualPaymentController.php tests/Feature/Admin/ManualPaymentNotificationTest.php
git commit -m "feat(keuangan): wire TransferManualDisetujui/DitolakNotification into ManualPaymentController"
```

---

### Task 10: `DueReminderNotification` + `billing:kirim-due-reminder` scheduled command

**Files:**
- Create: `app/Notifications/Finance/DueReminderNotification.php`
- Create: `app/Console/Commands/KirimDueReminderTagihan.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Console/KirimDueReminderTagihanTest.php`

**Interfaces:**
- Consumes: `FinanceNotification`, `NotificationDispatcher` (Task 2), `tagihan_jatuh_tempo` template (Task 3).
- Produces: nothing new consumed elsewhere.

**Per the spec's resolved ambiguity**: kontak utama saja untuk H-3 MAUPUN H-1 (bukan "semua kontak untuk H-1" seperti draf awal — sudah dikonfirmasi user).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Console/KirimDueReminderTagihanTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NotificationLog;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Notifications\Finance\DueReminderNotification;
use Illuminate\Support\Facades\Notification;

it('sends a non-urgent reminder for a tagihan due in 3 days, to the kontak utama only', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTuaLain = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTuaLain->id, ['hubungan' => 'ibu', 'is_kontak_utama' => false]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('billing:kirim-due-reminder')->assertExitCode(0);

    Notification::assertSentTo($kontakUtama, DueReminderNotification::class, fn ($n) => $n->isUrgent() === false);
    Notification::assertNotSentTo($orangTuaLain, DueReminderNotification::class);
});

it('sends an urgent reminder for a tagihan due tomorrow (H-1)', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDay()->toDateString(),
    ]);

    $this->artisan('billing:kirim-due-reminder')->assertExitCode(0);

    Notification::assertSentTo($kontakUtama, DueReminderNotification::class, fn ($n) => $n->isUrgent() === true);
});

it('does not send a duplicate reminder for the same tagihan on a same-day re-run (idempotency)', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('billing:kirim-due-reminder');
    $this->artisan('billing:kirim-due-reminder'); // re-run same day

    Notification::assertSentToTimes($kontakUtama, DueReminderNotification::class, 1);
});

it('does not send for a tagihan that is already lunas or dibatalkan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'lunas', 'jatuh_tempo' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('billing:kirim-due-reminder');

    Notification::assertNothingSent();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Console/KirimDueReminderTagihanTest.php`
Expected: FAIL

- [ ] **Step 3: Write `app/Notifications/Finance/DueReminderNotification.php`**

```php
<?php
// app/Notifications/Finance/DueReminderNotification.php

namespace App\Notifications\Finance;

use App\Models\Tagihan;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class DueReminderNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan, private readonly bool $urgent)
    {
    }

    public function isUrgent(): bool
    {
        return $this->urgent;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tagihan_id' => $this->tagihan->id,
            'message' => "Tagihan {$this->tagihan->jenisTagihan?->nama} akan jatuh tempo pada ".$this->tagihan->jatuh_tempo?->format('d M Y').'.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Pengingat Jatuh Tempo Tagihan')
            ->line("Tagihan {$this->tagihan->jenisTagihan?->nama} akan jatuh tempo pada ".$this->tagihan->jatuh_tempo?->format('d M Y').'.')
            ->line('Segera lakukan pembayaran.');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('tagihan_jatuh_tempo', [
            'jenis_tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'jatuh_tempo' => $this->tagihan->jatuh_tempo?->format('d M Y') ?? '-',
        ]);
    }
}
```

- [ ] **Step 4: Write `app/Console/Commands/KirimDueReminderTagihan.php`** (following `KirimReminderSesi.php`'s exact structure, idempotency via `notification_logs`):

```php
<?php
// app/Console/Commands/KirimDueReminderTagihan.php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Notifications\Finance\DueReminderNotification;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Console\Command;

class KirimDueReminderTagihan extends Command
{
    protected $signature = 'billing:kirim-due-reminder';

    protected $description = 'Kirim pengingat H-3 dan H-1 sebelum tanggal jatuh_tempo tagihan yang belum lunas';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $h3 = now()->addDays(3)->toDateString();
        $h1 = now()->addDay()->toDateString();

        $tagihans = Tagihan::whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('jatuh_tempo', [$h3, $h1])
            ->with('jenisTagihan')
            ->get();

        $terkirim = 0;

        foreach ($tagihans as $tagihan) {
            if ($tagihan->tagihable_type !== Siswa::class) {
                continue;
            }

            $siswa = $tagihan->tagihable;
            if ($siswa === null) {
                continue;
            }

            $isUrgent = $tagihan->jatuh_tempo->toDateString() === $h1;
            $eventKey = DueReminderNotification::class;

            $sudahDikirimHariIni = NotificationLog::where('event_key', $eventKey)
                ->where('payload->tagihan_id', $tagihan->id)
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($sudahDikirimHariIni) {
                continue;
            }

            $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
            if ($kontakUtama === null) {
                continue;
            }

            $dispatcher->send($kontakUtama, new DueReminderNotification($tagihan, $isUrgent));
            $terkirim++;
        }

        $this->info("Reminder terkirim untuk {$terkirim} tagihan.");

        return self::SUCCESS;
    }
}
```

**Note on the idempotency check**: `NotificationLog` (Task 1) doesn't have a `payload` column with a `tagihan_id` key populated by `NotificationDispatcher::send()` as written in Task 2 — `NotificationDispatcher::logAttempt()` currently creates `NotificationLog` rows WITHOUT a `payload`. Before this idempotency check can work, either (a) extend `NotificationDispatcher::logAttempt()` to accept and store a `$payload` array param, threading `['tagihan_id' => $tagihan->id]` through from the caller, or (b) query `notification_logs` differently (e.g. by `event_key` + `user_id` + date only, accepting the coarser granularity of "this kontak utama already got ANY due reminder today" rather than per-tagihan). **Resolve this explicitly, do not guess** — option (a) is more precise and matches the spec's stated idempotency requirement ("kombinasi `tagihan_id` + `jatuh_tempo`"), so extend `NotificationDispatcher::send()` to accept an optional `array $payload = []` parameter, store it on each `NotificationLog::create()` call in `logAttempt()`, and pass `['tagihan_id' => $tagihan->id]` from this command's dispatch call.

**JSON query driver portability — do not assume, verify:** `where('payload->tagihan_id', $tagihan->id)` relies on Laravel's JSON-path query builder translating `->` into the correct native syntax per database driver. This project's test environment (`.env.testing`, confirmed by reading it directly) is **MySQL** (`DB_CONNECTION=mysql`, database `pintera_app_test`), the SAME driver as production dev (Laragon `pintera_app`) — there is no MySQL/SQLite driver split in this project, unlike some Laravel setups that run tests against SQLite for speed. This means the query has no cross-driver portability gap to worry about here specifically, but the implementer must still not simply assume the syntax is correct — Step 7 below already runs the idempotency test (`does not send a duplicate reminder...`) which exercises this exact query end-to-end (creates a log row, re-runs the command same-day, asserts no duplicate `Notification::assertSentToTimes(..., 1)`); treat that test passing as the actual proof, not the reasoning above. If the implementer discovers ANY query behavior surprise while making this test pass (e.g. `whereDate()` combined with `payload->tagihan_id` needing an explicit cast, or MySQL requiring `->>'$.tagihan_id'` instead of `->` for a scalar string comparison depending on the MySQL version installed), **do not silently work around it and move on** — document the exact workaround and why it was needed directly in this task's commit message AND carry it forward into Task 11's handoff log, the same way the Sub-project 03 `lockForUpdate()` MySQL-specific behavior was explicitly documented rather than assumed portable.

- [ ] **Step 5: Apply the `NotificationDispatcher` extension from the note above**

In `app/Services/Finance/NotificationDispatcher.php`, change `send()`'s signature to:
```php
    public function send(object $notifiable, Notification $notification, string $module = 'finance', array $payload = []): void
```
Thread `$payload` into `logAttempt()`'s `NotificationLog::create([...])` call (add `'payload' => $payload,` to the array). Update this task's `KirimDueReminderTagihan::handle()` dispatch call to:
```php
            $dispatcher->send($kontakUtama, new DueReminderNotification($tagihan, $isUrgent), 'finance', ['tagihan_id' => $tagihan->id]);
```

- [ ] **Step 6: Register the schedule in `routes/console.php`**

Add the import:
```php
use App\Console\Commands\KirimDueReminderTagihan;
```
Add the schedule line (matching the file's existing `Schedule::command(ClassName::class)->dailyAt(...)` pattern):
```php
Schedule::command(KirimDueReminderTagihan::class)->dailyAt('08:00');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Console/KirimDueReminderTagihanTest.php`
Expected: PASS (4/4)

- [ ] **Step 8: Re-run Task 2's dispatcher test file to confirm the signature change didn't break it**

Run: `php artisan test tests/Feature/Keuangan/NotificationDispatcherTest.php`
Expected: PASS (unchanged — `$payload` is optional with a default, existing calls without it still work)

- [ ] **Step 9: Commit**

```bash
git add app/Notifications/Finance/DueReminderNotification.php app/Console/Commands/KirimDueReminderTagihan.php app/Services/Finance/NotificationDispatcher.php routes/console.php tests/Feature/Console/KirimDueReminderTagihanTest.php
git commit -m "feat(keuangan): add DueReminderNotification and billing:kirim-due-reminder scheduled command"
```

---

### Task 11: Full regression verification + handoff log

**Files:** none (verification-only task)

- [ ] **Step 1: Run every test file this plan touched or created**

```bash
php artisan test tests/Feature/Keuangan/ tests/Feature/Admin/ManualPaymentControllerTest.php tests/Feature/Admin/ManualPaymentNotificationTest.php tests/Feature/Console/KirimDueReminderTagihanTest.php
```
Expected: all PASS, no failures.

- [ ] **Step 2: Run the full project suite** (single foreground run, never background, never concurrent with another `php artisan test` process)

```bash
php artisan test
```
Expected: no NEW failures beyond the established pre-existing baseline (6 failures: `LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest` — confirmed unrelated to Keuangan, per `.agents/logs/keuangan-audit-fixes-01-04.md`).

- [ ] **Step 3: Seed the WhatsApp templates on the real dev database if not already done in Task 3**

```bash
php artisan db:seed --class=WhatsAppTemplateSeeder --force
```

- [ ] **Step 4: Write the handoff log**

Write `.agents/logs/keuangan-05-notifikasi.md` covering: all 10 implementation tasks, the 2 spec-clarification decisions made during brainstorming (H-1 reminder scope = kontak utama only; in-app badge UI deferred to Sub-project 6), the manual-transfer-topup gap discovery and closure (with a pointer back to the corrected `.agents/logs/keuangan-04-payment-channels.md` entry), the cross-validation guard and its explicit test coverage, the best-effort notification try/catch decision in `ManualPaymentController` (Task 9) and its dedicated failure-mode test, and current git state.

**Explicitly include a subsection on Task 10's `payload->tagihan_id` JSON-path query**: state plainly whether it worked against MySQL exactly as written, or required a workaround — do not omit this because it "just worked." If it just worked, say so in one sentence with the test name as evidence. If it needed a workaround, document the exact syntax change and why, mirroring how Sub-project 03's `lockForUpdate()` MySQL behavior was documented rather than silently patched. This project's test DB is MySQL, not SQLite (confirmed via `.env.testing`) — say this explicitly so a future reader doesn't assume an untested SQLite path exists.
