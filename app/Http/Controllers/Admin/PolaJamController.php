<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\PolaJam\AssignKelasToPolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\CreatePolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\DeletePolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\DuplicatePolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\UpdatePolaJamAction;
use App\Domains\Akademik\DataTransferObjects\AssignKelasData;
use App\Domains\Akademik\DataTransferObjects\PolaJamData;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Kelas;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PolaJamController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    public function index(): View
    {
        $this->authorize('pola-jam.view');

        return view('portals.lembaga.akademik.pola-jam.index', [
            'polaJamList' => PolaJam::with(['jamPelajaran', 'lembaga', 'kelas.tahunAjaran'])->orderBy('nama')->get(),
            'kelasList' => Kelas::with(['tahunAjaran', 'polaJam'])->orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('pola-jam.create');

        return view('portals.lembaga.akademik.pola-jam.create');
    }

    public function store(Request $request, CreatePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
        }

        $action->execute(new PolaJamData(nama: $data['nama'], lembagaId: $lembagaId));

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dibuat.');
    }

    public function edit(PolaJam $polaJam): View
    {
        $this->authorize('pola-jam.edit');

        return view('portals.lembaga.akademik.pola-jam.edit', ['polaJam' => $polaJam]);
    }

    public function update(Request $request, PolaJam $polaJam, UpdatePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $action->execute($polaJam, new PolaJamData(nama: $data['nama'], lembagaId: $polaJam->lembaga_id));

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil diperbarui.');
    }

    public function destroy(PolaJam $polaJam, DeletePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.delete');

        try {
            $action->execute($polaJam);
        } catch (ValidationException $e) {
            return back()->withErrors(['pola_jam' => $e->validator->errors()->first('pola_jam')]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dihapus.');
    }

    public function assignKelas(Request $request, PolaJam $polaJam, AssignKelasToPolaJamAction $action): RedirectResponse
    {
        $this->authorize('kelas.edit');

        $data = $request->validate([
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => ['integer'],
        ]);

        try {
            $action->execute($polaJam, new AssignKelasData(kelasIds: $data['kelas_ids'] ?? []));
        } catch (ValidationException $e) {
            return back()->withErrors(['kelas_ids' => $e->validator->errors()->first('kelas_ids')]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Tautan kelas untuk pola jam ini berhasil disimpan.');
    }

    public function duplicate(PolaJam $polaJam, DuplicatePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        [$newPola, $count] = $action->execute($polaJam);

        return redirect()->route('admin.pola-jam.index')->with('status', "Pola jam \"{$polaJam->nama}\" beserta {$count} slot jam berhasil diduplikasi.");
    }
}
