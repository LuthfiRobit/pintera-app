<?php
// app/Http/Controllers/Admin/JenisTagihanMonitoringController.php

namespace App\Http\Controllers\Admin;

use App\Models\JenisTagihan;
use App\Models\Tagihan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        return view('admin.jenis-tagihan.monitoring.index', [
            'jenisTagihan' => $jenisTagihan,
            'ringkasan' => $ringkasan,
        ]);
    }

    public function batalTagihan(Request $request, JenisTagihan $jenisTagihan, Tagihan $tagihan)
    {
        $this->authorize('jenis-tagihan.edit');

        return response('OK', 200);
    }
}
