<?php
// app/Http/Controllers/Keuangan/DashboardController.php

namespace App\Http\Controllers\Keuangan;

use App\Services\Finance\NotificationFeedResolver;
use App\Services\Finance\SkipAlertResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function __construct(
        private readonly SkipAlertResolver $skipAlertResolver,
        private readonly NotificationFeedResolver $notificationFeedResolver,
    ) {
    }

    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        $wallet = $activeSiswa->wallet;
        $skipAlert = $this->skipAlertResolver->resolve($activeSiswa);
        $notificationFeed = $this->notificationFeedResolver->resolve($request->user());

        return view('keuangan.dashboard', [
            'activeSiswa' => $activeSiswa,
            'wallet' => $wallet,
            'skipAlert' => $skipAlert,
            'notificationFeed' => $notificationFeed,
        ]);
    }
}
