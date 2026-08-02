<?php

namespace App\Http\Controllers\Admin\Guru;

use App\Models\Guru;
use App\Models\SertifikasiGuru;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class SertifikasiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);

        $data = $request->validate([
            'jenis_sertifikasi' => ['required', 'string', 'max:100'],
            'nomor_sertifikat' => ['required', 'string', 'max:100'],
            'tahun_sertifikasi' => ['required', 'integer', 'min:1970', 'max:2050'],
            'bidang_studi_sertifikasi' => ['nullable', 'string', 'max:255'],
            'nrg' => ['nullable', 'string', 'max:50'],
            'kode_lembaga_sertifikasi' => ['nullable', 'string', 'max:100'],
        ]);

        $guru->sertifikasi()->create($data);

        return back()->with('status', 'Data sertifikasi berhasil ditambahkan.');
    }

    public function update(Request $request, Guru $guru, SertifikasiGuru $sertifikasi): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($sertifikasi->guru_id !== $guru->id, 404);

        $data = $request->validate([
            'jenis_sertifikasi' => ['required', 'string', 'max:100'],
            'nomor_sertifikat' => ['required', 'string', 'max:100'],
            'tahun_sertifikasi' => ['required', 'integer', 'min:1970', 'max:2050'],
            'bidang_studi_sertifikasi' => ['nullable', 'string', 'max:255'],
            'nrg' => ['nullable', 'string', 'max:50'],
            'kode_lembaga_sertifikasi' => ['nullable', 'string', 'max:100'],
        ]);

        $sertifikasi->update($data);

        return back()->with('status', 'Data sertifikasi berhasil diperbarui.');
    }

    public function destroy(Request $request, Guru $guru, SertifikasiGuru $sertifikasi): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($sertifikasi->guru_id !== $guru->id, 404);

        $sertifikasi->delete();

        return back()->with('status', 'Data sertifikasi berhasil dihapus.');
    }

    private function ensureTenantScope(Request $request, Guru $guru): void
    {
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        if ($lembagaId !== null && $guru->lembaga_id !== $lembagaId) {
            abort(404);
        }
    }
}
