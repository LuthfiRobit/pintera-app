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
        usleep(200000); // 200ms delay to ensure distinct millisecond timestamps
    }
    foreach (range(1, 6) as $i) {
        $orangTua->notify(new FeedTestNotification("ortu-{$i}"));
        usleep(200000); // 200ms delay to ensure distinct millisecond timestamps
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
