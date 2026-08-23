<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\Tagihan\BatalkanTagihanAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JenisTagihanMonitoringController extends Controller
{
    use AuthorizesRequests;

    public function index(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.view');

        $tagihanQuery = Tagihan::where('jenis_tagihan_id', $jenisTagihan->id);

        $ringkasan = [
            'total_penerima' => (clone $tagihanQuery)->count(),
            'lunas' => (clone $tagihanQuery)->where('status', 'lunas')->count(),
            'sebagian' => (clone $tagihanQuery)->where('status', 'sebagian')->count(),
            'belum_bayar' => (clone $tagihanQuery)->where('status', 'belum_bayar')->count(),
            'dibatalkan' => (clone $tagihanQuery)->where('status', 'dibatalkan')->count(),
            'total_tertagih' => (float) (clone $tagihanQuery)->where('status', '!=', 'dibatalkan')->sum('net_amount'),
            'total_masuk' => (float) (clone $tagihanQuery)->where('status', '!=', 'dibatalkan')->sum('paid_amount'),
        ];

        $tagihanPenerima = Tagihan::with('tagihable')
            ->where('jenis_tagihan_id', $jenisTagihan->id)
            ->where('status', '!=', 'dibatalkan')
            ->paginate(15, ['*'], 'penerima_page');

        $tagihanTunggakan = Tagihan::with('tagihable')
            ->where('jenis_tagihan_id', $jenisTagihan->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->selectRaw('MAX(id) as id, tagihable_type, tagihable_id, SUM(net_amount - paid_amount) as total_tunggakan, COUNT(*) as jumlah_tunggakan')
            ->groupBy('tagihable_type', 'tagihable_id')
            ->orderByDesc('total_tunggakan')
            ->paginate(15, ['*'], 'tunggakan_page');

        return view('portals.lembaga.keuangan.jenis-tagihan.monitoring.index', [
            'jenisTagihan' => $jenisTagihan,
            'ringkasan' => $ringkasan,
            'tagihanPenerima' => $tagihanPenerima,
            'tagihanTunggakan' => $tagihanTunggakan,
        ]);
    }

    public function batalTagihan(Request $request, JenisTagihan $jenisTagihan, Tagihan $tagihan, BatalkanTagihanAction $action): RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $request->validate([
            'cancel_reason' => 'required|string|max:255',
        ]);

        $action->execute($jenisTagihan, $tagihan, auth()->id(), $request->cancel_reason);

        return back()->with('success', 'Tagihan berhasil dibatalkan.');
    }
}