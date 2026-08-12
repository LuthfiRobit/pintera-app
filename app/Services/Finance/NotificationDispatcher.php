<?php

namespace App\Services\Finance;

use App\Models\NotificationLog;
use App\Models\UserNotificationPreference;
use Illuminate\Notifications\Notification;

class NotificationDispatcher
{
    public function send(object $notifiable, Notification $notification, string $module = 'finance', array $payload = []): void
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

        $this->logAttempt($notifiable, $notification, 'database', 'sent', $payload);
        $this->logAttempt($notifiable, $notification, 'wa', $allowWa ? 'sent' : 'skipped', $payload);
        $this->logAttempt($notifiable, $notification, 'email', $allowEmail ? 'sent' : 'skipped', $payload);
    }

    private function logAttempt(object $notifiable, Notification $notification, string $channel, string $status, array $payload = []): void
    {
        // notification_logs.user_id is a FK to users.id. For a User notifiable that's
        // ->id directly; for other notifiables (e.g. OrangTua) it's the ->user_id
        // column linking back to their account, not their own primary key.
        $userId = $notifiable instanceof \App\Models\User
            ? $notifiable->id
            : ($notifiable->user_id ?? null);

        if (! is_int($userId)) {
            return;
        }

        NotificationLog::create([
            'user_id' => $userId,
            'event_key' => get_class($notification),
            'channel' => $channel,
            'payload' => $payload,
            'status' => $status,
        ]);
    }
}
