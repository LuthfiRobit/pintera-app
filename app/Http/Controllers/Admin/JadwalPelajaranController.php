<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction;
use App\Domains\Akademik\Actions\Jadwal\DuplicateJadwalAction;
use App\Domains\Akademik\Actions\Jadwal\UpdateJadwalPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\JadwalPelajaranData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Sarpras\Models\Ruangan;
use App\Enums\Hari;
use App\Http\Requests\Akademik\DuplicateJadwalRequest;
use App\Http\Requests\Akademik\StoreJadwalPelajaranRequest;
use App\Http\Requests\Akademik\UpdateJadwalPelajaranRequest;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Scopes\TenantScope;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JadwalPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreateJadwalPelajaranAction $createJadwalPelajaranAction,
        private readonly UpdateJadwalPelajaranAction $updateJadwalPelajaranAction,
        private readonly DuplicateJadwalAction $duplicateJadwalAction
    ) {}

    public function index(Request $request): View|string
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        if (! $tahunAjaranId) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        $kelasId = $request->query('kelas_id');
        $semesterId = $request->query('semester_id');

        $kelas = $kelasId ? Kelas::with(['lembaga', 'ruangan'])->find($kelasId) : null;
        $hariAktif = $kelas
            ? Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? [])
            : Hari::cases();

        $jadwalList = $kelasId && $semesterId
            ? JadwalPelajaran::with(['jamPelajaran', 'mataPelajaran', 'guru', 'ruangan'])
                ->where('kelas_id', $kelasId)->where('semester_id', $semesterId)->get()
            : collect();

        $kelasList = $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect();
        $semesterList = $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect();
        $jamPelajaranPerHari = $kelas ? $this->jamPelajaranPerHari($kelas) : collect();

        $targetLembagaId = $kelas?->lembaga_id
            ?? ($tahunAjaranId ? TahunAjaran::find($tahunAjaranId)?->lembaga_id : null)
            ?? ($request->user()->widestScopeLevel() === 'yayasan' ? session('active_lembaga_id') : $request->user()->lembaga_id);

        $mataPelajaranList = $targetLembagaId
            ? MataPelajaran::where('lembaga_id', $targetLembagaId)->orderBy('nama')->get()
            : MataPelajaran::orderBy('nama')->get();

        $guruList = $targetLembagaId
            ? Guru::where('lembaga_id', $targetLembagaId)->orderBy('nama')->get()
            : Guru::orderBy('nama')->get();

        $ruanganList = $targetLembagaId
            ? Ruangan::withoutGlobalScope(TenantScope::class)
                ->where('is_aktif', true)
                ->where(function ($q) use ($targetLembagaId) {
                    $q->where('lembaga_id', $targetLembagaId)
                        ->orWhere('is_shared', true);
                })
                ->orderBy('nama_ruangan')
                ->get()
            : Ruangan::where('is_aktif', true)->orderBy('nama_ruangan')->get();

        if ($request->ajax()) {
            return view('portals.lembaga.akademik.jadwal-pelajaran._daftar', [
                'jadwalList' => $jadwalList,
                'hariAktif' => $hariAktif,
                'kelasId' => $kelasId,
                'semesterId' => $semesterId,
                'kelasList' => $kelasList,
                'semesterList' => $semesterList,
                'jamPelajaranPerHari' => $jamPelajaranPerHari,
                'mataPelajaranList' => $mataPelajaranList,
                'guruList' => $guruList,
                'ruanganList' => $ruanganList,
                'kelas' => $kelas,
            ])->render();
        }

        return view('portals.lembaga.akademik.jadwal-pelajaran.index', [
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
            'ruanganList' => $ruanganList,
            'kelas' => $kelas,
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

        $kelas = Kelas::with(['lembaga', 'tahunAjaran', 'ruangan'])->findOrFail($request->query('kelas_id'));
        $semesterId = $request->query('semester_id');
        $semester = $semesterId ? Semester::find($semesterId) : null;
        $targetLembagaId = $kelas->lembaga_id;

        $jamPelajaranPerHari = $this->jamPelajaranPerHari($kelas);

        $mataPelajaranList = MataPelajaran::where('lembaga_id', $targetLembagaId)->orderBy('nama')->get();
        $guruList = Guru::where('lembaga_id', $targetLembagaId)->orderBy('nama')->get();
        $ruanganList = Ruangan::withoutGlobalScope(TenantScope::class)
            ->where('is_aktif', true)
            ->where(function ($q) use ($targetLembagaId) {
                $q->where('lembaga_id', $targetLembagaId)
                    ->orWhere('is_shared', true);
            })
            ->orderBy('nama_ruangan')
            ->get();

        return view('portals.lembaga.akademik.jadwal-pelajaran.create', [
            'kelas' => $kelas,
            'semesterId' => $semesterId,
            'semester' => $semester,
            'jamPelajaranPerHari' => $jamPelajaranPerHari,
            'mataPelajaranList' => $mataPelajaranList,
            'guruList' => $guruList,
            'ruanganList' => $ruanganList,
        ]);
    }

    public function store(StoreJadwalPelajaranRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $kelas = Kelas::find($data['kelas_id']);
        abort_if(! $kelas, 404);

        $guru = Guru::find($data['guru_id']);
        abort_if(! $guru, 404);

        $semester = Semester::find($data['semester_id']);
        abort_if(! $semester, 404);

        if (! empty($data['mata_pelajaran_id'])) {
            $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
            abort_if(! $mataPelajaran, 404);

            if ($mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
                $msg = 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['mata_pelajaran_id' => [$msg]]], 422);
                }

                return back()->withErrors(['mata_pelajaran_id' => $msg])->withInput();
            }
        }

        if ($guru->lembaga_id !== $kelas->lembaga_id) {
            $msg = 'Guru harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['guru_id' => [$msg]]], 422);
            }

            return back()->withErrors(['guru_id' => $msg])->withInput();
        }

        if ($semester->lembaga_id !== $kelas->lembaga_id) {
            $msg = 'Semester harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['semester_id' => [$msg]]], 422);
            }

            return back()->withErrors(['semester_id' => $msg])->withInput();
        }

        if (! empty($data['ruangan_id'])) {
            $ruangan = Ruangan::withoutGlobalScope(TenantScope::class)->find((int) $data['ruangan_id']);
            if (! $ruangan || (! $ruangan->is_shared && $ruangan->lembaga_id !== $kelas->lembaga_id)) {
                $msg = 'Ruangan harus berasal dari lembaga yang sama dengan kelas ini, atau berupa ruangan bersama.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['ruangan_id' => [$msg]]], 422);
                }

                return back()->withErrors(['ruangan_id' => $msg])->withInput();
            }
        }

        $ruanganId = ! empty($data['ruangan_id']) ? (int) $data['ruangan_id'] : $kelas->ruangan_id;

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

        foreach ($jamPelajaranList as $jamPelajaran) {
            $dto = new JadwalPelajaranData(
                lembagaId: $kelas->lembaga_id,
                kelasId: $kelas->id,
                guruId: $guru->id,
                jamPelajaranId: $jamPelajaran->id,
                semesterId: $semester->id,
                mataPelajaranId: ! empty($data['mata_pelajaran_id']) ? (int) $data['mata_pelajaran_id'] : null,
                ruanganId: $ruanganId
            );

            try {
                $this->createJadwalPelajaranAction->execute($dto);
                $berhasil[] = $this->formatSlot($jamPelajaran);
            } catch (ValidationException $e) {
                $errorMessage = collect($e->errors())->flatten()->first() ?? 'Bentrok slot/ruangan/guru';
                $dilewati[] = $this->formatSlot($jamPelajaran)." ({$errorMessage})";
            }
        }

        if (empty($berhasil)) {
            $msg = 'Semua slot yang dipilih dilewati: '.implode('; ', $dilewati).'.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['jam_pelajaran_id' => [$msg]]], 422);
            }

            return back()->withErrors(['jam_pelajaran_id' => $msg])->withInput();
        }

        $status = 'Jadwal pelajaran berhasil ditambahkan untuk '.implode(', ', $berhasil).'.';
        if (! empty($dilewati)) {
            $status .= ' Dilewati: '.implode('; ', $dilewati).'.';
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

        $kelas = Kelas::with(['lembaga', 'tahunAjaran', 'ruangan'])->findOrFail($jadwalPelajaran->kelas_id);
        $semester = Semester::find($jadwalPelajaran->semester_id);
        $targetLembagaId = $kelas->lembaga_id;

        $jamPelajaranPerHari = $this->jamPelajaranPerHari($kelas);
        $slotMasihValid = $jamPelajaranPerHari->flatMap(fn ($grup) => $grup['items']->pluck('id'))->contains($jadwalPelajaran->jam_pelajaran_id);

        $mataPelajaranList = MataPelajaran::where('lembaga_id', $targetLembagaId)->orderBy('nama')->get();
        $guruList = Guru::where('lembaga_id', $targetLembagaId)->orderBy('nama')->get();
        $ruanganList = Ruangan::withoutGlobalScope(TenantScope::class)
            ->where('is_aktif', true)
            ->where(function ($q) use ($targetLembagaId) {
                $q->where('lembaga_id', $targetLembagaId)
                    ->orWhere('is_shared', true);
            })
            ->orderBy('nama_ruangan')
            ->get();

        return view('portals.lembaga.akademik.jadwal-pelajaran.edit', [
            'jadwalPelajaran' => $jadwalPelajaran,
            'kelas' => $kelas,
            'semester' => $semester,
            'jamPelajaranPerHari' => $jamPelajaranPerHari,
            'mataPelajaranList' => $mataPelajaranList,
            'guruList' => $guruList,
            'ruanganList' => $ruanganList,
            'slotMasihValid' => $slotMasihValid,
        ]);
    }

    public function update(UpdateJadwalPelajaranRequest $request, JadwalPelajaran $jadwalPelajaran): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $kelas = Kelas::findOrFail($jadwalPelajaran->kelas_id);
        $guru = Guru::findOrFail($data['guru_id']);

        $jamPelajaran = JamPelajaran::where('id', $data['jam_pelajaran_id'])
            ->where('pola_jam_id', $kelas->pola_jam_id)
            ->isPelajaran()
            ->first();
        if (! $jamPelajaran) {
            abort(404);
        }

        if ($guru->lembaga_id !== $kelas->lembaga_id) {
            $msg = 'Guru harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['guru_id' => [$msg]]], 422);
            }

            return back()->withErrors(['guru_id' => $msg])->withInput();
        }

        if (! empty($data['mata_pelajaran_id'])) {
            $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
            if (! $mataPelajaran || $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
                $msg = 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['mata_pelajaran_id' => [$msg]]], 422);
                }

                return back()->withErrors(['mata_pelajaran_id' => $msg])->withInput();
            }
        }

        if (! empty($data['ruangan_id'])) {
            $ruangan = Ruangan::withoutGlobalScope(TenantScope::class)->find((int) $data['ruangan_id']);
            if (! $ruangan || (! $ruangan->is_shared && $ruangan->lembaga_id !== $kelas->lembaga_id)) {
                $msg = 'Ruangan harus berasal dari lembaga yang sama dengan kelas ini, atau berupa ruangan bersama.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['ruangan_id' => [$msg]]], 422);
                }

                return back()->withErrors(['ruangan_id' => $msg])->withInput();
            }
        }

        $ruanganId = ! empty($data['ruangan_id']) ? (int) $data['ruangan_id'] : ($jadwalPelajaran->ruangan_id ?? $kelas->ruangan_id);

        $dto = new JadwalPelajaranData(
            lembagaId: $kelas->lembaga_id,
            kelasId: $kelas->id,
            guruId: $guru->id,
            jamPelajaranId: (int) $data['jam_pelajaran_id'],
            semesterId: $jadwalPelajaran->semester_id,
            mataPelajaranId: ! empty($data['mata_pelajaran_id']) ? (int) $data['mata_pelajaran_id'] : null,
            ruanganId: $ruanganId
        );

        try {
            $this->updateJadwalPelajaranAction->execute($jadwalPelajaran, $dto);
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            return back()->withErrors($e->errors())->withInput();
        }

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

    public function duplicate(DuplicateJadwalRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        $sourceKelas = Kelas::findOrFail($data['source_kelas_id']);
        $targetKelas = Kelas::findOrFail($data['target_kelas_id']);
        $sourceSemester = Semester::findOrFail($data['source_semester_id']);
        $targetSemester = Semester::findOrFail($data['target_semester_id']);

        $user = $request->user();
        $lembagaId = $user->active_lembaga_id ?: ($user->lembaga_id ?: null);

        if ($lembagaId) {
            abort_if($sourceKelas->lembaga_id !== $lembagaId || $targetKelas->lembaga_id !== $lembagaId, 404);
            abort_if($sourceSemester->tahunAjaran?->lembaga_id !== $lembagaId || $targetSemester->tahunAjaran?->lembaga_id !== $lembagaId, 404);
        } else {
            abort_if($sourceKelas->lembaga_id !== $targetKelas->lembaga_id, 404);
        }

        try {
            $result = $this->duplicateJadwalAction->execute(
                sourceKelas: $sourceKelas,
                sourceSemester: $sourceSemester,
                targetKelas: $targetKelas,
                targetSemester: $targetSemester
            );
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            return redirect()->back()->withErrors($e->errors());
        }

        $msg = "Duplikasi jadwal selesai: {$result['copied']} sesi berhasil disalin";
        if ($result['skipped'] > 0) {
            $msg .= ", {$result['skipped']} sesi dilewati (bentrok slot/ruangan/guru).";
        } else {
            $msg .= '.';
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $msg,
                'copied_count' => $result['copied'],
                'skipped_count' => $result['skipped'],
            ]);
        }

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $targetKelas->id,
            'semester_id' => $targetSemester->id,
        ])->with('status', $msg);
    }

    private function formatSlot(JamPelajaran $jamPelajaran): string
    {
        return $jamPelajaran->hari->label().' '.$jamPelajaran->label;
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
                ->groupBy(fn ($jam) => $jam->hari instanceof \UnitEnum ? $jam->hari->value : $jam->hari);

            foreach ($hariAktif as $hari) {
                $key = $hari instanceof \UnitEnum ? $hari->value : $hari;
                if ($mentah->has($key)) {
                    $jamPelajaranPerHari->push(['hari' => $hari, 'items' => $mentah->get($key)]);
                }
            }
        }

        return $jamPelajaranPerHari;
    }
}
