<?php

namespace App\Http\Controllers\Guru\Akademik;

use App\Domains\Akademik\Actions\KartuSiswa\ResolveKartuUntukPresensiAction;
use App\Domains\Akademik\Actions\Presensi\GenerateSesiHarianAction;
use App\Domains\Akademik\Actions\Presensi\RecordJurnalDanPresensiAction;
use App\Domains\Akademik\Exceptions\KartuValidasiException;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PiketAccessChecker;
use App\Enums\Hari;
use App\Http\Requests\Akademik\UpdateJurnalPresensiRequest;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
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
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('presensi.isi');

        $guru = $request->user()->guru;

        $tanggalInput = $request->query('tanggal');

        try {
            $tanggal = $tanggalInput ? Carbon::parse($tanggalInput) : now();
        } catch (\Exception) {
            return redirect()->route('guru.jurnal-kbm.index')->with('error', 'Format tanggal tidak valid.');
        }

        if ($tanggal->isFuture()) {
            return redirect()->route('guru.jurnal-kbm.index')->with('error', 'Tidak bisa mengisi jurnal untuk tanggal yang belum terjadi.');
        }

        if ($guru) {
            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $guru->lembaga_id)->where('status_aktif', true)->first();
            $semesterAktif = $tahunAjaranAktif
                ? Semester::where('tahun_ajaran_id', $tahunAjaranAktif->id)->where('status_aktif', true)->first()
                : null;

            if ($semesterAktif && $semesterAktif->tanggal_mulai && $tanggal->lt($semesterAktif->tanggal_mulai)) {
                return redirect()->route('guru.jurnal-kbm.index')->with('error', 'Tidak bisa mengisi jurnal untuk tanggal sebelum semester aktif dimulai.');
            }
        }

        $hariIni = $tanggal;

        if ($guru) {
            $this->generateSesiHarianAction->execute($guru, $hariIni);
        }

        $sesiList = $guru
            ? SesiPembelajaran::where('guru_id', $guru->id)->whereDate('tanggal', $hariIni)->with('kelas.tahunAjaran', 'mataPelajaran')->get()
            : collect();

        return view('portals.guru.akademik.jurnal-kbm.index', [
            'sesiList' => $sesiList,
            'mapelTerjadwal' => $this->mapelTerjadwalUntukSesiTematik($sesiList, $hariIni),
            'tanggalDipilih' => $hariIni->toDateString(),
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

        $guru = auth()->user()->guru;
        $terkunci = $this->sesiTerkunci($sesi, $guru);

        return view('portals.guru.akademik.jurnal-kbm.show', [
            'sesi' => $sesi,
            'presensiList' => $sesi->presensi()->with('siswa')->get(),
            'mapelTerjadwal' => $mapelTerjadwal[$sesi->kelas_id] ?? null,
            'terkunci' => $terkunci,
            'batasEditHari' => $guru->lembaga->batas_edit_absen_hari ?? 3,
        ]);
    }

    public function update(UpdateJurnalPresensiRequest $request, SesiPembelajaran $sesi): RedirectResponse
    {
        $this->authorize('presensi.isi');
        // Ownership check is already enforced by UpdateJurnalPresensiRequest::authorize(),
        // which runs before this method body — no need to call authorizeMilikGuru() again here.

        $guru = $request->user()->guru;

        if ($this->sesiTerkunci($sesi, $guru)) {
            $batasHari = $guru->lembaga->batas_edit_absen_hari ?? 3;

            return redirect()->route('guru.jurnal-kbm.index')
                ->with('error', "Sesi ini sudah melewati batas waktu edit ({$batasHari} hari). Hubungi Wali Kelas kelas ini untuk koreksi.");
        }

        $diisiOlehGuruId = $guru->id !== $sesi->guru_id ? $guru->id : null;

        $this->recordJurnalDanPresensiAction->execute($sesi, $request->toDTO(), $diisiOlehGuruId);

        return redirect()->route('guru.jurnal-kbm.index')->with('status', 'Jurnal dan presensi berhasil disimpan.');
    }

    private function sesiTerkunci(SesiPembelajaran $sesi, Guru $guru): bool
    {
        $sesi->loadMissing('kelas');

        if ($sesi->kelas->wali_kelas_guru_id === $guru->id) {
            return false;
        }

        $batasHari = $guru->lembaga->batas_edit_absen_hari ?? 3;

        return $sesi->tanggal->lt(now()->subDays($batasHari)->startOfDay());
    }

    public function resolveKartu(Request $request, SesiPembelajaran $sesi, ResolveKartuUntukPresensiAction $action): JsonResponse
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        $request->validate(['kode' => ['required', 'string']]);

        $guru = $request->user()->guru;
        abort_unless($guru !== null, 403);

        try {
            $siswa = $action->execute($request->string('kode')->toString(), $sesi, (int) $guru->lembaga_id);
        } catch (KartuValidasiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['siswa_id' => $siswa->id, 'nama_lengkap' => $siswa->nama_lengkap]);
    }

    private function authorizeMilikGuru(SesiPembelajaran $sesi): void
    {
        $guru = auth()->user()->guru;

        abort_if($guru === null || ! app(PiketAccessChecker::class)->bisaAkses($sesi, $guru), 403);
    }
}
