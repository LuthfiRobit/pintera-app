<?php

namespace App\Http\Controllers\Admin\Guru;

use App\Models\Guru;
use App\Models\RiwayatPendidikanGuru;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class RiwayatPendidikanController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);

        $data = $request->validate([
            'jenjang_pendidikan' => ['required', 'string', 'max:50'],
            'sekolah_formal' => ['required', 'string', 'max:255'],
            'bidang_studi' => ['nullable', 'string', 'max:255'],
            'gelar_akademik' => ['nullable', 'string', 'max:50'],
            'fakultas' => ['nullable', 'string', 'max:255'],
            'tahun_masuk' => ['nullable', 'integer', 'min:1950', 'max:2050'],
            'tahun_lulus' => ['required', 'integer', 'min:1950', 'max:2050'],
            'kependidikan' => ['nullable', 'boolean'],
        ]);

        $data['kependidikan'] = $data['kependidikan'] ?? false;
        $guru->riwayatPendidikan()->create($data);

        return back()->with('status', 'Riwayat pendidikan berhasil ditambahkan.');
    }

    public function update(Request $request, Guru $guru, RiwayatPendidikanGuru $riwayatPendidikan): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($riwayatPendidikan->guru_id !== $guru->id, 404);

        $data = $request->validate([
            'jenjang_pendidikan' => ['required', 'string', 'max:50'],
            'sekolah_formal' => ['required', 'string', 'max:255'],
            'bidang_studi' => ['nullable', 'string', 'max:255'],
            'gelar_akademik' => ['nullable', 'string', 'max:50'],
            'fakultas' => ['nullable', 'string', 'max:255'],
            'tahun_masuk' => ['nullable', 'integer', 'min:1950', 'max:2050'],
            'tahun_lulus' => ['required', 'integer', 'min:1950', 'max:2050'],
            'kependidikan' => ['nullable', 'boolean'],
        ]);

        $data['kependidikan'] = $data['kependidikan'] ?? false;
        $riwayatPendidikan->update($data);

        return back()->with('status', 'Riwayat pendidikan berhasil diperbarui.');
    }

    public function destroy(Request $request, Guru $guru, RiwayatPendidikanGuru $riwayatPendidikan): RedirectResponse
    {
        $this->authorize('guru.edit');
        $this->ensureTenantScope($request, $guru);
        abort_if($riwayatPendidikan->guru_id !== $guru->id, 404);

        $riwayatPendidikan->delete();

        return back()->with('status', 'Riwayat pendidikan berhasil dihapus.');
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
