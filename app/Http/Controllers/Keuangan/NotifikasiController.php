<?php

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
