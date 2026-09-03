<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lembaga\Rapor;

use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Http\Requests\Akademik\ProcessRaporApprovalRequest;
use App\Models\Siswa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PersetujuanController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
        private readonly VerifyPengajuanRaporAction $verifyPengajuanRaporAction,
        private readonly ApprovePengajuanRaporAction $approvePengajuanRaporAction,
        private readonly RaporPdfDataBuilder $raporPdfDataBuilder,
    ) {}

    public function index(Request $request): View|string
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);

        $tab = $request->query('tab', 'menunggu');

        if ($tab === 'riwayat') {
            $effectiveLembagaId = $request->user()->widestScopeLevel() === 'yayasan'
                ? session('active_lembaga_id')
                : $request->user()->lembaga_id;

            $query = PengajuanRapor::whereIn('status', [StatusPengajuanRapor::Disetujui, StatusPengajuanRapor::Ditolak])
                ->when($effectiveLembagaId, fn ($q) => $q->where('lembaga_id', $effectiveLembagaId))
                ->with(['kelas.tahunAjaran', 'semester'])
                ->when($request->search, function ($q, $search) {
                    $q->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
                })
                ->latest();
        } else {
            $statusYangDicari = $this->statusUntukAktor($request);

            $query = PengajuanRapor::where('status', $statusYangDicari)
                ->with(['kelas.tahunAjaran', 'semester'])
                ->when($request->search, function ($q, $search) {
                    $q->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
                })
                ->latest();
        }

        $pengajuanList = $query->get();

        if ($request->ajax()) {
            return view('portals.lembaga.rapor.persetujuan._daftar', compact('pengajuanList', 'tab'))->render();
        }

        return view('portals.lembaga.rapor.persetujuan.index', compact('pengajuanList', 'tab'));
    }

    public function show(PengajuanRapor $pengajuanRapor, Request $request): View
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
        abort_unless($pengajuanRapor->status === $this->statusUntukAktor($request), 404, 'Pengajuan ini bukan berada di tahap Anda.');

        $pengajuanRapor->load(['kelas', 'semester', 'approvalRequest.logs.user', 'approvalRequest.currentStep']);

        $rekap = $this->raporCalculationService->hitungRekapKelas($pengajuanRapor->kelas, $pengajuanRapor->semester);
        $catatanList = CatatanWaliKelas::where('semester_id', $pengajuanRapor->semester_id)
            ->whereIn('siswa_id', $rekap['siswaList']->pluck('id'))
            ->get()
            ->keyBy('siswa_id');

        return view('portals.lembaga.rapor.persetujuan.show', array_merge([
            'pengajuanRapor' => $pengajuanRapor,
            'catatanList' => $catatanList,
        ], $rekap));
    }

    public function cetak(PengajuanRapor $pengajuanRapor, Siswa $siswa, Request $request): Response
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
        abort_unless($siswa->kelas_id === $pengajuanRapor->kelas_id, 404);

        $data = $this->raporPdfDataBuilder->build($siswa, $pengajuanRapor->semester);
        $template = $this->raporPdfDataBuilder->templateUntukJenjang($pengajuanRapor->kelas->lembaga->bentuk_pendidikan);

        $pdf = Pdf::loadView($template, $data);

        return $pdf->stream('rapor-'.Str::slug($siswa->nama_lengkap).'.pdf');
    }

    public function decision(ProcessRaporApprovalRequest $request, PengajuanRapor $pengajuanRapor): RedirectResponse
    {
        abort_unless($pengajuanRapor->status === $this->statusUntukAktor($request), 404, 'Pengajuan ini bukan berada di tahap Anda.');

        $action = ApprovalAction::from($request->validated('action'));
        $catatan = $request->validated('catatan');

        if ($request->user()->can('rapor.approve')) {
            $this->approvePengajuanRaporAction->execute($pengajuanRapor, $request->user(), $action, $catatan);
        } else {
            $this->verifyPengajuanRaporAction->execute($pengajuanRapor, $request->user(), $action, $catatan);
        }

        $pesan = $action === ApprovalAction::Approve
            ? 'Keputusan berhasil disimpan.'
            : 'Pengajuan berhasil ditolak. Wali kelas dapat mengajukan ulang setelah revisi.';

        return redirect()->route('admin.rapor.persetujuan.index')->with('success', $pesan);
    }

    private function statusUntukAktor(Request $request): StatusPengajuanRapor
    {
        return $request->user()->can('rapor.approve') ? StatusPengajuanRapor::Diverifikasi : StatusPengajuanRapor::Diajukan;
    }
}
