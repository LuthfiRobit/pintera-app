<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\KenaikanKelas\ProsesKenaikanKelasAction;
use App\Domains\Akademik\DataTransferObjects\KenaikanKelasData;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KenaikanKelasController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kenaikan-kelas.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        $tahunAjaranTujuanId = $request->query('tahun_ajaran_tujuan_id');

        return view('portals.lembaga.akademik.kenaikan-kelas.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'kelasLamaList' => $tahunAjaranId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->with('lembaga')->withCount('siswa')->orderBy('nama')->get()
                : collect(),
            'kelasTujuanList' => $tahunAjaranTujuanId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderBy('nama')->get()
                : collect(),
            'semesterList' => $tahunAjaranTujuanId
                ? Semester::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderByDesc('id')->get()
                : collect(),
            'tahunAjaranId' => $tahunAjaranId,
            'tahunAjaranTujuanId' => $tahunAjaranTujuanId,
        ]);
    }

    public function store(Request $request, ProsesKenaikanKelasAction $action): RedirectResponse
    {
        $this->authorize('kenaikan-kelas.kelola');

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus,lewati'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['nullable', 'integer'],
        ]);

        try {
            $result = $action->execute(new KenaikanKelasData(mapping: $data['mapping']));
        } catch (\DomainException $e) {
            return back()->withErrors(['mapping' => $e->getMessage()]);
        }

        $status = 'Kenaikan kelas berhasil diproses.';
        if (! empty($result['jadwalGagal'])) {
            $status .= ' '.count($result['jadwalGagal']).' jadwal tidak tersalin karena bentrok: '.implode('; ', $result['jadwalGagal']).'.';
        }

        return redirect()->route('admin.kelas.index')->with('status', $status);
    }
}
