<?php

namespace App\Http\Controllers\Admin\Guru;

use App\Models\Guru;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class JabatanTambahanController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);

        $data = $request->validate([
            'jabatan_tambahan_master_id' => ['required', 'integer', 'exists:jabatan_tambahan_master,id'],
            'mulai_periode' => ['required', 'date'],
            'akhir_periode' => ['nullable', 'date', 'after_or_equal:mulai_periode'],
            'no_sk' => ['nullable', 'string', 'max:100'],
        ]);

        $guru->jabatanTambahan()->attach($data['jabatan_tambahan_master_id'], [
            'mulai_periode' => $data['mulai_periode'],
            'akhir_periode' => $data['akhir_periode'] ?? null,
            'no_sk' => $data['no_sk'] ?? null,
        ]);

        return back()->with('status', 'Jabatan tambahan berhasil ditambahkan.');
    }

    public function destroy(Request $request, Guru $guru, int $jabatanMasterId): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);

        $guru->jabatanTambahan()->detach($jabatanMasterId);

        return back()->with('status', 'Jabatan tambahan berhasil dihapus.');
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
