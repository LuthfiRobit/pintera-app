<?php

namespace App\Http\Controllers\Guru\Akademik;

use App\Domains\Akademik\Actions\Presensi\GenerateSesiHarianAction;
use App\Domains\Akademik\Actions\Presensi\RecordJurnalDanPresensiAction;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Http\Requests\Akademik\UpdateJurnalPresensiRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JurnalKbmController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly GenerateSesiHarianAction $generateSesiHarianAction,
        private readonly RecordJurnalDanPresensiAction $recordJurnalDanPresensiAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('presensi.isi');

        $guru = $request->user()->guru;
        $hariIni = now();

        if ($guru) {
            $this->generateSesiHarianAction->execute($guru, $hariIni);
        }

        return view('portals.guru.akademik.jurnal-kbm.index', [
            'sesiList' => $guru
                ? SesiPembelajaran::where('guru_id', $guru->id)->whereDate('tanggal', $hariIni)->with('kelas', 'mataPelajaran')->get()
                : collect(),
        ]);
    }

    public function show(SesiPembelajaran $sesi): View
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        return view('portals.guru.akademik.jurnal-kbm.show', [
            'sesi' => $sesi,
            'presensiList' => $sesi->presensi()->with('siswa')->get(),
        ]);
    }

    public function update(UpdateJurnalPresensiRequest $request, SesiPembelajaran $sesi): RedirectResponse
    {
        $this->authorize('presensi.isi');
        // Ownership check is already enforced by UpdateJurnalPresensiRequest::authorize(),
        // which runs before this method body — no need to call authorizeMilikGuru() again here.

        $this->recordJurnalDanPresensiAction->execute($sesi, $request->toDTO());

        return redirect()->route('guru.jurnal-kbm.index')->with('status', 'Jurnal dan presensi berhasil disimpan.');
    }

    private function authorizeMilikGuru(SesiPembelajaran $sesi): void
    {
        $guru = auth()->user()->guru;

        abort_if($guru === null || $sesi->guru_id !== $guru->id, 403);
    }
}
