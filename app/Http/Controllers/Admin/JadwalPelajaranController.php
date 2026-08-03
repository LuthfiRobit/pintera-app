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
use Illuminate\Support\Collection;
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

        $kelasList = $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect();
        $semesterList = $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect();
        $jamPelajaranPerHari = $kelas ? $this->jamPelajaranPerHari($kelas) : collect();
        $mataPelajaranList = MataPelajaran::orderBy('nama')->get();
        $guruList = Guru::orderBy('nama')->get();

        if ($request->ajax()) {
            return view('admin.jadwal-pelajaran._daftar', [
                'jadwalList' => $jadwalList,
                'hariAktif' => $hariAktif,
                'kelasId' => $kelasId,
                'semesterId' => $semesterId,
                'kelasList' => $kelasList,
                'semesterList' => $semesterList,
                'jamPelajaranPerHari' => $jamPelajaranPerHari,
                'mataPelajaranList' => $mataPelajaranList,
                'guruList' => $guruList,
            ])->render();
        }

        return view('admin.jadwal-pelajaran.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'kelasList' => $kelasList,
            'semesterList' => $semesterList,
            'jadwalList' => $jadwalList,
            'hariAktif' => $hariAktif,
            'kelasId' => $kelasId,
            'semesterId' => $semesterId,
            'jamPelajaranPerHari' => $jamPelajaranPerHari,
            'mataPelajaranList' => $mataPelajaranList,
            'guruList' => $guruList,
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

        $jamPelajaranPerHari = $this->jamPelajaranPerHari($kelas);

        return view('admin.jadwal-pelajaran.create', [
            'kelas' => $kelas,
            'semesterId' => $semesterId,
            'semester' => $semester,
            'jamPelajaranPerHari' => $jamPelajaranPerHari,
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
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
            $msg = 'Guru harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['guru_id' => [$msg]]], 422);
            return back()->withErrors(['guru_id' => $msg])->withInput();
        }

        if ($semester->lembaga_id !== $kelas->lembaga_id) {
            $msg = 'Semester harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['semester_id' => [$msg]]], 422);
            return back()->withErrors(['semester_id' => $msg])->withInput();
        }

        if (isset($mataPelajaran) && $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
            $msg = 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['mata_pelajaran_id' => [$msg]]], 422);
            return back()->withErrors(['mata_pelajaran_id' => $msg])->withInput();
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
            $msg = 'Semua slot yang dipilih dilewati: ' . implode('; ', $dilewati) . '.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['jam_pelajaran_id' => [$msg]]], 422);
            return back()->withErrors([
                'jam_pelajaran_id' => $msg,
            ])->withInput();
        }

        $status = 'Jadwal pelajaran berhasil ditambahkan untuk ' . implode(', ', $berhasil) . '.';
        if (! empty($dilewati)) {
            $status .= ' Dilewati: ' . implode('; ', $dilewati) . '.';
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $status]);
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

        $jamPelajaranPerHari = $this->jamPelajaranPerHari($kelas);

        $slotMasihValid = $jamPelajaranPerHari->flatMap(fn ($grup) => $grup['items']->pluck('id'))->contains($jadwalPelajaran->jam_pelajaran_id);

        return view('admin.jadwal-pelajaran.edit', [
            'jadwalPelajaran' => $jadwalPelajaran,
            'kelas' => $kelas,
            'semester' => $semester,
            'jamPelajaranPerHari' => $jamPelajaranPerHari,
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
            'slotMasihValid' => $slotMasihValid,
        ]);
    }

    public function update(Request $request, JadwalPelajaran $jadwalPelajaran): RedirectResponse|JsonResponse
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
            $msg = 'Guru harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['guru_id' => [$msg]]], 422);
            return back()->withErrors(['guru_id' => $msg])->withInput();
        }

        if (isset($mataPelajaran) && $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
            $msg = 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['mata_pelajaran_id' => [$msg]]], 422);
            return back()->withErrors(['mata_pelajaran_id' => $msg])->withInput();
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
            $msg = 'Kelas ini sudah punya jadwal pada slot ini di semester yang sama.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['jam_pelajaran_id' => [$msg]]], 422);
            return back()->withErrors(['jam_pelajaran_id' => $msg])->withInput();
        }

        $guruBentrok = JadwalPelajaran::where('guru_id', $data['guru_id'])
            ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
            ->where('semester_id', $jadwalPelajaran->semester_id)
            ->where('id', '!=', $jadwalPelajaran->id)
            ->exists();
        if ($guruBentrok) {
            $msg = 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['guru_id' => [$msg]]], 422);
            return back()->withErrors(['guru_id' => $msg])->withInput();
        }

        $jadwalPelajaran->update([
            'jam_pelajaran_id' => $data['jam_pelajaran_id'],
            'mata_pelajaran_id' => $data['mata_pelajaran_id'] ?? null,
            'guru_id' => $data['guru_id'],
        ]);

        $msg = 'Jadwal pelajaran berhasil diperbarui.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $jadwalPelajaran->kelas_id,
            'semester_id' => $jadwalPelajaran->semester_id,
        ])->with('status', $msg);
    }

    public function destroy(Request $request, JadwalPelajaran $jadwalPelajaran): RedirectResponse|JsonResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::find($jadwalPelajaran->kelas_id);
        if (! $kelas) {
            abort(404);
        }

        $kelasId = $jadwalPelajaran->kelas_id;
        $semesterId = $jadwalPelajaran->semester_id;
        $jadwalPelajaran->delete();

        $msg = 'Jadwal pelajaran berhasil dihapus.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $kelasId,
            'semester_id' => $semesterId,
        ])->with('status', $msg);
    }

    private function formatSlot(JamPelajaran $jamPelajaran): string
    {
        return $jamPelajaran->hari->label() . ' ' . $jamPelajaran->label;
    }

    private function jamPelajaranPerHari(Kelas $kelas): Collection
    {
        $jamPelajaranPerHari = collect();

        if ($kelas->pola_jam_id) {
            $hariAktif = Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? []);
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

        return $jamPelajaranPerHari;
    }

    public function duplicate(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $data = $request->validate([
            'source_kelas_id' => ['required', 'integer'],
            'source_semester_id' => ['required', 'integer'],
            'target_kelas_id' => ['required', 'integer', 'different:source_kelas_id'],
            'target_semester_id' => ['required', 'integer'],
        ]);

        $sourceKelas = Kelas::find($data['source_kelas_id']);
        $targetKelas = Kelas::find($data['target_kelas_id']);
        $sourceSemester = Semester::find($data['source_semester_id']);
        $targetSemester = Semester::find($data['target_semester_id']);

        abort_if(! $sourceKelas || ! $targetKelas || ! $sourceSemester || ! $targetSemester, 404);
        
        $user = $request->user();
        $lembagaId = $user->active_lembaga_id ?: ($user->lembaga_id ?: null);
        
        if ($lembagaId) {
            abort_if($sourceKelas->lembaga_id !== $lembagaId || $targetKelas->lembaga_id !== $lembagaId, 404);
            abort_if($sourceSemester->tahunAjaran?->lembaga_id !== $lembagaId || $targetSemester->tahunAjaran?->lembaga_id !== $lembagaId, 404);
        } else {
            abort_if($sourceKelas->lembaga_id !== $targetKelas->lembaga_id, 404);
        }

        if (! $targetKelas->pola_jam_id) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kelas tujuan belum memiliki ikatan Pola Jam.',
                ], 422);
            }
            return redirect()->back()->withErrors(['error' => 'Kelas tujuan belum memiliki ikatan Pola Jam.']);
        }

        $targetSlots = JamPelajaran::where('pola_jam_id', $targetKelas->pola_jam_id)->get()->keyBy(function ($slot) {
            return ($slot->hari instanceof \UnitEnum ? $slot->hari->value : $slot->hari) . '-' . $slot->urutan;
        });

        $sourceJadwals = JadwalPelajaran::with('jamPelajaran')
            ->where('kelas_id', $sourceKelas->id)
            ->where('semester_id', $sourceSemester->id)
            ->get();

        $copiedCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($sourceJadwals, $targetSlots, $targetKelas, $targetSemester, &$copiedCount, &$skippedCount) {
            foreach ($sourceJadwals as $sj) {
                if (! $sj->jamPelajaran) {
                    $skippedCount++;
                    continue;
                }
                $key = ($sj->jamPelajaran->hari instanceof \UnitEnum ? $sj->jamPelajaran->hari->value : $sj->jamPelajaran->hari) . '-' . $sj->jamPelajaran->urutan;
                $targetSlot = $targetSlots->get($key);
                
                if (! $targetSlot) {
                    $skippedCount++;
                    continue;
                }

                $classCollision = JadwalPelajaran::where('kelas_id', $targetKelas->id)
                    ->where('semester_id', $targetSemester->id)
                    ->where('jam_pelajaran_id', $targetSlot->id)
                    ->exists();

                if ($classCollision) {
                    $skippedCount++;
                    continue;
                }

                $teacherCollision = JadwalPelajaran::where('guru_id', $sj->guru_id)
                    ->where('semester_id', $targetSemester->id)
                    ->where('jam_pelajaran_id', $targetSlot->id)
                    ->exists();

                if ($teacherCollision) {
                    $skippedCount++;
                    continue;
                }

                JadwalPelajaran::create([
                    'kelas_id' => $targetKelas->id,
                    'semester_id' => $targetSemester->id,
                    'jam_pelajaran_id' => $targetSlot->id,
                    'mata_pelajaran_id' => $sj->mata_pelajaran_id,
                    'guru_id' => $sj->guru_id,
                ]);
                
                $copiedCount++;
            }
        });

        $message = "Berhasil menyalin {$copiedCount} sesi jadwal.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} sesi dilewati karena bentrok waktu atau tidak sesuai pola jam.";
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'copied_count' => $copiedCount,
                'skipped_count' => $skippedCount,
            ]);
        }

        return redirect()->back()->with('status', $message);
    }
}

