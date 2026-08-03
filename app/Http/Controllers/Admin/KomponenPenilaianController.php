<?php

namespace App\Http\Controllers\Admin;

use App\Models\KomponenPenilaian;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KomponenPenilaianController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|string
    {
        $this->authorize('komponen-penilaian.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        if ($tahunAjaranId === null && ! $request->query->has('tahun_ajaran_id')) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }
        $semesterId = $request->query('semester_id');
        $mataPelajaranId = $request->query('mata_pelajaran_id');
        $search = $request->query('search');

        $komponenList = KomponenPenilaian::whereHas('mataPelajaran')
            ->with(['mataPelajaran', 'semester.tahunAjaran'])
            ->when($tahunAjaranId, fn ($q) => $q->whereHas('semester', fn ($q2) => $q2->where('tahun_ajaran_id', $tahunAjaranId)))
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->when($mataPelajaranId, fn ($q) => $q->where('mata_pelajaran_id', $mataPelajaranId))
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('kode', 'like', "%{$search}%")->orWhere('deskripsi', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->get();

        if ($request->ajax()) {
            return view('admin.komponen-penilaian._daftar', ['komponenList' => $komponenList])->render();
        }

        return view('admin.komponen-penilaian.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'semesterId' => $semesterId,
            'mataPelajaranId' => $mataPelajaranId,
            'search' => $search,
            'komponenList' => $komponenList,
        ]);
    }

    public function opsi(Request $request): JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $data = $request->validate(['tahun_ajaran_id' => ['required', 'integer']]);

        $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
        abort_if($tahunAjaran === null, 404);

        return response()->json([
            'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('komponen-penilaian.kelola');

        $tahunAjaranId = old('tahun_ajaran_id', $request->query('tahun_ajaran_id'));
        if (! $tahunAjaranId) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        return view('admin.komponen-penilaian.create', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
        ]);

        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
        $semester = Semester::find($data['semester_id']);

        abort_if($mataPelajaran === null || $semester === null, 404);
        abort_if($mataPelajaran->lembaga_id !== $semester->lembaga_id, 404);

        $data['bobot'] = $data['bobot'] ?? 10;
        $existingSum = KomponenPenilaian::where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->sum('bobot');

        if (($existingSum + (int) $data['bobot']) > 100) {
            $remaining = max(0, 100 - $existingSum);
            $msg = "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk mata pelajaran ini adalah {$remaining}%.";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors(['bobot' => $msg]);
        }

        KomponenPenilaian::create($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil disimpan.']);
        }

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil disimpan.');
    }

    public function edit(KomponenPenilaian $komponenPenilaian): View
    {
        $this->authorize('komponen-penilaian.kelola');

        $mataPelajaran = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
        if (! $mataPelajaran) {
            abort(404);
        }

        $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

        return view('admin.komponen-penilaian.edit', [
            'komponenPenilaian' => $komponenPenilaian->load(['mataPelajaran', 'semester.tahunAjaran']),
            'dipakai' => $dipakai,
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'semesterList' => Semester::with('tahunAjaran')->orderByDesc('id')->get(),
        ]);
    }

    public function update(Request $request, KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $mataPelajaranSaatIni = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
        if (! $mataPelajaranSaatIni) {
            abort(404);
        }

        $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

        $rules = [
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
        ];
        if (! $dipakai) {
            $rules['mata_pelajaran_id'] = ['required', 'integer'];
            $rules['semester_id'] = ['required', 'integer'];
        }

        $data = $request->validate($rules);

        if (! $dipakai) {
            $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
            $semester = Semester::find($data['semester_id']);
            abort_if($mataPelajaran === null || $semester === null, 404);
            abort_if($mataPelajaran->lembaga_id !== $semester->lembaga_id, 404);

            $komponenPenilaian->mata_pelajaran_id = $data['mata_pelajaran_id'];
            $komponenPenilaian->semester_id = $data['semester_id'];
        }

        $newBobot = $data['bobot'] ?? $komponenPenilaian->bobot;
        $existingSum = KomponenPenilaian::where('mata_pelajaran_id', $komponenPenilaian->mata_pelajaran_id)
            ->where('semester_id', $komponenPenilaian->semester_id)
            ->where('id', '!=', $komponenPenilaian->id)
            ->sum('bobot');

        if (($existingSum + (int) $newBobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            $msg = "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk mata pelajaran ini adalah {$remaining}%.";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors(['bobot' => $msg]);
        }

        $komponenPenilaian->kode = $data['kode'] ?? null;
        $komponenPenilaian->deskripsi = $data['deskripsi'];
        $komponenPenilaian->bobot = $newBobot;
        $komponenPenilaian->kktp = $data['kktp'] ?? null;
        $komponenPenilaian->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil diperbarui.']);
        }

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil diperbarui.');
    }

    public function destroy(KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $mataPelajaran = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
        if (! $mataPelajaran) {
            abort(404);
        }

        if ($komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists()) {
            $msg = 'Komponen ini sudah dipakai pada asesmen atau nilai siswa — tidak bisa dihapus.';
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withErrors(['komponen_penilaian' => $msg]);
        }

        $komponenPenilaian->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil dihapus.']);
        }

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil dihapus.');
    }
}
