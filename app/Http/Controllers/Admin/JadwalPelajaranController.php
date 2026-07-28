<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Hari;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JadwalPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|string
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        if (! $tahunAjaranId) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        $kelasId = $request->query('kelas_id');
        $semesterId = $request->query('semester_id');

        $kelas = $kelasId ? Kelas::with('lembaga')->find($kelasId) : null;
        $hariAktif = $kelas
            ? Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? [])
            : Hari::cases();

        $jadwalList = $kelasId && $semesterId
            ? JadwalPelajaran::with(['jamPelajaran', 'mataPelajaran', 'guru'])
                ->where('kelas_id', $kelasId)->where('semester_id', $semesterId)->get()
            : collect();

        if ($request->ajax()) {
            return view('admin.jadwal-pelajaran._daftar', [
                'jadwalList' => $jadwalList,
                'hariAktif' => $hariAktif,
                'kelasId' => $kelasId,
                'semesterId' => $semesterId,
            ])->render();
        }

        return view('admin.jadwal-pelajaran.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'kelasList' => $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect(),
            'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
            'jadwalList' => $jadwalList,
            'hariAktif' => $hariAktif,
            'kelasId' => $kelasId,
            'semesterId' => $semesterId,
        ]);
    }

    public function opsi(Request $request): JsonResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer'],
        ]);

        $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
        abort_if($tahunAjaran === null, 404);

        return response()->json([
            'kelasList' => Kelas::where('tahun_ajaran_id', $tahunAjaran->id)->orderBy('nama')->get(['id', 'nama']),
            'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
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

        if ($guru->lembaga_id !== $kelas->lembaga_id) {
            return back()->withErrors(['guru_id' => 'Guru harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
        }

        if ($semester->lembaga_id !== $kelas->lembaga_id) {
            return back()->withErrors(['semester_id' => 'Semester harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
        }

        if (isset($mataPelajaran) && $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
            return back()->withErrors(['mata_pelajaran_id' => 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
        }

        $jamPelajaran = JamPelajaran::where('id', $data['jam_pelajaran_id'])
            ->where('pola_jam_id', $kelas->pola_jam_id)
            ->first();
        if (! $jamPelajaran) {
            abort(404);
        }

        $duplikat = JadwalPelajaran::where('kelas_id', $data['kelas_id'])
            ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->exists();
        if ($duplikat) {
            return back()->withErrors(['jam_pelajaran_id' => 'Kelas ini sudah punya jadwal pada slot ini di semester yang sama.'])->withInput();
        }

        $guruBentrok = JadwalPelajaran::where('guru_id', $data['guru_id'])
            ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->exists();
        if ($guruBentrok) {
            return back()->withErrors(['guru_id' => 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.'])->withInput();
        }

        JadwalPelajaran::create($data);

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $data['kelas_id'],
            'semester_id' => $data['semester_id'],
        ])->with('status', 'Jadwal pelajaran berhasil ditambahkan.');
    }
}
