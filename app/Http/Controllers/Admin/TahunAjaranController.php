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
