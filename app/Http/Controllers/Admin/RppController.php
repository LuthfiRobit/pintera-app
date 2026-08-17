<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Rpp\CreateRppAction;
use App\Domains\Akademik\Actions\Rpp\DeleteRppAction;
use App\Domains\Akademik\Actions\Rpp\ListRppAction;
use App\Domains\Akademik\Actions\Rpp\SubmitRppAction;
use App\Domains\Akademik\Actions\Rpp\UpdateRppAction;
use App\Domains\Akademik\Actions\Rpp\VerifyRppAction;
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
        private readonly DeleteRppAction $deleteRppAction,
        private readonly ListRppAction $listRppAction,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('rpp.view');

        $user = $request->user();
        $tab = $request->query('tab', 'saya'); // 'saya' | 'verifikasi'
        $perPage = (int) $request->query('per_page', 20);
        $search = $request->query('search');

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->first();
        $tahunAjaranId = $request->query('tahun_ajaran_id')
            ?: ($tahunAjaranAktif?->id ?: TahunAjaran::orderByDesc('id')->value('id'));
        $semesterId = $request->query('semester_id');
        $kelasId = $request->query('kelas_id');
        $mapelId = $request->query('mata_pelajaran_id');
        $status = $request->query('status');

        if ($tab === 'verifikasi') {
            $this->authorize('rpp.verify');
        }

        [
            'rppList' => $rppList,
            'stats' => $stats,
            'status' => $status,
            'targetLembagaId' => $targetLembagaId,
        ] = $this->listRppAction->execute(
            user: $user,
            tab: $tab,
            search: $search,
            tahunAjaranId: $tahunAjaranId ? (int) $tahunAjaranId : null,
            semesterId: $semesterId ? (int) $semesterId : null,
            kelasId: $kelasId ? (int) $kelasId : null,
            mapelId: $mapelId ? (int) $mapelId : null,
            status: $status,
            perPage: $perPage,
        );

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

        $dto = $request->toDTO((int) $guruId, $kelas, $semester);

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

        $dto = $request->toDTO($rpp, $kelas);

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
