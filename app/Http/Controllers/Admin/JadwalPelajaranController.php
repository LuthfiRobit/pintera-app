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
use Illuminate\Support\Facades\DB;
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

        $kelas = Kelas::with(['lembaga', 'tahunAjaran'])->findOrFail($request->query('kelas_id'));
        $semesterId = $request->query('semester_id');
        $semester = $semesterId ? Semester::find($semesterId) : null;

        $hariAktif = Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? []);

        $jamPelajaranPerHari = collect();
        if ($kelas->pola_jam_id) {
            $mentah = JamPelajaran::where('pola_jam_id', $kelas->pola_jam_id)
                ->isPelajaran()
                ->orderBy('urutan')
                ->get()
                ->groupBy(fn ($jam) => $jam->hari->value);

            foreach ($hariAktif as $hari) {
                if ($mentah->has($hari->value)) {
                    $jamPelajaranPerHari->push(['hari' => $hari, 'items' => $mentah->get($hari->value)]);
                }
            }
        }

        return view('admin.jadwal-pelajaran.create', [
            'kelas' => $kelas,
            'semesterId' => $semesterId,
            'semester' => $semester,
            'jamPelajaranPerHari' => $jamPelajaranPerHari,
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer'],
            'jam_pelajaran_id' => ['required', 'array', 'min:1'],
            'jam_pelajaran_id.*' => ['integer'],
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

        $jamPelajaranIds = array_unique($data['jam_pelajaran_id']);
        $jamPelajaranList = JamPelajaran::whereIn('id', $jamPelajaranIds)
            ->where('pola_jam_id', $kelas->pola_jam_id)
            ->isPelajaran()
            ->get();
        if ($jamPelajaranList->count() !== count($jamPelajaranIds)) {
            abort(404);
        }

        $berhasil = [];
        $dilewati = [];

        DB::transaction(function () use ($jamPelajaranList, $kelas, $guru, $semester, $data, &$berhasil, &$dilewati) {
            foreach ($jamPelajaranList as $jamPelajaran) {
                $duplikat = JadwalPelajaran::where('kelas_id', $kelas->id)
                    ->where('jam_pelajaran_id', $jamPelajaran->id)
                    ->where('semester_id', $semester->id)
                    ->exists();
                if ($duplikat) {
                    $dilewati[] = $this->formatSlot($jamPelajaran) . ' (kelas ini sudah punya jadwal di slot ini)';
                    continue;
                }

                $guruBentrok = JadwalPelajaran::where('guru_id', $guru->id)
                    ->where('jam_pelajaran_id', $jamPelajaran->id)
                    ->where('semester_id', $semester->id)
                    ->exists();
                if ($guruBentrok) {
                    $dilewati[] = $this->formatSlot($jamPelajaran) . ' (guru sudah mengajar kelas lain di slot ini)';
                    continue;
                }

                JadwalPelajaran::create([
                    'kelas_id' => $kelas->id,
                    'jam_pelajaran_id' => $jamPelajaran->id,
                    'mata_pelajaran_id' => $data['mata_pelajaran_id'] ?? null,
                    'guru_id' => $guru->id,
                    'semester_id' => $semester->id,
                ]);
                $berhasil[] = $this->formatSlot($jamPelajaran);
            }
        });

        if (empty($berhasil)) {
            return back()->withErrors([
                'jam_pelajaran_id' => 'Semua slot yang dipilih dilewati: ' . implode('; ', $dilewati) . '.',
            ])->withInput();
        }

        $status = 'Jadwal pelajaran berhasil ditambahkan untuk ' . implode(', ', $berhasil) . '.';
        if (! empty($dilewati)) {
            $status .= ' Dilewati: ' . implode('; ', $dilewati) . '.';
        }

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $kelas->id,
            'semester_id' => $semester->id,
        ])->with('status', $status);
    }

    public function edit(JadwalPelajaran $jadwalPelajaran): View
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::with(['lembaga', 'tahunAjaran'])->find($jadwalPelajaran->kelas_id);
        if (! $kelas) {
            abort(404);
        }

        $semester = Semester::find($jadwalPelajaran->semester_id);

        $hariAktif = Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? []);

        $jamPelajaranPerHari = collect();
        if ($kelas->pola_jam_id) {
            $mentah = JamPelajaran::where('pola_jam_id', $kelas->pola_jam_id)
                ->isPelajaran()
                ->orderBy('urutan')
                ->get()
                ->groupBy(fn ($jam) => $jam->hari->value);

            foreach ($hariAktif as $hari) {
                if ($mentah->has($hari->value)) {
                    $jamPelajaranPerHari->push(['hari' => $hari, 'items' => $mentah->get($hari->value)]);
                }
            }
        }

        return view('admin.jadwal-pelajaran.edit', [
            'jadwalPelajaran' => $jadwalPelajaran,
            'kelas' => $kelas,
            'semester' => $semester,
            'jamPelajaranPerHari' => $jamPelajaranPerHari,
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, JadwalPelajaran $jadwalPelajaran): RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::find($jadwalPelajaran->kelas_id);
        if (! $kelas) {
            abort(404);
        }

        $data = $request->validate([
            'jam_pelajaran_id' => ['required', 'integer'],
            'mata_pelajaran_id' => ['nullable', 'integer'],
            'guru_id' => ['required', 'integer'],
        ]);

        $guru = Guru::find($data['guru_id']);
        if (! $guru) {
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

        if (isset($mataPelajaran) && $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
            return back()->withErrors(['mata_pelajaran_id' => 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
        }

        $jamPelajaran = JamPelajaran::where('id', $data['jam_pelajaran_id'])
            ->where('pola_jam_id', $kelas->pola_jam_id)
            ->isPelajaran()
            ->first();
        if (! $jamPelajaran) {
            abort(404);
        }

        $duplikat = JadwalPelajaran::where('kelas_id', $jadwalPelajaran->kelas_id)
            ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
            ->where('semester_id', $jadwalPelajaran->semester_id)
            ->where('id', '!=', $jadwalPelajaran->id)
            ->exists();
        if ($duplikat) {
            return back()->withErrors(['jam_pelajaran_id' => 'Kelas ini sudah punya jadwal pada slot ini di semester yang sama.'])->withInput();
        }

        $guruBentrok = JadwalPelajaran::where('guru_id', $data['guru_id'])
            ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
            ->where('semester_id', $jadwalPelajaran->semester_id)
            ->where('id', '!=', $jadwalPelajaran->id)
            ->exists();
        if ($guruBentrok) {
            return back()->withErrors(['guru_id' => 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.'])->withInput();
        }

        $jadwalPelajaran->update([
            'jam_pelajaran_id' => $data['jam_pelajaran_id'],
            'mata_pelajaran_id' => $data['mata_pelajaran_id'] ?? null,
            'guru_id' => $data['guru_id'],
        ]);

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $jadwalPelajaran->kelas_id,
            'semester_id' => $jadwalPelajaran->semester_id,
        ])->with('status', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function destroy(JadwalPelajaran $jadwalPelajaran): RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::find($jadwalPelajaran->kelas_id);
        if (! $kelas) {
            abort(404);
        }

        $kelasId = $jadwalPelajaran->kelas_id;
        $semesterId = $jadwalPelajaran->semester_id;
        $jadwalPelajaran->delete();

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $kelasId,
            'semester_id' => $semesterId,
        ])->with('status', 'Jadwal pelajaran berhasil dihapus.');
    }

    private function formatSlot(JamPelajaran $jamPelajaran): string
    {
        return $jamPelajaran->hari->label() . ' ' . $jamPelajaran->label;
    }
}
