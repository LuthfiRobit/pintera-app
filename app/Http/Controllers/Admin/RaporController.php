<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RaporController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
    ) {
    }

    public function index(Request $request): View|string
    {
        $this->authorize('rapor.view');

        $tahunAjaranId = is_scalar($request->query('tahun_ajaran_id')) ? $request->query('tahun_ajaran_id') : null;
        $kelasIdParam = is_scalar($request->query('kelas_id')) ? $request->query('kelas_id') : null;
        if (! $tahunAjaranId && $kelasIdParam) {
            // Deep link with kelas_id but no tahun_ajaran_id (e.g. a bookmarked/shared URL):
            // derive it from the kelas itself instead of falling back to the active tahun
            // ajaran, which may not be the one the kelas actually belongs to.
            $tahunAjaranId = Kelas::find($kelasIdParam)?->tahun_ajaran_id;
        }
        if (! $tahunAjaranId) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        $kelasList = $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect();
        $semesterList = $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect();

        $kelasId = $kelasIdParam;
        if (! $kelasId || ! $kelasList->contains('id', (int) $kelasId)) {
            $kelasId = $kelasList->first()?->id;
        }
        $semesterId = is_scalar($request->query('semester_id')) ? $request->query('semester_id') : null;
        if (! $semesterId || ! $semesterList->contains('id', (int) $semesterId)) {
            $semesterId = $semesterList->first()?->id;
        }

        $selectedKelas = $kelasId ? Kelas::find($kelasId) : null;
        $selectedSemester = $semesterId ? Semester::find($semesterId) : null;

        $rekap = ($selectedKelas && $selectedSemester)
            ? $this->raporCalculationService->hitungRekapKelas($selectedKelas, $selectedSemester)
            : $this->rekapKosong();

        if ($request->ajax()) {
            return view('admin.rapor._hasil', array_merge([
                'selectedKelas' => $selectedKelas,
                'selectedSemester' => $selectedSemester,
            ], $rekap))->render();
        }

        return view('admin.rapor.index', array_merge([
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'kelasList' => $kelasList,
            'semesterList' => $semesterList,
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
        ], $rekap));
    }

    public function opsi(Request $request): JsonResponse
    {
        $this->authorize('rapor.view');

        $data = $request->validate(['tahun_ajaran_id' => ['required', 'integer']]);

        $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
        abort_if($tahunAjaran === null, 404);

        return response()->json([
            'kelasList' => Kelas::where('tahun_ajaran_id', $tahunAjaran->id)->orderBy('nama')->get(['id', 'nama']),
            'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
        ]);
    }

    public function cetak(Request $request): Response
    {
        $this->authorize('rapor.view');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
        ]);

        $selectedKelas = Kelas::find($data['kelas_id']);
        abort_if($selectedKelas === null, 404);
        $selectedSemester = Semester::find($data['semester_id']);
        abort_if($selectedSemester === null, 404);
        abort_if($selectedSemester->tahun_ajaran_id !== $selectedKelas->tahun_ajaran_id, 404);

        $rekap = $this->raporCalculationService->hitungRekapKelas($selectedKelas, $selectedSemester);

        $pdf = Pdf::loadView('pdf.rekap-rapor', array_merge([
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
        ], $rekap));

        return $pdf->stream('rekap-rapor-'.Str::slug($selectedKelas->nama).'.pdf');
    }

    /**
     * @return array{siswaList: \Illuminate\Support\Collection, mapelList: \Illuminate\Support\Collection, rekapNilai: array<int, array<int, float|null>>, classAvg: float|null, highestScore: float|null}
     */
    private function rekapKosong(): array
    {
        return [
            'siswaList' => collect(),
            'mapelList' => collect(),
            'rekapNilai' => [],
            'classAvg' => null,
            'highestScore' => null,
        ];
    }
}
