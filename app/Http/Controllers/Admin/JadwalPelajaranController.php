<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JadwalPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelasId = $request->query('kelas_id');
        $semesterId = $request->query('semester_id');

        return view('admin.jadwal-pelajaran.index', [
            'kelasList' => Kelas::orderBy('nama')->get(),
            'semesterList' => Semester::orderByDesc('id')->get(),
            'jadwalList' => $kelasId && $semesterId
                ? JadwalPelajaran::with(['jamPelajaran', 'mataPelajaran', 'guru'])
                    ->where('kelas_id', $kelasId)->where('semester_id', $semesterId)->get()
                : collect(),
            'kelasId' => $kelasId,
            'semesterId' => $semesterId,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::findOrFail($request->query('kelas_id'));

        return view('admin.jadwal-pelajaran.create', [
            'kelas' => $kelas,
            'semesterId' => $request->query('semester_id'),
            'jamPelajaranList' => $kelas->pola_jam_id
                ? JamPelajaran::where('pola_jam_id', $kelas->pola_jam_id)->isPelajaran()->orderBy('hari')->orderBy('urutan')->get()
                : collect(),
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer'],
            'jam_pelajaran_id' => ['required', 'integer'],
            'mata_pelajaran_id' => ['nullable', 'integer'],
            'guru_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
        ]);

        $kelas = Kelas::find($data['kelas_id']);
        if (! $kelas) {
            abort(404);
        }

        $guru = Guru::find($data['guru_id']);
        if (! $guru) {
            abort(404);
        }

        $semester = Semester::find($data['semester_id']);
        if (! $semester) {
            abort(404);
        }

        if (! empty($data['mata_pelajaran_id'])) {
            $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
            if (! $mataPelajaran) {
                abort(404);
            }
        }

        $jamPelajaran = JamPelajaran::where('id', $data['jam_pelajaran_id'])
            ->where('pola_jam_id', $kelas->pola_jam_id)
            ->first();
        if (! $jamPelajaran) {
            abort(404);
        }

        JadwalPelajaran::create($data);

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $data['kelas_id'],
            'semester_id' => $data['semester_id'],
        ])->with('status', 'Jadwal pelajaran berhasil ditambahkan.');
    }
}
