<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Support\ResolveAnakOrangTuaTrait;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RiwayatIzinSakitAnakController extends BaseController
{
    use ResolveAnakOrangTuaTrait;

    public function index(Request $request): View
    {
        $request->validate([
            'dari_tanggal' => ['nullable', 'date'],
            'sampai_tanggal' => ['nullable', 'date', 'after_or_equal:dari_tanggal'],
        ]);

        $anakList = $this->resolveAnakList($request->user());
        $anak = $this->resolveAnakTerpilih($anakList, $request->integer('siswa_id') ?: null);

        $dariTanggal = $request->date('dari_tanggal') ?: now()->startOfMonth();
        $sampaiTanggal = $request->date('sampai_tanggal') ?: now()->endOfMonth();

        $riwayatList = $anak
            ? Presensi::where('siswa_id', $anak->id)
                ->whereIn('status', ['izin', 'sakit'])
                ->whereHas('sesiPembelajaran', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->whereBetween('tanggal', [$dariTanggal->toDateString(), $sampaiTanggal->toDateString()]))
                ->with([
                    'sesiPembelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with([
                        'mataPelajaran' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class),
                    ]),
                ])
                ->latest('id')
                ->get()
            : collect();

        return view('admin.orang-tua.riwayat-izin-sakit-anak', [
            'anakList' => $anakList,
            'anak' => $anak,
            'dariTanggal' => $dariTanggal,
            'sampaiTanggal' => $sampaiTanggal,
            'riwayatList' => $riwayatList,
        ]);
    }
}
