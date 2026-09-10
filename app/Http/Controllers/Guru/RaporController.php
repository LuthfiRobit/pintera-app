<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guru;

use App\Domains\Akademik\Actions\Rapor\GenerateNarasiPerkembanganAction;
use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Http\Requests\Akademik\StoreCatatanWaliKelasRequest;
use App\Http\Requests\Akademik\SubmitPengajuanRaporRequest;
use App\Models\EkstrakurikulerLembaga;
use App\Models\Kelas;
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

class RaporController extends BaseController
{
    use AuthorizesRequests;

    private const JENJANG_ANTROPOMETRI = ['KB', 'TPA', 'SPS', 'TK'];

    public function __construct(
        private readonly SimpanCatatanWaliKelasAction $simpanCatatanWaliKelasAction,
        private readonly SubmitPengajuanRaporAction $submitPengajuanRaporAction,
        private readonly GenerateNarasiPerkembanganAction $generateNarasiPerkembanganAction,
        private readonly RaporPdfDataBuilder $raporPdfDataBuilder,
        private readonly RaporCalculationService $raporCalculationService,
    ) {}

    public function index(Request $request): View|string
    {
        $this->authorize('rapor.input-wali');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);

        $lembagaId = $request->user()->lembaga_id;

        $tahunAjaranQuery = TahunAjaran::query();
        if ($lembagaId) {
            $tahunAjaranQuery->where('lembaga_id', $lembagaId);
        }
        $tahunAjaranList = $tahunAjaranQuery->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $tahunAjaranAktif = $tahunAjaranList->firstWhere('status_aktif', true);

        $tahunAjaranId = $request->has('tahun_ajaran_id')
            ? ($request->query('tahun_ajaran_id') !== '' ? (int) $request->query('tahun_ajaran_id') : null)
            : ($tahunAjaranAktif?->id ?? $tahunAjaranList->first()?->id);

        $semesterQuery = Semester::query();
        if ($lembagaId) {
            $semesterQuery->where('lembaga_id', $lembagaId);
        }
        if ($tahunAjaranId) {
            $semesterQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }
        $semesterList = $semesterQuery->orderBy('urutan')->orderBy('nama')->get();
        $semesterAktif = $semesterList->firstWhere('status_aktif', true);

        $semesterId = $request->has('semester_id')
            ? ($request->query('semester_id') !== '' ? (int) $request->query('semester_id') : null)
            : ($semesterAktif?->id ?? null);

        $kelasQuery = Kelas::where('wali_kelas_guru_id', $guru->id);
        if ($tahunAjaranId) {
            $kelasQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }
        $kelasList = $kelasQuery->orderBy('nama')->get();

        $kelasId = $request->has('kelas_id') && $request->query('kelas_id') !== ''
            ? (int) $request->query('kelas_id')
            : optional($kelasList->first())->id;

        $kelas = $kelasList->firstWhere('id', $kelasId);
        $semester = $semesterId ? $semesterList->firstWhere('id', $semesterId) : null;

        $siswaList = collect();
        $pengajuanRapor = null;
        $kelengkapanNilai = collect();
        $stats = [
            'totalSiswa' => 0,
            'totalLengkap' => 0,
            'totalBelumLengkap' => 0,
            'isNilaiComplete' => true,
            'totalNilaiKosong' => 0,
            'statusPengajuan' => null,
        ];

        if ($kelas && $semester) {
            $rawSiswaList = Siswa::where('kelas_id', $kelas->id)->with('person')->orderByNama()->get();
            $catatanList = CatatanWaliKelas::where('semester_id', $semester->id)
                ->whereIn('siswa_id', $rawSiswaList->pluck('id'))
                ->get()
                ->keyBy('siswa_id');

            $allSiswaWithCatatan = $rawSiswaList->map(function (Siswa $siswa) use ($catatanList) {
                $catatan = $catatanList->get($siswa->id);
                $siswa->catatan = $catatan;
                $siswa->catatan_lengkap = $catatan !== null;

                return $siswa;
            });

            $pengajuanRapor = PengajuanRapor::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->first();
            $kelengkapanNilai = $this->raporCalculationService->kelengkapanNilaiKelas($kelas, $semester);

            $stats = [
                'totalSiswa' => $allSiswaWithCatatan->count(),
                'totalLengkap' => $allSiswaWithCatatan->where('catatan_lengkap', true)->count(),
                'totalBelumLengkap' => $allSiswaWithCatatan->where('catatan_lengkap', false)->count(),
                'isNilaiComplete' => $kelengkapanNilai->isEmpty(),
                'totalNilaiKosong' => $kelengkapanNilai->sum(fn ($sel) => $sel->siswaBelumLengkap->count()),
                'statusPengajuan' => $pengajuanRapor?->status,
                'statusLabel' => $pengajuanRapor?->status?->label() ?? 'Draft',
                'diajukanPadaLabel' => $pengajuanRapor?->diajukan_pada?->format('d M Y H:i'),
            ];

            // Filter search & status jika ada request query
            $filtered = $allSiswaWithCatatan;
            if ($request->filled('search')) {
                $search = trim((string) $request->query('search'));
                $filtered = $filtered->filter(function (Siswa $s) use ($search) {
                    $q = mb_strtolower($search);

                    return str_contains(mb_strtolower($s->nama_lengkap ?? ''), $q)
                        || str_contains(mb_strtolower($s->nis ?? ''), $q)
                        || str_contains(mb_strtolower($s->nisn ?? ''), $q);
                })->values();
            }

            if ($request->filled('status_catatan')) {
                $statusCatatan = (string) $request->query('status_catatan');
                if (in_array($statusCatatan, ['complete', 'lengkap'], true)) {
                    $filtered = $filtered->where('catatan_lengkap', true)->values();
                } elseif (in_array($statusCatatan, ['incomplete', 'perlu_dilengkapi'], true)) {
                    $filtered = $filtered->where('catatan_lengkap', false)->values();
                }
            }

            $siswaList = $filtered;
        }

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.guru.rapor.catatan._daftar', [
                'kelas' => $kelas,
                'semester' => $semester,
                'siswaList' => $siswaList,
                'pengajuanRapor' => $pengajuanRapor,
                'kelengkapanNilai' => $kelengkapanNilai,
                'stats' => $stats,
            ])->render();
        }

        return view('portals.guru.rapor.catatan.index', [
            'tahunAjaranList' => $tahunAjaranList,
            'tahunAjaranId' => $tahunAjaranId,
            'semesterList' => $semesterList,
            'semesterId' => $semesterId,
            'kelasId' => $kelasId,
            'kelasList' => $kelasList,
            'kelas' => $kelas,
            'semester' => $semester,
            'siswaList' => $siswaList,
            'pengajuanRapor' => $pengajuanRapor,
            'kelengkapanNilai' => $kelengkapanNilai,
            'stats' => $stats,
        ]);
    }

    public function opsi(Request $request): JsonResponse
    {
        $this->authorize('rapor.input-wali');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer'],
        ]);

        $tahunAjaranId = (int) $data['tahun_ajaran_id'];
        $lembagaId = $request->user()->lembaga_id;

        $semesterQuery = Semester::where('tahun_ajaran_id', $tahunAjaranId);
        if ($lembagaId) {
            $semesterQuery->where('lembaga_id', $lembagaId);
        }
        $semesterList = $semesterQuery->orderBy('urutan')->orderBy('nama')->get(['id', 'nama', 'status_aktif']);

        $kelasList = Kelas::where('wali_kelas_guru_id', $guru->id)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return response()->json([
            'semesterList' => $semesterList,
            'kelasList' => $kelasList,
        ]);
    }

    public function edit(Request $request, Siswa $siswa): View
    {
        return $this->editCatatan($request, $siswa);
    }

    public function editCatatan(Request $request, Siswa $siswa): View
    {
        $this->authorize('rapor.input-wali');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $semesterId = (int) $request->input('semester_id', 0);
        abort_if($semesterId === 0, 404, 'Konteks semester wajib disertakan untuk membuka form catatan wali kelas.');
        $semester = Semester::find($semesterId);
        abort_if($semester === null, 404);
        abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

        $catatan = CatatanWaliKelas::where('siswa_id', $siswa->id)->where('semester_id', $semester->id)->first()
            ?? new CatatanWaliKelas(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

        $siswaListKelas = Siswa::where('kelas_id', $siswa->kelas_id)->with('person')->orderByNama()->get();
        $posisiSaatIni = $siswaListKelas->search(fn (Siswa $s) => $s->id === $siswa->id);
        $siswaSebelumnya = $posisiSaatIni > 0 ? $siswaListKelas->get($posisiSaatIni - 1) : null;
        $siswaBerikutnya = $posisiSaatIni !== false && $posisiSaatIni < $siswaListKelas->count() - 1 ? $siswaListKelas->get($posisiSaatIni + 1) : null;

        $bentukPendidikan = $siswa->kelas->lembaga->bentuk_pendidikan ?? null;

        return view('portals.guru.rapor.catatan.edit', [
            'siswa' => $siswa,
            'semester' => $semester,
            'catatan' => $catatan,
            'siswaSebelumnya' => $siswaSebelumnya,
            'siswaBerikutnya' => $siswaBerikutnya,
            'tampilkanAntropometri' => in_array($bentukPendidikan, self::JENJANG_ANTROPOMETRI, true),
            'tampilkanPklInfo' => $bentukPendidikan === 'SMK',
            'ekskulOptions' => EkstrakurikulerLembaga::where('lembaga_id', $siswa->lembaga_id)->orderBy('nama_ekskul')->pluck('nama_ekskul'),
        ]);
    }

    public function update(Siswa $siswa, StoreCatatanWaliKelasRequest $request): RedirectResponse
    {
        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $semester = Semester::find($request->validated('semester_id'));
        abort_if($semester === null || $semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

        $this->simpanCatatanWaliKelasAction->execute(
            CatatanWaliKelasData::fromArray([...$request->validated(), 'siswa_id' => $siswa->id])
        );

        $nextSiswaId = $request->input('next_siswa_id');
        if ($nextSiswaId) {
            return redirect()
                ->route('guru.rapor.catatan.edit', ['siswa' => $nextSiswaId, 'semester_id' => $request->input('semester_id')])
                ->with('success', 'Catatan wali kelas berhasil disimpan.');
        }

        return redirect()
            ->route('guru.rapor.catatan.index', ['kelas_id' => $siswa->kelas_id, 'semester_id' => $request->input('semester_id')])
            ->with('success', 'Catatan wali kelas berhasil disimpan.');
    }

    public function generateNarasi(Siswa $siswa, Request $request): JsonResponse
    {
        $this->authorize('rapor.input-wali');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $semester = Semester::find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);
        abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

        $narasi = $this->generateNarasiPerkembanganAction->execute($siswa, $siswa->kelas, $semester);

        return response()->json(['narasi' => $narasi]);
    }

    public function ajukan(SubmitPengajuanRaporRequest $request): RedirectResponse
    {
        $guru = $request->user()->guru;
        abort_if($guru === null, 403);

        $kelas = Kelas::find($request->validated('kelas_id'));
        abort_if($kelas === null, 404);
        abort_unless($kelas->wali_kelas_guru_id === $guru->id, 403);

        $semester = Semester::find($request->validated('semester_id'));
        abort_if($semester === null, 404);
        abort_if($semester->tahun_ajaran_id !== $kelas->tahun_ajaran_id, 404);

        $this->submitPengajuanRaporAction->execute($kelas, $semester, $request->user());

        return redirect()
            ->route('guru.rapor.catatan.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id])
            ->with('success', 'Rapor kelas berhasil diajukan untuk verifikasi Waka Kurikulum.');
    }

    public function cetak(Siswa $siswa, Request $request): Response
    {
        $this->authorize('rapor.input-wali');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $semester = Semester::find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);
        abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

        $data = $this->raporPdfDataBuilder->build($siswa, $semester);
        $template = $this->raporPdfDataBuilder->templateUntukJenjang($siswa->kelas->lembaga->bentuk_pendidikan);

        $pdf = Pdf::loadView($template, $data);

        return $pdf->stream('rapor-'.Str::slug($siswa->nama_lengkap).'.pdf');
    }
}
