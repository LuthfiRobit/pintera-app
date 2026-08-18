<?php

namespace App\Http\Controllers\Guru\Akademik;

use App\Domains\Akademik\Actions\Presensi\GenerateSesiHarianAction;
use App\Domains\Akademik\Actions\Presensi\RecordJurnalDanPresensiAction;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Enums\Hari;
use App\Http\Requests\Akademik\UpdateJurnalPresensiRequest;
use App\Models\JadwalPelajaran;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
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

        $sesiList = $guru
            ? SesiPembelajaran::where('guru_id', $guru->id)->whereDate('tanggal', $hariIni)->with('kelas.tahunAjaran', 'mataPelajaran')->get()
            : collect();

        return view('portals.guru.akademik.jurnal-kbm.index', [
            'sesiList' => $sesiList,
            'mapelTerjadwal' => $this->mapelTerjadwalUntukSesiTematik($sesiList, $hariIni),
        ]);
    }

    /**
     * Untuk sesi Mode Tematik (mata_pelajaran_id selalu NULL by design), cari mata pelajaran
     * apa saja yang terjadwal di JadwalPelajaran kelas tsb hari ini — murni informasi tampilan
     * di badge index, TIDAK mengubah data guru_id/mata_pelajaran_id sesi itu sendiri.
     *
     * @return array<int, string> keyed by kelas_id
     */
    private function mapelTerjadwalUntukSesiTematik(Collection $sesiList, CarbonInterface $tanggal): array
    {
        $kelasTematik = $sesiList->filter(fn (SesiPembelajaran $sesi) => $sesi->isTematik())->pluck('kelas')->unique('id');

        if ($kelasTematik->isEmpty()) {
            return [];
        }

        $hari = Hari::fromCarbonDayOfWeek($tanggal->dayOfWeek);
        $mapelTerjadwal = [];

        foreach ($kelasTematik as $kelas) {
            $semesterId = optional($kelas->tahunAjaran->semester()->where('status_aktif', true)->first())->id;

            if (! $semesterId) {
                continue;
            }

            $nama = JadwalPelajaran::where('kelas_id', $kelas->id)
                ->where('semester_id', $semesterId)
                ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hari->value))
                ->with('mataPelajaran')
                ->get()
                ->pluck('mataPelajaran.nama')
                ->filter()
                ->unique()
                ->implode(', ');

            if ($nama !== '') {
                $mapelTerjadwal[$kelas->id] = $nama;
            }
        }

        return $mapelTerjadwal;
    }

    public function show(SesiPembelajaran $sesi): View
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        $sesi->loadMissing('kelas.tahunAjaran');
        $mapelTerjadwal = $this->mapelTerjadwalUntukSesiTematik(collect([$sesi]), $sesi->tanggal);

        return view('portals.guru.akademik.jurnal-kbm.show', [
            'sesi' => $sesi,
            'presensiList' => $sesi->presensi()->with('siswa')->get(),
            'mapelTerjadwal' => $mapelTerjadwal[$sesi->kelas_id] ?? null,
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
