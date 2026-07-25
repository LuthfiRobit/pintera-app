<?php

namespace App\Http\Controllers\Admin;

use App\Models\PolaJam;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PolaJamController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('pola-jam.view');

        return view('admin.pola-jam.index', [
            'polaJamList' => PolaJam::with(['jamPelajaran', 'lembaga'])->orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('pola-jam.create');

        return view('admin.pola-jam.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        PolaJam::create($data);

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dibuat.');
    }
}
