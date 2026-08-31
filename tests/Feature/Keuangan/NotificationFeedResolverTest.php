<?php

// tests/Feature/Keuangan/NotificationFeedResolverTest.php

use App\Models\OrangTua;
use App\Models\User;
use App\Notifications\Finance\FinanceNotification;
use App\Services\Notifications\NotificationFeedResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

class FeedTestNotification extends FinanceNotification
{
    public function __construct(private readonly string $label) {}

    public function isUrgent(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['message' => $this->label];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line('test');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return null;
    }
}

it('merges notifications sent to the User directly and to their linked OrangTua', function () {
    $user = User::factory()->create(['lembaga_id' => null]);
    $orangTua = OrangTua::factory()->create([
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
    $orangTua = OrangTua::factory()->create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Feed',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200001112',
    ]);

    foreach (range(1, 6) as $i) {
        $user->notify(new FeedTestNotification("user-{$i}"));
        usleep(1100000); // 1.1s delay to ensure distinct second-level timestamps in the database
    }
    foreach (range(1, 6) as $i) {
        $orangTua->notify(new FeedTestNotification("ortu-{$i}"));
        usleep(1100000); // 1.1s delay to ensure distinct second-level timestamps in the database
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

it('returns the 10 newest notifications when a source has more than 10 notifications', function () {
    $user = User::factory()->create(['lembaga_id' => null]);

    // Create 15 notifications from the user alone, with distinct timestamps
    foreach (range(1, 15) as $i) {
        $label = "notification-{$i}";
        $user->notify(new FeedTestNotification($label));
        usleep(1100000); // 1.1s delay to ensure distinct second-level timestamps in the database
    }

    $feed = app(NotificationFeedResolver::class)->resolve($user);

    expect($feed)->toHaveCount(10);

    // The feed should be the 10 newest: notifications 6 through 15 (in descending order)
    $messages = $feed->pluck('data.message')->all();
    expect($messages)->toEqual(['notification-15', 'notification-14', 'notification-13', 'notification-12', 'notification-11', 'notification-10', 'notification-9', 'notification-8', 'notification-7', 'notification-6']);
});
