<?php

namespace App\Http\Controllers\Guru\Akademik;

use App\Domains\Akademik\Services\PresensiAggregationService;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RekapKehadiranController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PresensiAggregationService $aggregationService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('presensi.isi');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);

        $kelasList = Kelas::where('wali_kelas_guru_id', $guru->id)->orderBy('nama')->get();
        $kelasId = (int) $request->input('kelas_id', optional($kelasList->first())->id);
        $kelas = $kelasList->firstWhere('id', $kelasId);

        $rekap = collect();
        $semester = null;
        if ($kelas) {
            $semester = Semester::where('tahun_ajaran_id', $kelas->tahun_ajaran_id)->where('status_aktif', true)->first();
            if ($semester) {
                $rekap = $this->aggregationService->agregasiPerKelas($kelas->id, $semester);
            }
        }

        return view('portals.guru.akademik.jurnal-kbm.rekap', [
            'kelasList' => $kelasList,
            'kelas' => $kelas,
            'semester' => $semester,
            'rekap' => $rekap,
        ]);
    }
}
