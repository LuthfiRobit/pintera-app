<?php

namespace App\Http\Controllers\Keuangan;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class NotifikasiController extends BaseController
{
    public function bacaSatu(Request $request, string $id)
    {
        $user = Auth::user();

        // Cari di notifikasi User
        $notification = $user->notifications()->where('id', $id)->first();

        // Jika tidak ketemu, cari di notifikasi OrangTua yang terhubung
        if (! $notification && $user->orangTua !== null) {
            $notification = $user->orangTua->notifications()->where('id', $id)->first();
        }

        abort_if(! $notification, 403);

        $notification->markAsRead();

        return response()->json(['status' => 'ok']);
    }

    public function bacaSemua(Request $request)
    {
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        if ($user->orangTua !== null) {
            $user->orangTua->unreadNotifications->markAsRead();
        }

        return response()->json(['status' => 'ok']);
    }
}
