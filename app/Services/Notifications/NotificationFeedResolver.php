<?php
// app/Services/Finance/NotificationFeedResolver.php

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
