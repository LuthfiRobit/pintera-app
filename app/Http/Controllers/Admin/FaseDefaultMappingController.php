<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\FaseMapping\CreateFaseDefaultMappingAction;
use App\Domains\Akademik\Actions\FaseMapping\UpdateFaseDefaultMappingAction;
use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Http\Requests\Akademik\StoreFaseDefaultMappingRequest;
use App\Http\Requests\Akademik\UpdateFaseDefaultMappingRequest;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class FaseDefaultMappingController extends BaseController
{
    use AuthorizesRequests;

    private const BENTUK_PENDIDIKAN = ['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB'];

    public function index(Request $request): View
    {
        $this->authorize('fase-mapping.view');

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);

        $query = FaseDefaultMapping::with(['fase', 'lembaga']);

        if (! $isPlatformOrYayasan) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id);
            });
        }

        return view('admin.fase-mapping.index', [
            'mappingList' => $query->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get(),
            'isPlatformOrYayasan' => $isPlatformOrYayasan,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('fase-mapping.create');

        return view('admin.fase-mapping.create', [
            'faseList' => Fase::orderBy('urutan')->get(),
            'lembagaList' => $this->isPlatformOrYayasan($request) ? Lembaga::orderBy('nama')->get() : collect(),
            'isPlatformOrYayasan' => $this->isPlatformOrYayasan($request),
            'bentukPendidikanList' => self::BENTUK_PENDIDIKAN,
        ]);
    }

    public function store(StoreFaseDefaultMappingRequest $request, CreateFaseDefaultMappingAction $action): RedirectResponse
    {
        $this->authorize('fase-mapping.create');

        $validated = $request->validated();
        $tingkat = $validated['tingkat'] !== '' ? ($validated['tingkat'] ?? null) : null;

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
        $lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;

        $this->authorizeMappingScope($request, $lembagaId);

        if (FaseDefaultMapping::where('lembaga_id', $lembagaId)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->execute(new FaseDefaultMappingData(
            bentukPendidikan: $validated['bentuk_pendidikan'],
            tingkat: $tingkat,
            faseId: (int) $validated['fase_id'],
            lembagaId: $lembagaId,
        ));

        return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil disimpan.');
    }

    public function edit(Request $request, FaseDefaultMapping $faseMapping): View
    {
        $this->authorize('fase-mapping.edit');
        $this->authorizeMappingScope($request, $faseMapping->lembaga_id);

        return view('admin.fase-mapping.edit', [
            'mapping' => $faseMapping,
            'faseList' => Fase::orderBy('urutan')->get(),
            'bentukPendidikanList' => self::BENTUK_PENDIDIKAN,
        ]);
    }

    public function update(UpdateFaseDefaultMappingRequest $request, FaseDefaultMapping $faseMapping, UpdateFaseDefaultMappingAction $action): RedirectResponse
    {
        $this->authorize('fase-mapping.edit');
        $this->authorizeMappingScope($request, $faseMapping->lembaga_id);

        $validated = $request->validated();
        $tingkat = $validated['tingkat'] !== '' ? ($validated['tingkat'] ?? null) : null;

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
        $this->authorizeMappingScope($request, $faseMapping->lembaga_id);

        $faseMapping->delete();

        return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil dihapus.');
    }

    private function isPlatformOrYayasan(Request $request): bool
    {
        return in_array($request->user()->widestScopeLevel(), ['platform', 'yayasan'], true);
    }

    private function authorizeMappingScope(Request $request, ?int $lembagaIdDiminta): void
    {
        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);

        if ($lembagaIdDiminta === null) {
            abort_unless($isPlatformOrYayasan, 403);

            return;
        }

        abort_unless($isPlatformOrYayasan || $lembagaIdDiminta === $request->user()->lembaga_id, 403);
    }
}
