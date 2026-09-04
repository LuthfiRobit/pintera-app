<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Rpp\CreateRppAction;
use App\Domains\Akademik\Actions\Rpp\DeleteRppAction;
use App\Domains\Akademik\Actions\Rpp\ListRppAction;
use App\Domains\Akademik\Actions\Rpp\SubmitRppAction;
use App\Domains\Akademik\Actions\Rpp\UpdateRppAction;
use App\Domains\Akademik\Actions\Rpp\VerifyRppAction;
use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\Rpp;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Http\Requests\Akademik\StoreRppRequest;
use App\Http\Requests\Akademik\UpdateRppRequest;
use App\Http\Requests\Akademik\VerifyRppRequest;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
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
use Symfony\Component\HttpFoundation\Response;

class RppController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

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
        $kurikulum = $request->query('kurikulum');
        if ($kurikulum !== null && ! in_array($kurikulum, array_column(KurikulumFramework::cases(), 'value'), true)) {
            $kurikulum = null;
        }

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
            kurikulum: $kurikulum,
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

        $guruQuery = Guru::query();
        if ($targetLembagaId) {
            $guruQuery->where('lembaga_id', $targetLembagaId);
        }
        $guruList = $guruQuery->orderByNama()->get();

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
            'guruList' => $guruList,
            'semesterList' => $semesterList,
            'tahunAjaranList' => $tahunAjaranList,
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'tahunAjaranId' => $tahunAjaranId,
            'semesterId' => $semesterId,
            'kelasId' => $kelasId,
            'mapelId' => $mapelId,
            'kurikulum' => $kurikulum,
            'status' => $status,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(StoreRppRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $guru = $user->guru;

        $kelas = Kelas::findOrFail($request->input('kelas_id'));
        $semester = Semester::findOrFail($request->input('semester_id'));

        $guruId = $guru ? $guru->id : (int) $request->input('guru_id');

        if ($guru === null && $request->filled('mata_pelajaran_id')) {
            $mapel = MataPelajaran::find($request->input('mata_pelajaran_id'));
            abort_if($mapel === null || $mapel->lembaga_id !== $kelas->lembaga_id, 404);
        }

        if ($guru !== null) {
            if ($request->filled('mata_pelajaran_id')) {
                $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
                    ->where('kelas_id', $kelas->id)
                    ->where('mata_pelajaran_id', $request->input('mata_pelajaran_id'))
                    ->where('semester_id', $semester->id)
                    ->exists();

                abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi kelas dan mata pelajaran ini.');
            } else {
                abort_unless((int) $kelas->wali_kelas_guru_id === $guru->id, 403, 'RPP tematik tanpa mata pelajaran hanya dapat dibuat oleh wali kelas.');
            }
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

    public function download(Request $request, Rpp $rpp): Response
    {
        $guru = auth()->user()->guru;
        $isPemilik = $guru !== null && $rpp->guru_id === $guru->id;
        abort_unless($isPemilik || auth()->user()->can('rpp.verify'), 403);

        if (! Storage::disk('public')->exists($rpp->file_path)) {
            abort(404, 'Berkas fisik tidak ditemukan di server.');
        }

        if ($request->boolean('inline') || $request->has('preview')) {
            $mime = Storage::disk('public')->mimeType($rpp->file_path) ?: 'application/pdf';

            return response()->file(Storage::disk('public')->path($rpp->file_path), [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.$rpp->file_name.'"',
            ]);
        }

        return Storage::disk('public')->download($rpp->file_path, $rpp->file_name);
    }

    public function update(UpdateRppRequest $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorizeMilikGuru($rpp);

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
        $this->authorizeMilikGuru($rpp);

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

        $effectiveLembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;

        abort_if($effectiveLembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum memverifikasi RPP.');

        try {
            $this->verifyRppAction->execute(
                rpp: $rpp,
                targetStatus: $targetStatus,
                verifierUserId: (int) $request->user()->id,
                verifierLembagaId: (int) $effectiveLembagaId,
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
        $this->authorizeMilikGuru($rpp);

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

    private function authorizeMilikGuru(Rpp $rpp): void
    {
        $guru = auth()->user()->guru;
        abort_if($guru === null || $rpp->guru_id !== $guru->id, 403);
    }
}
