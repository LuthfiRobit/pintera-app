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
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
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
        $statusYangDicari = $this->statusUntukAktor($request);

        $eagerLoads = ['kelas.tahunAjaran', 'semester', 'kelas.waliKelas.person', 'diajukanOleh', 'diverifikasiOleh', 'disetujuiOleh'];

        $filterClosure = function ($q) use ($request) {
            $q->when($request->filled('tahun_ajaran_id'), function ($subQ) use ($request) {
                $subQ->whereHas('kelas', fn ($k) => $k->where('tahun_ajaran_id', $request->tahun_ajaran_id));
            })
                ->when($request->filled('semester_id'), function ($subQ) use ($request) {
                    $subQ->where('semester_id', $request->semester_id);
                })
                ->when($request->filled('search'), function ($subQ) use ($request) {
                    $search = $request->search;
                    $subQ->where(function ($sub) use ($search) {
                        $sub->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"))
                            ->orWhereHas('kelas.waliKelas.person', fn ($p) => $p->where('nama_lengkap', 'like', "%{$search}%"))
                            ->orWhereHas('diajukanOleh', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                    });
                });
        };

        if ($tab === 'riwayat') {
            $query = PengajuanRapor::whereIn('status', [StatusPengajuanRapor::Disetujui, StatusPengajuanRapor::Ditolak])
                ->with($eagerLoads)
                ->tap($filterClosure)
                ->latest();
        } else {
            $query = PengajuanRapor::where('status', $statusYangDicari)
                ->with($eagerLoads)
                ->tap($filterClosure)
                ->latest();
        }

        $pengajuanList = $query->get();

        $statsBase = PengajuanRapor::query()
            ->when($request->filled('tahun_ajaran_id'), fn ($q) => $q->whereHas('kelas', fn ($k) => $k->where('tahun_ajaran_id', $request->tahun_ajaran_id)))
            ->when($request->filled('semester_id'), fn ($q) => $q->where('semester_id', $request->semester_id));

        $stats = [
            'totalMenunggu' => (clone $statsBase)->where('status', $statusYangDicari)->count(),
            'totalRiwayat' => (clone $statsBase)->whereIn('status', [StatusPengajuanRapor::Disetujui, StatusPengajuanRapor::Ditolak])->count(),
            'totalDisetujui' => (clone $statsBase)->where('status', StatusPengajuanRapor::Disetujui)->count(),
            'totalDitolak' => (clone $statsBase)->where('status', StatusPengajuanRapor::Ditolak)->count(),
            'roleAktor' => $request->user()->can('rapor.approve') ? 'Kepala Sekolah' : 'Wakasek Kurikulum',
        ];

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.rapor.persetujuan._daftar', compact('pengajuanList', 'tab', 'stats'))->render();
        }

        $tahunAjaranList = TahunAjaran::with('lembaga')->orderByDesc('id')->get();
        $semesterList = $request->filled('tahun_ajaran_id')
            ? Semester::where('tahun_ajaran_id', $request->tahun_ajaran_id)->with('tahunAjaran')->orderByDesc('id')->get()
            : Semester::with('tahunAjaran')->orderByDesc('id')->get();

        return view('portals.lembaga.rapor.persetujuan.index', array_merge(
            compact('pengajuanList', 'tab', 'stats', 'tahunAjaranList', 'semesterList'),
            $this->scopeHeaderData($request)
        ));
    }

    public function opsi(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer'],
        ]);

        $semesterList = Semester::where('tahun_ajaran_id', $data['tahun_ajaran_id'])
            ->orderByDesc('id')
            ->get(['id', 'nama']);

        return response()->json([
            'semesterList' => $semesterList,
        ]);
    }

    public function show(PengajuanRapor $pengajuanRapor, Request $request): View
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);

        $statusUntukAktor = $this->statusUntukAktor($request);
        $statusBolehDilihat = [$statusUntukAktor, StatusPengajuanRapor::Disetujui, StatusPengajuanRapor::Ditolak];
        abort_unless(in_array($pengajuanRapor->status, $statusBolehDilihat, true), 404, 'Pengajuan ini tidak ditemukan atau bukan wewenang Anda.');

        $isReadOnly = $pengajuanRapor->status !== $statusUntukAktor;

        $pengajuanRapor->load([
            'kelas.tahunAjaran',
            'kelas.waliKelas.person',
            'semester',
            'diajukanOleh',
            'approvalRequest.logs.user',
            'approvalRequest.currentStep',
        ]);

        $logKeputusanTerakhir = $pengajuanRapor->approvalRequest?->logs?->sortByDesc('created_at')->first();

        $tanggalFallback = $pengajuanRapor->status === StatusPengajuanRapor::Disetujui
            ? $pengajuanRapor->disetujui_pada
            : $pengajuanRapor->diverifikasi_pada;
        $tanggalKeputusan = $logKeputusanTerakhir?->created_at ?? $tanggalFallback;
        $namaPengambilKeputusan = $logKeputusanTerakhir?->user?->name;

        $rekap = $this->raporCalculationService->hitungRekapKelas($pengajuanRapor->kelas, $pengajuanRapor->semester);
        $catatanList = CatatanWaliKelas::where('semester_id', $pengajuanRapor->semester_id)
            ->whereIn('siswa_id', $rekap['siswaList']->pluck('id'))
            ->get()
            ->keyBy('siswa_id');

        // Lapis 2 (informative-only): rincian kelengkapan nilai per mapel/siswa jadi
        // panduan Waka Kurikulum sebelum memutuskan Setujui/Tolak -- bukan hard block,
        // Waka tetap bisa menyetujui walau ada nilai kosong (mis. siswa pindahan).
        $kelengkapanNilai = $this->raporCalculationService->kelengkapanNilaiKelas($pengajuanRapor->kelas, $pengajuanRapor->semester);

        return view('portals.lembaga.rapor.persetujuan.show', array_merge([
            'pengajuanRapor' => $pengajuanRapor,
            'catatanList' => $catatanList,
            'isReadOnly' => $isReadOnly,
            'tanggalKeputusan' => $tanggalKeputusan,
            'namaPengambilKeputusan' => $namaPengambilKeputusan,
            'kelengkapanNilai' => $kelengkapanNilai,
        ], $rekap));
    }

    public function cetak(PengajuanRapor $pengajuanRapor, Siswa $siswa, Request $request): Response
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
        abort_unless($siswa->kelas_id === $pengajuanRapor->kelas_id, 404);

        $pengajuanRapor->loadMissing('kelas.lembaga');

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

    /**
     * Info scope yayasan/lembaga yang sedang aktif, ditampilkan sebagai badge di header
     * halaman (pola sama seperti admin/karyawan/index.blade.php) -- HANYA relevan untuk aktor
     * berscope yayasan (punya switcher lembaga); aktor lembaga-scope tidak butuh badge ini
     * karena mereka selalu berada di 1 lembaga tetap.
     *
     * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
     */
    private function scopeHeaderData(Request $request): array
    {
        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        $lembagaId = session('active_lembaga_id');

        return [
            'isYayasan' => $isYayasan,
            'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
        ];
    }
}
