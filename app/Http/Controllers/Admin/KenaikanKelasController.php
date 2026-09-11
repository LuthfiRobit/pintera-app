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

        $errorTahunAjaran = null;
        if ($tahunAjaranId && $tahunAjaranTujuanId) {
            $tahunSumber = TahunAjaran::find($tahunAjaranId);
            $tahunTujuan = TahunAjaran::find($tahunAjaranTujuanId);

            if ($tahunSumber && $tahunTujuan) {
                if ((int) $tahunAjaranId === (int) $tahunAjaranTujuanId) {
                    $errorTahunAjaran = 'Tahun Ajaran Sumber dan Tujuan tidak boleh sama. Pilih Tahun Ajaran Tujuan yang berbeda (biasanya tahun ajaran berikutnya).';
                } elseif ($tahunTujuan->tanggal_mulai < $tahunSumber->tanggal_mulai) {
                    $errorTahunAjaran = "Tahun Ajaran Tujuan (\"{$tahunTujuan->nama}\") lebih lama dari Tahun Ajaran Sumber (\"{$tahunSumber->nama}\"). Pilih Tahun Ajaran Tujuan yang lebih baru.";
                }
            }
        }

        return view('portals.lembaga.akademik.kenaikan-kelas.index', [
            'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('tanggal_mulai')->get(),
            'kelasLamaList' => ($tahunAjaranId && ! $errorTahunAjaran)
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->with('lembaga')->withCount('siswa')->orderBy('nama')->get()
                : collect(),
            'kelasTujuanList' => ($tahunAjaranTujuanId && ! $errorTahunAjaran)
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderBy('nama')->get()
                : collect(),
            'semesterList' => ($tahunAjaranTujuanId && ! $errorTahunAjaran)
                ? Semester::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderByDesc('id')->get()
                : collect(),
            'tahunAjaranId' => $tahunAjaranId,
            'tahunAjaranTujuanId' => $tahunAjaranTujuanId,
            'errorTahunAjaran' => $errorTahunAjaran,
        ]);
    }

    public function store(Request $request, ProsesKenaikanKelasAction $action): RedirectResponse
    {
        $this->authorize('kenaikan-kelas.kelola');

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus,lewati'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer', 'exists:kelas,id'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['required_if:mapping.*.salin_jadwal,1', 'nullable', 'integer', 'exists:semester,id'],
        ], [
            'mapping.*.kelas_baru_id.exists' => 'Kelas tujuan yang dipilih tidak valid atau sudah tidak tersedia.',
            'mapping.*.semester_tujuan_id.required_if' => 'Anda mencentang "Salin Jadwal" untuk salah satu kelas, tapi belum memilih semester tujuan. Pilih semester tujuan atau batalkan centang tersebut.',
        ]);

        try {
            $result = $action->execute(new KenaikanKelasData(mapping: $data['mapping']));
        } catch (\DomainException $e) {
            return back()->withErrors(['mapping' => $e->getMessage()]);
        }

        $status = "Kenaikan kelas berhasil diproses: {$result['siswaNaik']} siswa naik kelas, {$result['siswaLulus']} siswa diluluskan, {$result['kelasDilewati']} kelas dilewati.";
        if (! empty($result['jadwalGagal'])) {
            $status .= ' '.count($result['jadwalGagal']).' jadwal tidak tersalin karena bentrok: '.implode('; ', $result['jadwalGagal']).'.';
        }

        return redirect()->route('admin.kelas.index')->with('status', $status);
    }
}
