<?php
// tests/Feature/Keuangan/TopbarNotificationBellTest.php

use App\Models\Lembaga;
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
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->notify(new BellTestNotification());

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Tagihan SPP Agustus terbit.');
    $response->assertDontSee('Belum ada notifikasi.');
});

it('still shows the empty state when there are zero notifications', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Belum ada notifikasi.');
});
