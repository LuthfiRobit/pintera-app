<?php
// app/Http/Controllers/Keuangan/RiwayatController.php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Keuangan\Concerns\AuthorizesPembayaran;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RiwayatController extends BaseController
{
    use AuthorizesPembayaran;

    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        $dari = $request->query('dari');
        $sampai = $request->query('sampai');
        $metode = $request->query('metode');

        $dateRangeValid = $dari && $sampai && $dari <= $sampai;

        $pembayarans = Pembayaran::where('siswa_id', $activeSiswa->id)
            ->when($dateRangeValid, fn ($q) => $q->whereBetween('created_at', [$dari.' 00:00:00', $sampai.' 23:59:59']))
            ->when($metode, fn ($q) => $q->where('metode', $metode))
            ->with(['pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends($request->query());

        return view('keuangan.riwayat.index', [
            'activeSiswa' => $activeSiswa,
            'pembayarans' => $pembayarans,
            'dari' => $dari,
            'sampai' => $sampai,
            'metode' => $metode,
        ]);
    }
}
