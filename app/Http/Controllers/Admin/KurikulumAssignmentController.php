<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\KurikulumAssignment\CreateKurikulumAssignmentAction;
use App\Domains\Akademik\Actions\KurikulumAssignment\UpdateKurikulumAssignmentAction;
use App\Domains\Akademik\DataTransferObjects\KurikulumAssignmentData;
use App\Domains\Akademik\Enums\BentukPendidikan;
use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Http\Requests\Akademik\StoreKurikulumAssignmentRequest;
use App\Http\Requests\Akademik\UpdateKurikulumAssignmentRequest;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KurikulumAssignmentController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kurikulum-assignment.view');

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);

        $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);

        if (! $isPlatformOrYayasan) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id);
            });
        }

        return view('admin.kurikulum-assignment.index', [
            'assignmentList' => $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get(),
            'isPlatformOrYayasan' => $isPlatformOrYayasan,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kurikulum-assignment.create');

        return view('admin.kurikulum-assignment.create', [
            'kurikulumList' => KurikulumFramework::cases(),
            'bentukPendidikanList' => BentukPendidikan::cases(),
            'tahunAjaranList' => $this->tahunAjaranListForScope($request),
            'lembagaList' => $this->isPlatformOrYayasan($request) ? Lembaga::orderBy('nama')->get() : collect(),
            'isPlatformOrYayasan' => $this->isPlatformOrYayasan($request),
        ]);
    }

    public function store(StoreKurikulumAssignmentRequest $request, CreateKurikulumAssignmentAction $action): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.create');

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
        $lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;

        $this->authorizeAssignmentScope($request, $lembagaId);

        if ($lembagaId !== null) {
            $tahunAjaranValid = TahunAjaran::whereKey($validated['tahun_ajaran_id'])
                ->where('lembaga_id', $lembagaId)
                ->exists();

            if (! $tahunAjaranValid) {
                return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
            }
        }

        if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->execute(new KurikulumAssignmentData(
            bentukPendidikan: $validated['bentuk_pendidikan'],
            tingkat: $tingkat,
            kurikulum: $validated['kurikulum'],
            lembagaId: $lembagaId,
            tahunAjaranId: (int) $validated['tahun_ajaran_id'],
        ));

        return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil disimpan.');
    }

    public function edit(Request $request, KurikulumAssignment $kurikulumAssignment): View
    {
        $this->authorize('kurikulum-assignment.edit');
        $this->authorizeAssignmentScope($request, $kurikulumAssignment->lembaga_id);

        return view('admin.kurikulum-assignment.edit', [
            'assignment' => $kurikulumAssignment,
            'kurikulumList' => KurikulumFramework::cases(),
            'bentukPendidikanList' => BentukPendidikan::cases(),
        ]);
    }

    public function update(UpdateKurikulumAssignmentRequest $request, KurikulumAssignment $kurikulumAssignment, UpdateKurikulumAssignmentAction $action): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.edit');
        $this->authorizeAssignmentScope($request, $kurikulumAssignment->lembaga_id);

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

        if (KurikulumAssignment::where('id', '!=', $kurikulumAssignment->id)->where('lembaga_id', $kurikulumAssignment->lembaga_id)->where('tahun_ajaran_id', $kurikulumAssignment->tahun_ajaran_id)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->execute($kurikulumAssignment, new KurikulumAssignmentData(
            bentukPendidikan: $validated['bentuk_pendidikan'],
            tingkat: $tingkat,
            kurikulum: $validated['kurikulum'],
            lembagaId: $kurikulumAssignment->lembaga_id,
            tahunAjaranId: $kurikulumAssignment->tahun_ajaran_id,
        ));

        return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil diperbarui.');
    }

    public function destroy(Request $request, KurikulumAssignment $kurikulumAssignment): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.delete');
        $this->authorizeAssignmentScope($request, $kurikulumAssignment->lembaga_id);

        $kurikulumAssignment->delete();

        return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil dihapus.');
    }

    private function isPlatformOrYayasan(Request $request): bool
    {
        return in_array($request->user()->widestScopeLevel(), ['platform', 'yayasan'], true);
    }

    private function authorizeAssignmentScope(Request $request, ?int $lembagaIdDiminta): void
    {
        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);

        if ($lembagaIdDiminta === null) {
            abort_unless($isPlatformOrYayasan, 403);

            return;
        }

        abort_unless($isPlatformOrYayasan || $lembagaIdDiminta === $request->user()->lembaga_id, 403);
    }

    private function tahunAjaranListForScope(Request $request)
    {
        if ($this->isPlatformOrYayasan($request)) {
            return TahunAjaran::orderByDesc('tanggal_mulai')->get();
        }

        return TahunAjaran::where('lembaga_id', $request->user()->lembaga_id)->orderByDesc('tanggal_mulai')->get();
    }
}
