<?php

namespace App\Http\Controllers\Admin;

use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JenisTesMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('manage-ppdb');

        return view('admin.jenis-tes.index', ['jenisTesList' => JenisTesMaster::orderBy('nama')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ]);

        JenisTesMaster::create($data);

        return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil ditambahkan.');
    }

    public function destroy(JenisTesMaster $jenisTes): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        if (SeleksiPpdb::where('jenis_tes_master_id', $jenisTes->id)->exists()) {
            return redirect()->route('admin.jenis-tes.index')
                ->withErrors(['jenis_tes' => 'Jenis tes ini masih dipakai di satu atau lebih jadwal seleksi, tidak bisa dihapus.']);
        }

        $jenisTes->delete();

        return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil dihapus.');
    }
}
