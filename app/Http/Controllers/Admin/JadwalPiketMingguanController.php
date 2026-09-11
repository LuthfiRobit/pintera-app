<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Actions\Piket\RegenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JadwalPiketMingguanController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    private function scopeHeaderData(Request $request): array
    {
        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        $activeLembagaId = $isYayasan ? $this->resolveActiveLembagaId($request->user()) : $request->user()->lembaga_id;
        $isYayasanAggregate = $isYayasan && $activeLembagaId === null;

        return [
            'isYayasan' => $isYayasan,
            'activeLembaga' => ($isYayasan && $activeLembagaId) ? Lembaga::withoutGlobalScopes()->find($activeLembagaId) : null,
            'isYayasanAggregate' => $isYayasanAggregate,
        ];
    }

    public function index(Request $request): View|string
    {
        $this->authorize('piket.kelola');

        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        $activeLembagaId = $isYayasan ? $this->resolveActiveLembagaId($request->user()) : $request->user()->lembaga_id;
        $isYayasanAggregate = $isYayasan && $activeLembagaId === null;

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50], true) ? (int) $request->input('per_page') : 20;

        // Parameter query filter
        $filterLembagaId = $request->query('lembaga_id');
        $filterHari = $request->query('hari');
        $filterSemesterId = $request->query('semester_id');
        $search = $request->query('search');

        $targetLembagaId = $activeLembagaId ?? ($filterLembagaId ? (int) $filterLembagaId : null);

        $jadwalList = JadwalPiketMingguan::with(['guru.person', 'semester.tahunAjaran', 'lembaga'])
            ->when($targetLembagaId, fn ($q) => $q->where('lembaga_id', $targetLembagaId))
            ->when($filterHari !== null && $filterHari !== '', fn ($q) => $q->where('hari', (int) $filterHari))
            ->when($filterSemesterId, fn ($q) => $q->where('semester_id', (int) $filterSemesterId))
            ->when($search, fn ($q) => $q->whereHas('guru.person', fn ($q2) => $q2->where('nama_lengkap', 'like', "%{$search}%")))
            ->orderBy('hari')
            ->get();

        $overrides = PiketHarian::where('sumber', 'override_manual')
            ->where('tanggal', '>=', now()->toDateString())
            ->with(['guru.person', 'lembaga'])
            ->when($targetLembagaId, fn ($q) => $q->where('lembaga_id', $targetLembagaId))
            ->when($search, fn ($q) => $q->whereHas('guru.person', fn ($q2) => $q2->where('nama_lengkap', 'like', "%{$search}%")))
            ->orderBy('tanggal')
            ->get();

        $piketHarianMendatang = PiketHarian::where('tanggal', '>=', now()->toDateString())
            ->with(['guru.person', 'lembaga'])
            ->when($targetLembagaId, fn ($q) => $q->where('lembaga_id', $targetLembagaId))
            ->when($search, fn ($q) => $q->whereHas('guru.person', fn ($q2) => $q2->where('nama_lengkap', 'like', "%{$search}%")))
            ->orderBy('tanggal')
            ->paginate($perPage, ['*'], 'piket_page')
            ->withQueryString();

        $guruList = $targetLembagaId
            ? Guru::where('lembaga_id', $targetLembagaId)->orderByNama()->get()
            : Guru::with('lembaga')->orderByNama()->get();

        $semesterList = $targetLembagaId
            ? $this->semesterListUntukLembaga($targetLembagaId)
            : Semester::with('tahunAjaran', 'lembaga')->where('status_aktif', true)->get();

        $lembagaList = $isYayasan
            ? Lembaga::where('yayasan_id', $request->user()->yayasan_id)->orderBy('nama')->get()
            : collect();

        $stats = [
            'totalJadwal' => $jadwalList->count(),
            'guruTerjadwal' => $jadwalList->pluck('guru_id')->unique()->count(),
            'overrideAktif' => $overrides->count(),
            'lembagaTerjadwal' => $isYayasanAggregate
                ? $jadwalList->pluck('lembaga_id')->unique()->count()
                : $jadwalList->pluck('hari')->unique()->count(),
        ];

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.akademik.piket-guru._daftar', [
                'jadwalList' => $jadwalList,
                'overrides' => $overrides,
                'piketHarianMendatang' => $piketHarianMendatang,
                'guruList' => $guruList,
                'perPage' => $perPage,
                ...$this->scopeHeaderData($request),
            ]);
        }

        return view('portals.lembaga.akademik.piket-guru.index', [
            'jadwalList' => $jadwalList,
            'overrides' => $overrides,
            'piketHarianMendatang' => $piketHarianMendatang,
            'guruList' => $guruList,
            'semesterList' => $semesterList,
            'lembagaList' => $lembagaList,
            'stats' => $stats,
            'perPage' => $perPage,
            'filters' => [
                'search' => $search,
                'hari' => $filterHari,
                'semester_id' => $filterSemesterId,
                'lembaga_id' => $filterLembagaId,
            ],
            ...$this->scopeHeaderData($request),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('piket.kelola');

        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        $activeLembagaId = $isYayasan ? $this->resolveActiveLembagaId($request->user()) : $request->user()->lembaga_id;

        if ($activeLembagaId === null) {
            return redirect()->route('admin.piket-guru.index')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jadwal piket.']);
        }

        return view('portals.lembaga.akademik.piket-guru.create', [
            'guruList' => Guru::where('lembaga_id', $activeLembagaId)->orderByNama()->get(),
            'semesterList' => $this->semesterListUntukLembaga($activeLembagaId),
            'semesterAktif' => Semester::where('lembaga_id', $activeLembagaId)->where('status_aktif', true)->first(),
            ...$this->scopeHeaderData($request),
        ]);
    }

    public function store(Request $request, GenerateJadwalPiketHarianAction $generateAction, RegenerateJadwalPiketHarianAction $regenerateAction): RedirectResponse|JsonResponse
    {
        $this->authorize('piket.kelola');

        $lembagaId = $this->resolveLembagaIdAktif($request);
        if ($lembagaId === null) {
            $msg = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jadwal piket.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['lembaga_id' => [$msg]]], 422);
            }

            return redirect()->route('admin.piket-guru.index')->withErrors(['lembaga_id' => $msg])->withInput();
        }

        try {
            $data = $request->validate([
                'guru_id' => ['required', 'integer', Rule::exists('guru', 'id')->where('lembaga_id', $lembagaId)],
                'hari' => ['required', 'integer', 'between:0,6'],
                'semester_id' => ['required', 'integer', Rule::exists('semester', 'id')->where('lembaga_id', $lembagaId)],
            ]);
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
            }

            throw $e;
        }

        $jadwal = JadwalPiketMingguan::create([
            'lembaga_id' => $lembagaId,
            'guru_id' => $data['guru_id'],
            'hari' => $data['hari'],
            'semester_id' => $data['semester_id'],
            'dibuat_oleh_user_id' => $request->user()->id,
        ]);

        $semester = Semester::findOrFail($data['semester_id']);
        $sudahAdaPiketHarian = PiketHarian::where('lembaga_id', $lembagaId)
            ->where('tanggal', '>=', $semester->tanggal_mulai)
            ->exists();

        if ($sudahAdaPiketHarian) {
            $regenerateAction->execute($lembagaId, $data['semester_id']);
        } else {
            $generateAction->execute($jadwal);
        }

        $message = 'Jadwal piket mingguan berhasil disimpan.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $message]);
        }

        return redirect()->route('admin.piket-guru.index')->with('status', $message);
    }

    public function edit(JadwalPiketMingguan $jadwalPiketMingguan, Request $request): View|RedirectResponse
    {
        $this->authorize('piket.kelola');

        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        if ($isYayasan) {
            $isMilikYayasan = $jadwalPiketMingguan->lembaga && $jadwalPiketMingguan->lembaga->yayasan_id === $request->user()->yayasan_id;
            if (! $isMilikYayasan) {
                return redirect()->route('admin.piket-guru.index')
                    ->withErrors(['lembaga_id' => 'Jadwal piket tidak ditemukan atau di luar wewenang yayasan Anda.']);
            }
        } else {
            if ($jadwalPiketMingguan->lembaga_id !== $request->user()->lembaga_id) {
                return redirect()->route('admin.piket-guru.index')
                    ->withErrors(['lembaga_id' => 'Jadwal piket bukan milik lembaga Anda.']);
            }
        }

        $lembagaId = $jadwalPiketMingguan->lembaga_id;

        return view('portals.lembaga.akademik.piket-guru.edit', [
            'jadwal' => $jadwalPiketMingguan,
            'guruList' => Guru::where('lembaga_id', $lembagaId)->orderByNama()->get(),
            'semesterList' => $this->semesterListUntukLembaga($lembagaId),
            ...$this->scopeHeaderData($request),
        ]);
    }

    public function update(Request $request, JadwalPiketMingguan $jadwalPiketMingguan, GenerateJadwalPiketHarianAction $generateAction, RegenerateJadwalPiketHarianAction $regenerateAction): RedirectResponse|JsonResponse
    {
        $this->authorize('piket.kelola');

        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        if ($isYayasan) {
            $isMilikYayasan = $jadwalPiketMingguan->lembaga && $jadwalPiketMingguan->lembaga->yayasan_id === $request->user()->yayasan_id;
            if (! $isMilikYayasan) {
                $msg = 'Jadwal piket tidak ditemukan atau di luar wewenang yayasan Anda.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['lembaga_id' => [$msg]]], 422);
                }

                return redirect()->route('admin.piket-guru.index')->withErrors(['lembaga_id' => $msg]);
            }
        } else {
            if ($jadwalPiketMingguan->lembaga_id !== $request->user()->lembaga_id) {
                $msg = 'Jadwal piket bukan milik lembaga Anda.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['lembaga_id' => [$msg]]], 422);
                }

                return redirect()->route('admin.piket-guru.index')->withErrors(['lembaga_id' => $msg]);
            }
        }

        $lembagaId = $jadwalPiketMingguan->lembaga_id;

        try {
            $data = $request->validate([
                'guru_id' => ['required', 'integer', Rule::exists('guru', 'id')->where('lembaga_id', $lembagaId)],
                'hari' => ['required', 'integer', 'between:0,6'],
                'semester_id' => ['required', 'integer', Rule::exists('semester', 'id')->where('lembaga_id', $lembagaId)],
            ]);
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
            }

            throw $e;
        }

        $semesterLamaId = $jadwalPiketMingguan->semester_id;
        $semesterBerubah = (int) $data['semester_id'] !== $semesterLamaId;

        $jadwalPiketMingguan->update($data);

        if ($semesterBerubah) {
            $regenerateAction->execute($lembagaId, $semesterLamaId);

            $semesterBaru = Semester::findOrFail($data['semester_id']);
            $sudahAdaPiketHarian = PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', $semesterBaru->tanggal_mulai)
                ->exists();

            if ($sudahAdaPiketHarian) {
                $regenerateAction->execute($lembagaId, $data['semester_id']);
            } else {
                $generateAction->execute($jadwalPiketMingguan);
            }
        } else {
            $regenerateAction->execute($lembagaId, $jadwalPiketMingguan->semester_id);
        }

        $message = 'Jadwal piket mingguan berhasil diperbarui.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $message]);
        }

        return redirect()->route('admin.piket-guru.index')->with('status', $message);
    }

    public function destroy(JadwalPiketMingguan $jadwalPiketMingguan, Request $request, RegenerateJadwalPiketHarianAction $regenerateAction): RedirectResponse
    {
        $this->authorize('piket.kelola');

        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        if ($isYayasan) {
            $isMilikYayasan = $jadwalPiketMingguan->lembaga && $jadwalPiketMingguan->lembaga->yayasan_id === $request->user()->yayasan_id;
            if (! $isMilikYayasan) {
                return redirect()->route('admin.piket-guru.index')
                    ->withErrors(['lembaga_id' => 'Jadwal piket tidak ditemukan atau di luar wewenang yayasan Anda.']);
            }
        } else {
            if ($jadwalPiketMingguan->lembaga_id !== $request->user()->lembaga_id) {
                return redirect()->route('admin.piket-guru.index')
                    ->withErrors(['lembaga_id' => 'Jadwal piket bukan milik lembaga Anda.']);
            }
        }

        $lembagaId = $jadwalPiketMingguan->lembaga_id;
        $semesterId = $jadwalPiketMingguan->semester_id;
        $jadwalPiketMingguan->delete();

        $regenerateAction->execute($lembagaId, $semesterId);

        return redirect()->route('admin.piket-guru.index')->with('status', 'Jadwal piket mingguan berhasil dihapus.');
    }

    private function resolveLembagaIdAktif(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;
    }

    /**
     * @return Collection<int, Semester>
     */
    private function semesterListUntukLembaga(int $lembagaId): Collection
    {
        return Semester::where('semester.lembaga_id', $lembagaId)
            ->with('tahunAjaran')
            ->join('tahun_ajaran', 'tahun_ajaran.id', '=', 'semester.tahun_ajaran_id')
            ->orderByDesc('tahun_ajaran.tanggal_mulai')
            ->orderBy('semester.urutan')
            ->select('semester.*')
            ->get();
    }
}
