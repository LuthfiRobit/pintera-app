<?php

namespace App\Http\Controllers\Admin;

use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JenisTesMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('jenis-tes.view');

        return view('admin.jenis-tes.index', ['jenisTesList' => JenisTesMaster::orderBy('nama')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jenis-tes.create');

        $isYayasanScope = $request->user()->widestScopeLevel() === 'yayasan';

        if ($isYayasanScope) {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tes.'])->withInput();
            }
        } else {
            $lembagaId = $request->user()->lembaga_id;
        }

        $data = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('jenis_tes_master', 'nama')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($isYayasanScope) {
            $data['lembaga_id'] = $lembagaId;
        }

        JenisTesMaster::create($data);

        return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil ditambahkan.');
    }

    public function destroy(JenisTesMaster $jenisTes): RedirectResponse
    {
        $this->authorize('jenis-tes.delete');

        if (SeleksiPpdb::where('jenis_tes_master_id', $jenisTes->id)->exists()) {
            return redirect()->route('admin.jenis-tes.index')
                ->withErrors(['jenis_tes' => 'Jenis tes ini masih dipakai di satu atau lebih jadwal seleksi, tidak bisa dihapus.']);
        }

        $jenisTes->delete();

        return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil dihapus.');
    }
}
