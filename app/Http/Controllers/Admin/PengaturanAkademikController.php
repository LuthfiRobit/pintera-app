<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\KalenderAkademik;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PengaturanAkademikController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('kalender-akademik.view');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return redirect()->route('dashboard')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga untuk mengakses Pengaturan Akademik.']);
        }

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        $lembaga = Lembaga::findOrFail($lembagaId);

        return view('admin.pengaturan.akademik', [
            'lembaga' => $lembaga,
            'entriList' => KalenderAkademik::where(fn ($q) => $q->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId))
                ->orderBy('tanggal')
                ->get(),
            'bolehNasional' => $request->user()->can('kalender-akademik.kelola-nasional'),
            'bolehKelolaHariAktif' => $request->user()->can('pengaturan-akademik.kelola'),
        ]);
    }

    public function updateHariAktif(Request $request): JsonResponse
    {
        $this->authorize('pengaturan-akademik.kelola');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return response()->json([
                'message' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.',
                'errors' => ['lembaga_id' => ['Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.']],
            ], 422);
        }

        $data = $request->validate([
            'hari_aktif' => ['present', 'array'],
            'hari_aktif.*' => ['integer', 'between:0,6'],
        ]);

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        $lembaga = Lembaga::findOrFail($lembagaId);

        $hariLibur = array_values(array_diff(range(0, 6), $data['hari_aktif']));
        $lembaga->update(['hari_libur_mingguan' => $hariLibur]);

        return response()->json(['data' => ['hari_libur_mingguan' => $lembaga->fresh()->hari_libur_mingguan]]);
    }
}
