<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class DashboardController extends BaseController
{
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

        return view('admin.dashboard.lembaga', [
            'stats' => [
                'guru' => Guru::count(),
                'pengguna' => User::count(),
                'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->count(),
            ],
            'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->first(),
        ]);
    }
}
