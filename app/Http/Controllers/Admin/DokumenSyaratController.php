<?php

namespace App\Http\Controllers\Admin;

use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class DokumenSyaratController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('dokumen-syarat.create');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'wajib' => ['nullable', 'boolean'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);

        $dokumen = DokumenSyaratPpdb::create([
            'jalur_ppdb_id' => $jalur->id,
            'nama_dokumen' => $data['nama_dokumen'],
            'wajib' => $request->boolean('wajib', true),
            'urutan' => $jalur->dokumenSyarat()->count(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $dokumen], 201);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil ditambahkan.');
    }

    public function destroy(Request $request, DokumenSyaratPpdb $dokumenSyarat): RedirectResponse|JsonResponse
    {
        $this->authorize('dokumen-syarat.delete');

        $jumlahDokumen = $dokumenSyarat->dokumenPendaftaran()->count();
        if ($jumlahDokumen > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah ada {$jumlahDokumen} dokumen terkait dari calon murid."
            );
        }

        $jalur = $dokumenSyarat->jalurPpdb;
        $dokumenSyarat->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Dokumen syarat berhasil dihapus.']);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['dokumen_syarat' => $message]);
    }
}
