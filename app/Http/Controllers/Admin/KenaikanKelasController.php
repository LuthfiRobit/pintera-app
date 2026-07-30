<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KenaikanKelasController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kenaikan-kelas.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        $tahunAjaranTujuanId = $request->query('tahun_ajaran_tujuan_id');

        return view('admin.kenaikan-kelas.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'kelasLamaList' => $tahunAjaranId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->withCount('siswa')->orderBy('nama')->get()
                : collect(),
            'kelasTujuanList' => $tahunAjaranTujuanId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderBy('nama')->get()
                : collect(),
            'semesterList' => $tahunAjaranTujuanId
                ? Semester::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderByDesc('id')->get()
                : collect(),
            'tahunAjaranId' => $tahunAjaranId,
            'tahunAjaranTujuanId' => $tahunAjaranTujuanId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kenaikan-kelas.kelola');

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['nullable', 'integer'],
        ]);

        try {
            DB::transaction(function () use ($data) {
                foreach ($data['mapping'] as $kelasLamaId => $aksi) {
                    $kelasLama = Kelas::findOrFail($kelasLamaId);

                    if ($aksi['tindakan'] === 'lulus') {
                        Siswa::where('kelas_id', $kelasLama->id)->update([
                            'status' => StatusSiswa::Lulus->value,
                            'kelas_id' => null,
                        ]);

                        continue;
                    }

                    $kelasBaru = Kelas::find($aksi['kelas_baru_id']);
                    abort_if($kelasBaru === null || $kelasBaru->lembaga_id !== $kelasLama->lembaga_id, 404);

                    if ($kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id) {
                        throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" masih berada di tahun ajaran yang sama dengan kelas asal \"{$kelasLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                    }

                    Siswa::where('kelas_id', $kelasLama->id)->update(['kelas_id' => $kelasBaru->id]);

                    if (($aksi['salin_jadwal'] ?? false) && ! empty($aksi['semester_tujuan_id'])) {
                        $semesterTujuan = Semester::find($aksi['semester_tujuan_id']);
                        abort_if($semesterTujuan === null || $semesterTujuan->lembaga_id !== $kelasLama->lembaga_id, 404);

                        $this->salinJadwal($kelasLama->id, $kelasBaru->id, $semesterTujuan->id);
                    }
                }
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['mapping' => $e->getMessage()]);
        }

        return redirect()->route('admin.kelas.index')->with('status', 'Kenaikan kelas berhasil diproses.');
    }

    private function salinJadwal(int $kelasLamaId, int $kelasBaruId, int $semesterTujuanId): void
    {
        $jadwalLama = JadwalPelajaran::where('kelas_id', $kelasLamaId)->get();

        foreach ($jadwalLama as $jadwal) {
            JadwalPelajaran::firstOrCreate([
                'kelas_id' => $kelasBaruId,
                'jam_pelajaran_id' => $jadwal->jam_pelajaran_id,
                'semester_id' => $semesterTujuanId,
            ], [
                'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                'guru_id' => $jadwal->guru_id,
            ]);
        }
    }
}
