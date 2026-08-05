<?php
// app/Notifications/Channels/WhatsAppChannel.php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $notifiable->routeNotificationFor('whatsapp', $notification);

        if ($phone === null || $phone === '') {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if ($message === null) {
            return;
        }

        try {
            $response = Http::withHeaders(['Authorization' => (string) config('services.fonnte.token')])
                ->post('https://api.fonnte.com/send', [
                    'target' => $phone,
                    'message' => $message,
                ]);

            if ($response->failed()) {
                Log::warning('WhatsApp notification failed', [
                    'phone' => $phone,
                    'notification' => get_class($notification),
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp notification exception', [
                'phone' => $phone,
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
