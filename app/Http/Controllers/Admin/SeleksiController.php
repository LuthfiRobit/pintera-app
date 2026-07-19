<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class SeleksiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('seleksi.create');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'gelombang_ppdb_id' => ['required', Rule::exists('gelombang_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', GelombangPpdb::pluck('id')))],
            'jenis_tes_master_id' => ['required', Rule::exists('jenis_tes_master', 'id')->where(fn ($query) => $query->whereIn('id', JenisTesMaster::pluck('id')))],
            'jadwal' => ['required', 'date'],
            'kriteria_kelulusan' => ['nullable', 'string', 'max:2000'],
            'bobot' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);
        $gelombang = GelombangPpdb::findOrFail($data['gelombang_ppdb_id']);

        if ($gelombang->tahun_ajaran_id !== $jalur->tahun_ajaran_id) {
            $message = 'Gelombang yang dipilih bukan dari tahun ajaran yang sama dengan jalur ini.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'errors' => ['gelombang_ppdb_id' => [$message]]], 422);
            }

            return back()->withErrors(['gelombang_ppdb_id' => $message])->withInput();
        }

        $seleksi = SeleksiPpdb::create($data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $seleksi->load(['gelombangPpdb', 'jenisTesMaster'])], 201);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Jadwal seleksi berhasil ditambahkan.');
    }

    public function destroy(Request $request, SeleksiPpdb $seleksi): RedirectResponse|JsonResponse
    {
        $this->authorize('seleksi.delete');

        $jumlahHasil = $seleksi->hasilSeleksi()->count();
        if ($jumlahHasil > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah ada {$jumlahHasil} hasil penilaian terkait dari calon murid."
            );
        }

        $jalur = $seleksi->jalurPpdb;
        $seleksi->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jadwal seleksi berhasil dihapus.']);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Jadwal seleksi berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['seleksi' => $message]);
    }
}
