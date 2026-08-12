<?php
// app/Http/Controllers/Keuangan/CheckoutController.php

namespace App\Http\Controllers\Keuangan;

use App\Models\Scopes\TenantScope;
use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class CheckoutController extends BaseController
{
    public function create(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        $tagihanIds = (array) $request->query('tagihan_ids', []);

        $tagihans = Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('id', $tagihanIds)
            ->with(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->get();

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        return view('keuangan.checkout.create', [
            'activeSiswa' => $activeSiswa,
            'tagihans' => $tagihans,
            'totalTagihan' => $totalTagihan,
            'wallet' => $activeSiswa->wallet,
        ]);
    }
}
