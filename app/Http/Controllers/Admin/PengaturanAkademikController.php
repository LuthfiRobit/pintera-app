<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Kalender\UpdateHariAktifLembagaAction;
use App\Domains\Akademik\DataTransferObjects\HariAktifLembagaData;
use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PengaturanAkademikController extends BaseController
{
    use AuthorizesRequests, ResolveLembagaScopeTrait;

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('kalender-akademik.view');

        $lembagaId = $this->resolveActiveLembagaId($request->user());
        if ($lembagaId === null) {
            return redirect()->route('dashboard')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga untuk mengakses Pengaturan Akademik.']);
        }

        $lembaga = Lembaga::findOrFail($lembagaId);

        return view('portals.lembaga.akademik.pengaturan.akademik', [
            'lembaga' => $lembaga,
            'entriList' => KalenderAkademik::where(fn ($q) => $q->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId))
                ->orderBy('tanggal')
                ->get(),
            'bolehNasional' => $request->user()->can('kalender-akademik.kelola-nasional'),
            'bolehKelolaHariAktif' => $request->user()->can('pengaturan-akademik.kelola'),
        ]);
    }

    public function updateHariAktif(Request $request, UpdateHariAktifLembagaAction $action): JsonResponse
    {
        $this->authorize('pengaturan-akademik.kelola');

        $lembagaId = $this->resolveActiveLembagaId($request->user());
        if ($lembagaId === null) {
            return response()->json([
                'message' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.',
                'errors' => ['lembaga_id' => ['Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.']],
            ], 422);
        }

        $data = $request->validate([
            'hari_aktif' => ['present', 'array'],
            'hari_aktif.*' => ['integer', 'between:0,6'],
        ]);

        $lembaga = Lembaga::findOrFail($lembagaId);

        $lembaga = $action->execute($lembaga, new HariAktifLembagaData(hariAktif: $data['hari_aktif']));

        return response()->json(['data' => ['hari_libur_mingguan' => $lembaga->hari_libur_mingguan]]);
    }
}
