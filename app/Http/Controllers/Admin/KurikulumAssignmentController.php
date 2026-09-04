<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\KurikulumAssignment\AssignKurikulumAction;
use App\Domains\Akademik\Actions\KurikulumAssignment\UpdateKurikulumAssignmentAction;
use App\Domains\Akademik\DataTransferObjects\KurikulumAssignmentData;
use App\Domains\Akademik\Enums\BentukPendidikan;
use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Http\Requests\Akademik\StoreKurikulumAssignmentRequest;
use App\Http\Requests\Akademik\UpdateKurikulumAssignmentRequest;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KurikulumAssignmentController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    public function index(Request $request): View
    {
        $this->authorize('kurikulum-assignment.view');

        $scope = $request->user()->widestScopeLevel();
        $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);

        if ($scope === 'yayasan') {
            $lembagaIds = Lembaga::where('yayasan_id', $request->user()->yayasan_id)->pluck('id');
            $query->where(function ($q) use ($lembagaIds) {
                $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
            });
        } elseif ($scope !== 'platform') {
            $query->where(function ($q) use ($request) {
                $q->whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id);
            });
        }

        return view('admin.kurikulum-assignment.index', [
            'assignmentList' => $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get(),
            'isPlatformOrYayasan' => in_array($scope, ['platform', 'yayasan'], true),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kurikulum-assignment.create');

        $isPlatform = $request->user()->widestScopeLevel() === 'platform';

        return view('admin.kurikulum-assignment.create', [
            'kurikulumList' => KurikulumFramework::cases(),
            'bentukPendidikanList' => BentukPendidikan::cases(),
            'tahunAjaranList' => $this->tahunAjaranListForScope($request),
            'lembagaList' => $isPlatform ? Lembaga::orderBy('nama')->get() : collect(),
            'isPlatform' => $isPlatform,
        ]);
    }

    public function store(StoreKurikulumAssignmentRequest $request, AssignKurikulumAction $action): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.create');

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;
        $lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;
        $lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);

        if ($lembagaId !== null) {
            $tahunAjaranValid = TahunAjaran::withoutGlobalScope(TenantScope::class)
                ->whereKey($validated['tahun_ajaran_id'])
                ->where('lembaga_id', $lembagaId)
                ->exists();

            if (! $tahunAjaranValid) {
                return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
            }
        }

        if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->executeCreate($request->user(), $validated['bentuk_pendidikan'], $tingkat, $validated['kurikulum'], $lembagaIdDiminta, (int) $validated['tahun_ajaran_id']);

        return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil disimpan.');
    }

    public function edit(Request $request, KurikulumAssignment $kurikulumAssignment): View
    {
        $this->authorize('kurikulum-assignment.edit');
        $this->authorizeExistingAssignmentScope($request->user(), $kurikulumAssignment->lembaga_id);

        $isPlatform = $request->user()->widestScopeLevel() === 'platform';

        return view('admin.kurikulum-assignment.edit', [
            'assignment' => $kurikulumAssignment->loadMissing('tahunAjaran', 'lembaga'),
            'kurikulumList' => KurikulumFramework::cases(),
            'bentukPendidikanList' => BentukPendidikan::cases(),
            'isPlatform' => $isPlatform,
        ]);
    }

    public function update(UpdateKurikulumAssignmentRequest $request, KurikulumAssignment $kurikulumAssignment, UpdateKurikulumAssignmentAction $action): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.edit');
        $this->authorizeExistingAssignmentScope($request->user(), $kurikulumAssignment->lembaga_id);

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
        $this->authorizeExistingAssignmentScope($request->user(), $kurikulumAssignment->lembaga_id);

        if ($kurikulumAssignment->lembaga_id !== null) {
            $jumlahKelasTerdampak = Kelas::withoutGlobalScope(TenantScope::class)
                ->where('lembaga_id', $kurikulumAssignment->lembaga_id)
                ->where('tahun_ajaran_id', $kurikulumAssignment->tahun_ajaran_id)
                ->where('tingkat', $kurikulumAssignment->tingkat)
                ->where('kurikulum', $kurikulumAssignment->kurikulum)
                ->count();

            if ($jumlahKelasTerdampak > 0) {
                return redirect()->route('admin.kurikulum-assignment.index')
                    ->with('error', "Assignment ini masih dipakai {$jumlahKelasTerdampak} Kelas. Reassign kelas-kelas itu dulu, atau gunakan tool \"Cek & Perbaiki Kurikulum/Fase\".");
            }
        }

        $kurikulumAssignment->delete();

        return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil dihapus.');
    }

    private function authorizeExistingAssignmentScope(User $actor, ?int $existingLembagaId): void
    {
        if ($actor->widestScopeLevel() === 'platform') {
            return;
        }

        if ($existingLembagaId === null) {
            abort(403, 'Assignment global hanya bisa diubah/dihapus oleh Platform Admin.');
        }

        if ($actor->widestScopeLevel() === 'yayasan') {
            $milikYayasan = Lembaga::where('id', $existingLembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
            abort_unless($milikYayasan, 403, 'Assignment ini bukan milik yayasan Anda.');

            return;
        }

        abort_unless($existingLembagaId === $actor->lembaga_id, 403);
    }

    private function tahunAjaranListForScope(Request $request)
    {
        $scope = $request->user()->widestScopeLevel();

        if ($scope === 'platform') {
            return TahunAjaran::orderByDesc('tanggal_mulai')->get();
        }

        if ($scope === 'yayasan') {
            $activeLembagaId = session('active_lembaga_id');
            if ($activeLembagaId) {
                return TahunAjaran::where('lembaga_id', $activeLembagaId)->orderByDesc('tanggal_mulai')->get();
            }

            $lembagaIds = Lembaga::where('yayasan_id', $request->user()->yayasan_id)->pluck('id');

            return TahunAjaran::whereIn('lembaga_id', $lembagaIds)->orderByDesc('tanggal_mulai')->get();
        }

        return TahunAjaran::where('lembaga_id', $request->user()->lembaga_id)->orderByDesc('tanggal_mulai')->get();
    }
}
