<?php
// app/Http/Controllers/Admin/JenisTagihanMonitoringController.php

namespace App\Http\Controllers\Admin;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Tagihan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class JenisTagihanMonitoringController extends BaseController
{
    use AuthorizesRequests;

    public function index(JenisTagihan $jenisTagihan)
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

        return view('admin.jenis-tagihan.monitoring.index', [
            'jenisTagihan' => $jenisTagihan,
            'ringkasan' => $ringkasan,
            'tagihanPenerima' => $tagihanPenerima,
            'tagihanTunggakan' => $tagihanTunggakan,
        ]);
    }

    public function batalTagihan(Request $request, JenisTagihan $jenisTagihan, Tagihan $tagihan)
    {
        $this->authorize('jenis-tagihan.edit');

        $request->validate([
            'cancel_reason' => 'required|string|max:255',
        ]);

        // Ownership check MUST run before any business-rule check — otherwise a status-based
        // 422/403 response difference leaks whether an arbitrary cross-tenant tagihan is
        // belum_bayar, before we've verified it even belongs to this jenisTagihan.
        if ($tagihan->jenis_tagihan_id !== $jenisTagihan->id) {
            abort(403, 'Tagihan tidak ditemukan untuk jenis tagihan ini.');
        }

        if ($tagihan->status !== 'belum_bayar') {
            abort(422, 'Hanya tagihan dengan status belum bayar yang dapat dibatalkan.');
        }

        $tagihan->update([
            'status' => 'dibatalkan',
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
            'cancel_reason' => $request->cancel_reason,
        ]);

        return back()->with('success', 'Tagihan berhasil dibatalkan.');
    }
}
