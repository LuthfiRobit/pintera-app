<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\FaseMapping\SetFaseDefaultMappingAction;
use App\Domains\Akademik\Actions\FaseMapping\UpdateFaseDefaultMappingAction;
use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Http\Requests\Akademik\StoreFaseDefaultMappingRequest;
use App\Http\Requests\Akademik\UpdateFaseDefaultMappingRequest;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class FaseDefaultMappingController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    private const BENTUK_PENDIDIKAN = ['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB'];

    public function index(Request $request): View
    {
        $this->authorize('fase-mapping.view');

        $scope = $request->user()->widestScopeLevel();
        $query = FaseDefaultMapping::with(['fase', 'lembaga']);

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

        return view('admin.fase-mapping.index', [
            'mappingList' => $query->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get(),
            'isPlatformOrYayasan' => in_array($scope, ['platform', 'yayasan'], true),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('fase-mapping.create');

        $isPlatform = $request->user()->widestScopeLevel() === 'platform';

        return view('admin.fase-mapping.create', [
            'faseList' => Fase::orderBy('urutan')->get(),
            'lembagaList' => $isPlatform ? Lembaga::orderBy('nama')->get() : collect(),
            'isPlatform' => $isPlatform,
            'bentukPendidikanList' => self::BENTUK_PENDIDIKAN,
        ]);
    }

    public function store(StoreFaseDefaultMappingRequest $request, SetFaseDefaultMappingAction $action): RedirectResponse
    {
        $this->authorize('fase-mapping.create');

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? ($validated['tingkat'] ?? null) : null;
        $lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;
        $lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);

        if (FaseDefaultMapping::where('lembaga_id', $lembagaId)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->executeCreate($request->user(), $validated['bentuk_pendidikan'], $tingkat, (int) $validated['fase_id'], $lembagaIdDiminta);

        return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil disimpan.');
    }

    public function edit(Request $request, FaseDefaultMapping $faseMapping): View
    {
        $this->authorize('fase-mapping.edit');
        $this->authorizeExistingMappingScope($request->user(), $faseMapping->lembaga_id);

        $isPlatform = $request->user()->widestScopeLevel() === 'platform';

        return view('admin.fase-mapping.edit', [
            'mapping' => $faseMapping->loadMissing('fase', 'lembaga'),
            'faseList' => Fase::orderBy('urutan')->get(),
            'bentukPendidikanList' => self::BENTUK_PENDIDIKAN,
            'isPlatform' => $isPlatform,
        ]);
    }

    public function update(UpdateFaseDefaultMappingRequest $request, FaseDefaultMapping $faseMapping, UpdateFaseDefaultMappingAction $action): RedirectResponse
    {
        $this->authorize('fase-mapping.edit');
        $this->authorizeExistingMappingScope($request->user(), $faseMapping->lembaga_id);

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? ($validated['tingkat'] ?? null) : null;

        if (FaseDefaultMapping::where('id', '!=', $faseMapping->id)->where('lembaga_id', $faseMapping->lembaga_id)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->execute($faseMapping, new FaseDefaultMappingData(
            bentukPendidikan: $validated['bentuk_pendidikan'],
            tingkat: $tingkat,
            faseId: (int) $validated['fase_id'],
            lembagaId: $faseMapping->lembaga_id,
        ));

        return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil diperbarui.');
    }

    public function destroy(Request $request, FaseDefaultMapping $faseMapping): RedirectResponse
    {
        $this->authorize('fase-mapping.delete');
        $this->authorizeExistingMappingScope($request->user(), $faseMapping->lembaga_id);

        $faseMapping->delete();

        return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil dihapus.');
    }

    private function authorizeExistingMappingScope(User $actor, ?int $existingLembagaId): void
    {
        if ($actor->widestScopeLevel() === 'platform') {
            return;
        }

        if ($existingLembagaId === null) {
            abort(403, 'Mapping global hanya bisa diubah/dihapus oleh Platform Admin.');
        }

        if ($actor->widestScopeLevel() === 'yayasan') {
            $milikYayasan = Lembaga::where('id', $existingLembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
            abort_unless($milikYayasan, 403, 'Mapping ini bukan milik yayasan Anda.');

            return;
        }

        abort_unless($existingLembagaId === $actor->lembaga_id, 403);
    }
}
