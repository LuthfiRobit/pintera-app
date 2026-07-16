<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function __construct(private DashboardStatsService $dashboardStats)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->hasRole('guru')) {
            return view('admin.dashboard.guru', [
                'jabatanTambahan' => $user->guru?->jabatanTambahan ?? collect(),
            ]);
        }

        if ($user->widestScopeLevel() === 'yayasan') {
            return view('admin.dashboard.yayasan', [
                'lembagaList' => Lembaga::all(),
                'stats' => [
                    'lembaga' => Lembaga::count(),
                    'guru' => Guru::count(),
                    'pengguna' => User::count(),
                    'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->count(),
                ],
            ]);
        }

        return view('admin.dashboard.lembaga', $this->lembagaViewData($user->lembaga_id, $user));
    }

    private function lembagaViewData(int $lembagaId, User $user): array
    {
        $data = [
            'stats' => [
                'guru' => Guru::where('lembaga_id', $lembagaId)->count(),
                'pengguna' => User::where('lembaga_id', $lembagaId)->count(),
                'tahunAjaranAktif' => TahunAjaran::where('lembaga_id', $lembagaId)->where('status_aktif', true)->count(),
            ],
            'tahunAjaranAktif' => TahunAjaran::where('lembaga_id', $lembagaId)->where('status_aktif', true)->first(),
            'spmbStats' => null,
            'tren' => null,
            'keuanganStats' => null,
        ];

        if ($user->can('spmb-pendaftaran.view')) {
            $data['spmbStats'] = $this->dashboardStats->statistikSpmb($lembagaId);
            $data['tren'] = $this->dashboardStats->trenPendaftaranHarian($lembagaId);
        }

        if ($user->can('tagihan.view')) {
            $data['keuanganStats'] = $this->dashboardStats->statistikKeuangan($lembagaId);
        }

        return $data;
    }
}
