<?php

namespace App\Http\Controllers\Guru\Akademik;

use App\Domains\Akademik\Services\PresensiAggregationService;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RekapKehadiranController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PresensiAggregationService $aggregationService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('presensi.isi');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);

        $lembagaId = $request->user()->lembaga_id;

        // Daftar Tahun Ajaran untuk dropdown
        $tahunAjaranQuery = TahunAjaran::query();
        if ($lembagaId) {
            $tahunAjaranQuery->where('lembaga_id', $lembagaId);
        }
        $tahunAjaranList = $tahunAjaranQuery->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $tahunAjaranAktif = $tahunAjaranList->firstWhere('status_aktif', true);

        // Filter Tahun Ajaran
        $tahunAjaranId = $request->has('tahun_ajaran_id')
            ? ($request->query('tahun_ajaran_id') !== '' ? (int) $request->query('tahun_ajaran_id') : null)
            : ($tahunAjaranAktif?->id ?? $tahunAjaranList->first()?->id);

        // Daftar Semester untuk dropdown (berdasarkan Tahun Ajaran terpilih)
        $semesterQuery = Semester::query();
        if ($lembagaId) {
            $semesterQuery->where('lembaga_id', $lembagaId);
        }
        if ($tahunAjaranId) {
            $semesterQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }
        $semesterList = $semesterQuery->orderBy('urutan')->orderBy('nama')->get();
        $semesterAktif = $semesterList->firstWhere('status_aktif', true);

        // Filter Semester (default ke semester aktif pada Tahun Ajaran tersebut jika ada dan belum difilter)
        $semesterId = $request->has('semester_id')
            ? ($request->query('semester_id') !== '' ? (int) $request->query('semester_id') : null)
            : ($semesterAktif?->id ?? null);

        // Daftar Kelas yang bisa diakses Guru: sebagai wali kelas (rekap penuh) ATAU sebagai guru mapel
        // yang terjadwal (JadwalPelajaran) mengajar di kelas tsb (rekap difilter ke sesinya sendiri).
        // Sumber "kelas apa yang diajar guru" pakai JadwalPelajaran (jadwal resmi semester), konsisten
        // dengan Guru\KomponenPenilaianController dan Guru\AsesmenController -- BUKAN SesiPembelajaran,
        // supaya guru mapel yang terjadwal tapi belum sempat generate/isi sesi hari itu tidak kehilangan
        // akses ke Rekap Kehadiran kelas yang sebenarnya dia ajar.
        $kelasIdDiajar = JadwalPelajaran::where('guru_id', $guru->id)->distinct()->pluck('kelas_id');
        $kelasQuery = Kelas::where(function ($q) use ($guru, $kelasIdDiajar) {
            $q->where('wali_kelas_guru_id', $guru->id)->orWhereIn('id', $kelasIdDiajar);
        });
        if ($tahunAjaranId) {
            $kelasQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }
        $kelasList = $kelasQuery->orderBy('nama')->get();

        // Filter Kelas (default ke kelas pertama dari daftar kelas yang tersedia)
        $kelasId = $request->has('kelas_id') && $request->query('kelas_id') !== ''
            ? (int) $request->query('kelas_id')
            : optional($kelasList->first())->id;

        $kelas = $kelasList->firstWhere('id', $kelasId);
        $selectedSemester = $semesterId ? $semesterList->firstWhere('id', $semesterId) : null;

        $rekap = collect();
        $isWaliKelas = false;
        if ($kelas) {
            $isWaliKelas = $kelas->wali_kelas_guru_id === $guru->id;
            $rekap = $this->aggregationService->agregasiPerKelas($kelas->id, $selectedSemester, $isWaliKelas ? null : $guru->id);
        }

        return view('portals.guru.akademik.jurnal-kbm.rekap', [
            'tahunAjaranList' => $tahunAjaranList,
            'tahunAjaranId' => $tahunAjaranId,
            'semesterList' => $semesterList,
            'semesterId' => $semesterId,
            'kelasList' => $kelasList,
            'kelasId' => $kelasId,
            'kelas' => $kelas,
            'semester' => $selectedSemester,
            'rekap' => $rekap,
            'isSemuaSemester' => $selectedSemester === null,
            'isWaliKelas' => $isWaliKelas,
        ]);
    }
}
