<?php

namespace App\Http\Controllers\Admin;

use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class DokumenSyaratController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('dokumen-syarat.create');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'wajib' => ['nullable', 'boolean'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);

        DokumenSyaratPpdb::create([
            'jalur_ppdb_id' => $jalur->id,
            'nama_dokumen' => $data['nama_dokumen'],
            'wajib' => $request->boolean('wajib', true),
            'urutan' => $jalur->dokumenSyarat()->count(),
        ]);

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil ditambahkan.');
    }

    public function destroy(DokumenSyaratPpdb $dokumenSyarat): RedirectResponse
    {
        $this->authorize('dokumen-syarat.delete');

        $jalur = $dokumenSyarat->jalurPpdb;
        $dokumenSyarat->delete();

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil dihapus.');
    }
}
