<?php

namespace App\Http\Controllers\Admin;

use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class TahunAjaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('manage-tahun-ajaran');

        return view('admin.tahun-ajaran.index', [
            'tahunAjaranList' => TahunAjaran::with('semester')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-tahun-ajaran');

        return view('admin.tahun-ajaran.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-tahun-ajaran');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:20'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ]);

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat tahun ajaran.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        TahunAjaran::create($data);

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Tahun ajaran berhasil dibuat.');
    }

    public function activate(TahunAjaran $tahunAjaran): RedirectResponse
    {
        $this->authorize('manage-tahun-ajaran');

        $tahunAjaran->activate();

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Tahun ajaran berhasil diaktifkan.');
    }
}
