<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('fase-mapping.create');

        $data = $request->validate([
            'bentuk_pendidikan' => ['required', Rule::in(self::BENTUK_PENDIDIKAN)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'fase_id' => ['required', 'exists:fase,id'],
            'lembaga_id' => ['nullable', 'integer', 'exists:lembaga,id'],
        ]);
        $tingkat = $data['tingkat'] !== '' ? ($data['tingkat'] ?? null) : null;

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
        $lembagaId = $isPlatformOrYayasan ? ($data['lembaga_id'] ?? null) : $request->user()->lembaga_id;

        $this->authorizeMappingScope($request, $lembagaId);

        if (FaseDefaultMapping::where('lembaga_id', $lembagaId)->where('bentuk_pendidikan', $data['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        FaseDefaultMapping::create([
            'lembaga_id' => $lembagaId,
            'bentuk_pendidikan' => $data['bentuk_pendidikan'],
            'tingkat' => $tingkat,
            'fase_id' => $data['fase_id'],
        ]);

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

    public function update(Request $request, FaseDefaultMapping $faseMapping): RedirectResponse
    {
        $this->authorize('fase-mapping.edit');
        $this->authorizeMappingScope($request, $faseMapping->lembaga_id);

        $data = $request->validate([
            'bentuk_pendidikan' => ['required', Rule::in(self::BENTUK_PENDIDIKAN)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'fase_id' => ['required', 'exists:fase,id'],
        ]);
        $tingkat = $data['tingkat'] !== '' ? ($data['tingkat'] ?? null) : null;

        if (FaseDefaultMapping::where('id', '!=', $faseMapping->id)->where('lembaga_id', $faseMapping->lembaga_id)->where('bentuk_pendidikan', $data['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $faseMapping->update([
            'bentuk_pendidikan' => $data['bentuk_pendidikan'],
            'tingkat' => $tingkat,
            'fase_id' => $data['fase_id'],
        ]);

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
