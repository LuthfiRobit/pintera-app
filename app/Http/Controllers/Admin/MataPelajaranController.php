<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipeMataPelajaran;
use App\Models\MataPelajaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class MataPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('mata-pelajaran.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = MataPelajaran::orderBy('nama');

        if ($search = $request->input('search')) {
            $query->where('nama', 'like', '%' . $search . '%');
        }

        if ($tipe = $request->input('tipe')) {
            $query->where('tipe', $tipe);
        }

        return view('admin.mata-pelajaran.index', [
            'mataPelajaranList' => $query->paginate($perPage)->withQueryString(),
            'tipeList'          => TipeMataPelajaran::cases(),
            'perPage'           => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('mata-pelajaran.create');

        return view('admin.mata-pelajaran.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('mata-pelajaran.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:mapel,aspek_perkembangan'],
        ]);

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat mata pelajaran.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        MataPelajaran::create($data);

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil disimpan.');
    }

    public function edit(MataPelajaran $mataPelajaran): View
    {
        $this->authorize('mata-pelajaran.edit');

        return view('admin.mata-pelajaran.edit', ['mataPelajaran' => $mataPelajaran]);
    }

    public function update(Request $request, MataPelajaran $mataPelajaran): RedirectResponse
    {
        $this->authorize('mata-pelajaran.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:mapel,aspek_perkembangan'],
        ]);

        $mataPelajaran->update($data);

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil diperbarui.');
    }
}
