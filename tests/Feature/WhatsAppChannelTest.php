<?php
// tests/Feature/WhatsAppChannelTest.php

use App\Models\OrangTua;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContohWhatsAppNotification extends Notification
{
    public function __construct(private ?string $pesan)
    {
    }

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return $this->pesan;
    }
}

function buatOrangTuaDenganNoHp(string $noHp = '081234567890'): OrangTua
{
    return OrangTua::create([
        'user_id' => User::factory()->create()->id,
        'nama_lengkap' => 'Ibu Contoh',
        'nik' => fake()->unique()->numerify('################'),
        'no_hp' => $noHp,
        'email' => 'ortu.whatsapp@example.test',
    ]);
}

it('posts to the Fonnte API with the phone number and rendered message', function () {
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);
    config(['services.fonnte.token' => 'test-token']);
    $orangTua = buatOrangTuaDenganNoHp('081234567890');

    $orangTua->notify(new ContohWhatsAppNotification('Halo dari Pintera.'));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.fonnte.com/send'
            && $request['target'] === '081234567890'
            && $request['message'] === 'Halo dari Pintera.'
            && $request->hasHeader('Authorization', 'test-token');
    });
});

it('does not call Fonnte when toWhatsApp returns null', function () {
    Http::fake();
    $orangTua = buatOrangTuaDenganNoHp();

    $orangTua->notify(new ContohWhatsAppNotification(null));

    Http::assertNothingSent();
});

it('logs a warning and does not throw when Fonnte returns a failure response', function () {
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => false], 422)]);
    Log::spy();
    $orangTua = buatOrangTuaDenganNoHp();

    $orangTua->notify(new ContohWhatsAppNotification('Pesan gagal.'));

    Log::shouldHaveReceived('warning')->once();
});

it('logs an error and does not throw when the Fonnte call itself throws', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Gagal terhubung.');
    });
    Log::spy();
    $orangTua = buatOrangTuaDenganNoHp();

    $orangTua->notify(new ContohWhatsAppNotification('Pesan gagal.'));

    Log::shouldHaveReceived('error')->once();
});

it('routes an OrangTua notification to their no_hp column', function () {
    $orangTua = buatOrangTuaDenganNoHp('089900001111');

    expect($orangTua->routeNotificationForWhatsapp())->toBe('089900001111');
});
