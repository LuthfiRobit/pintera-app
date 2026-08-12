<?php

use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\Finance\FinanceNotification;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Facades\Notification;

class TestableFinanceNotification extends FinanceNotification
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
        return $this->baseChannels();
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
