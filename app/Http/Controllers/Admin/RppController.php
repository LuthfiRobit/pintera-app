<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Rpp\CreateRppAction;
use App\Domains\Akademik\Actions\Rpp\DeleteRppAction;
use App\Domains\Akademik\Actions\Rpp\SubmitRppAction;
use App\Domains\Akademik\Actions\Rpp\UpdateRppAction;
use App\Domains\Akademik\Actions\Rpp\VerifyRppAction;
use App\Domains\Akademik\DataTransferObjects\RppData;
use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use App\Http\Requests\Akademik\StoreRppRequest;
use App\Http\Requests\Akademik\UpdateRppRequest;
use App\Http\Requests\Akademik\VerifyRppRequest;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RppController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreateRppAction $createRppAction,
        private readonly UpdateRppAction $updateRppAction,
        private readonly SubmitRppAction $submitRppAction,
        private readonly VerifyRppAction $verifyRppAction,
        private readonly DeleteRppAction $deleteRppAction
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('rpp.view');

        $user = $request->user();
        $tab = $request->query('tab', 'saya'); // 'saya' | 'verifikasi'
        $perPage = (int) $request->query('per_page', 20);
        $search = $request->query('search');

        $targetLembagaId = $user->active_lembaga_id 
            ?: ($user->lembaga_id ?: null);

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->first();
        $tahunAjaranId = $request->query('tahun_ajaran_id') 
            ?: ($tahunAjaranAktif?->id ?: TahunAjaran::orderByDesc('id')->value('id'));
        $semesterId = $request->query('semester_id');
        $kelasId = $request->query('kelas_id');
        $mapelId = $request->query('mata_pelajaran_id');
        $status = $request->query('status');

        $baseQuery = Rpp::query();

        if ($targetLembagaId) {
            $baseQuery->where('lembaga_id', $targetLembagaId);
        }

        if ($tab === 'saya') {
            $guru = Guru::where('user_id', $user->id)->first();
            if ($guru) {
                $baseQuery->where('guru_id', $guru->id);
            }
        } elseif ($tab === 'verifikasi') {
            $this->authorize('rpp.verify');
            if ($status === null && ! $request->has('status')) {
                $status = StatusRpp::Diajukan->value;
            }
        }

        // Statistik KPI ringkasan
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', StatusRpp::Draft)->count(),
            'diajukan' => (clone $baseQuery)->where('status', StatusRpp::Diajukan)->count(),
            'disetujui' => (clone $baseQuery)->where('status', StatusRpp::Disetujui)->count(),
            'perlu_revisi' => (clone $baseQuery)->where('status', StatusRpp::PerluRevisi)->count(),
        ];

        $query = (clone $baseQuery)->with(['guru', 'kelas', 'mataPelajaran', 'semester', 'tahunAjaran', 'verifiedBy']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_topik', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%")
                  ->orWhereHas('guru', fn ($g) => $g->where('nama', 'like', "%{$search}%"))
                  ->orWhereHas('mataPelajaran', fn ($m) => $m->where('nama', 'like', "%{$search}%"));
            });
        }
        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }
        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }
        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }
        if ($mapelId) {
            $query->where('mata_pelajaran_id', $mapelId);
        }
        if ($status && in_array($status, array_column(StatusRpp::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $rppList = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        if ($request->ajax()) {
            return view('portals.lembaga.akademik.rpp._daftar', compact('rppList', 'tab', 'perPage'));
        }

        // Pilihan dropdown berdasar tenant & filter
        $kelasQuery = Kelas::query();
        if ($targetLembagaId) {
            $kelasQuery->where('lembaga_id', $targetLembagaId);
        }
        if ($tahunAjaranId) {
            $kelasQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }
        $kelasList = $kelasQuery->orderBy('nama')->get();

        $mapelQuery = MataPelajaran::query();
        if ($targetLembagaId) {
            $mapelQuery->where('lembaga_id', $targetLembagaId);
        }
        $mataPelajaranList = $mapelQuery->orderBy('nama')->get();

        $semesterQuery = Semester::query();
        if ($tahunAjaranId) {
            $semesterQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }
        $semesterList = $semesterQuery->orderByDesc('id')->get();

        $tahunAjaranList = TahunAjaran::orderByDesc('id')->get();

        return view('portals.lembaga.akademik.rpp.index', [
            'tab' => $tab,
            'rppList' => $rppList,
            'stats' => $stats,
            'kelasList' => $kelasList,
            'mataPelajaranList' => $mataPelajaranList,
            'semesterList' => $semesterList,
            'tahunAjaranList' => $tahunAjaranList,
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'tahunAjaranId' => $tahunAjaranId,
            'semesterId' => $semesterId,
            'kelasId' => $kelasId,
            'mapelId' => $mapelId,
            'status' => $status,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(StoreRppRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $guru = Guru::where('user_id', $user->id)->first();

        $kelas = Kelas::findOrFail($request->input('kelas_id'));
        $semester = Semester::findOrFail($request->input('semester_id'));

        $guruId = $guru ? $guru->id : ($request->input('guru_id') ?: Guru::where('lembaga_id', $kelas->lembaga_id)->value('id'));

        if (! $guruId) {
            abort(422, 'Profil guru pengampu tidak ditemukan.');
        }

        $dto = new RppData(
            yayasanId: $kelas->yayasan_id ?: $kelas->lembaga->yayasan_id,
            lembagaId: $kelas->lembaga_id,
            guruId: (int) $guruId,
            tahunAjaranId: $semester->tahun_ajaran_id,
            semesterId: $semester->id,
            kelasId: $kelas->id,
            judulTopik: (string) $request->input('judul_topik'),
            alokasiWaktu: (string) $request->input('alokasi_waktu'),
            mataPelajaranId: $request->filled('mata_pelajaran_id') ? (int) $request->input('mata_pelajaran_id') : null,
            pertemuanKe: $request->input('pertemuan_ke'),
            file: $request->file('file')
        );

        try {
            $rpp = $this->createRppAction->execute($dto);
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        }

        $msg = 'Dokumen RPP / Modul Ajar berhasil disimpan sebagai Draf.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $msg, 'rpp' => $rpp]);
        }

        return redirect()->route('admin.rpp.index', ['tab' => 'saya'])->with('success', $msg);
    }

    public function download(Request $request, Rpp $rpp): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('rpp.view');

        if (! Storage::disk('public')->exists($rpp->file_path)) {
            abort(404, 'Berkas fisik tidak ditemukan di server.');
        }

        if ($request->boolean('inline') || $request->has('preview')) {
            $mime = Storage::disk('public')->mimeType($rpp->file_path) ?: 'application/pdf';
            return response()->file(Storage::disk('public')->path($rpp->file_path), [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . $rpp->file_name . '"',
            ]);
        }

        return Storage::disk('public')->download($rpp->file_path, $rpp->file_name);
    }

    public function update(UpdateRppRequest $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $kelas = Kelas::findOrFail($request->input('kelas_id'));

        $dto = new RppData(
            yayasanId: $rpp->yayasan_id,
            lembagaId: $rpp->lembaga_id,
            guruId: $rpp->guru_id,
            tahunAjaranId: $rpp->tahun_ajaran_id,
            semesterId: $rpp->semester_id,
            kelasId: $kelas->id,
            judulTopik: (string) $request->input('judul_topik'),
            alokasiWaktu: (string) $request->input('alokasi_waktu'),
            mataPelajaranId: $request->filled('mata_pelajaran_id') ? (int) $request->input('mata_pelajaran_id') : null,
            pertemuanKe: $request->input('pertemuan_ke'),
            file: $request->file('file')
        );

        try {
            $this->updateRppAction->execute($rpp, $dto);
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        }

        $msg = 'Dokumen RPP / Modul Ajar berhasil diperbarui.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.rpp.index', ['tab' => 'saya'])->with('success', $msg);
    }

    public function submit(Request $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorize('rpp.kelola');

        try {
            $this->submitRppAction->execute($rpp);
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors());
        }

        $msg = 'Dokumen RPP berhasil diajukan ke Kurikulum untuk diverifikasi.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.rpp.index', ['tab' => 'saya'])->with('success', $msg);
    }

    public function verify(VerifyRppRequest $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $targetStatus = StatusRpp::from($data['status']);
        $catatanRevisi = $data['catatan_revisi'] ?? null;

        try {
            $this->verifyRppAction->execute(
                rpp: $rpp,
                targetStatus: $targetStatus,
                verifierUserId: (int) $request->user()->id,
                catatanRevisi: $catatanRevisi
            );
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors());
        }

        $statusLabel = $targetStatus === StatusRpp::Disetujui ? 'disetujui' : 'diminta revisi';
        $msg = "Dokumen RPP berhasil diverifikasi ({$statusLabel}).";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.rpp.index', ['tab' => 'verifikasi'])->with('success', $msg);
    }

    public function destroy(Request $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorize('rpp.kelola');

        try {
            $this->deleteRppAction->execute($rpp);
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors());
        }

        $msg = 'Dokumen RPP berhasil dihapus.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.rpp.index', ['tab' => 'saya'])->with('success', $msg);
    }
}
