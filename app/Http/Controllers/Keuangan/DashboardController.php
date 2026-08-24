<?php
// app/Http/Controllers/Keuangan/DashboardController.php

namespace App\Http\Controllers\Keuangan;

use App\Exceptions\PaymentException;
use App\Models\Scopes\TenantScope;
use App\Models\SystemSetting;
use App\Domains\Keuangan\Models\Tagihan;
use App\Services\Notifications\NotificationFeedResolver;
use App\Domains\Keuangan\Services\PaymentService;
use App\Domains\Keuangan\Services\SkipAlertResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function __construct(
        private readonly SkipAlertResolver $skipAlertResolver,
        private readonly NotificationFeedResolver $notificationFeedResolver,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        try {
            $this->paymentService->getOrCreatePermanentVa($activeSiswa);
        } catch (PaymentException $e) {
            Log::error('Gagal membuat VA BRI Permanen: '.$e->getMessage());
            // Tidak ada VA untuk disinkronkan -- dashboard tetap dirender, $wallet
            // di bawah kemungkinan null dan view sudah pakai null-safe operator.
        }

        $wallet = $activeSiswa->wallet;
        $skipAlert = $this->skipAlertResolver->resolve($activeSiswa);
        $notificationFeed = $this->notificationFeedResolver->resolve($request->user());

        $tagihans = Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->with(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->orderBy('jatuh_tempo')
            ->get();

        $autoDebitEnabled = (bool) SystemSetting::getResolved('auto_debit_enabled', $activeSiswa->lembaga_id, false);

        return view('keuangan.dashboard', [
            'activeSiswa' => $activeSiswa,
            'wallet' => $wallet,
            'skipAlert' => $skipAlert,
            'notificationFeed' => $notificationFeed,
            'tagihans' => $tagihans,
            'autoDebitEnabled' => $autoDebitEnabled,
        ]);
    }
}
