<?php

namespace App\Http\Controllers\Guru;

use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Models\KomponenPenilaian;
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
        $this->authorize('komponen-penilaian.kelola-sendiri');

        $guru = $request->user()->guru;
        $mapelIds = $guru ? $this->mapelDiajar($guru->id) : collect();

        $tahunAjaranId = $request->query('tahun_ajaran_id') ?? TahunAjaran::where('status_aktif', true)->value('id');
        $semesterId = $request->query('semester_id');
        $mataPelajaranId = $request->query('mata_pelajaran_id');
        $search = $request->query('search');

        $komponenList = KomponenPenilaian::whereIn('mata_pelajaran_id', $mapelIds)
            ->with(['mataPelajaran', 'semester.tahunAjaran'])
            ->when($tahunAjaranId, fn ($q) => $q->whereHas('semester', fn ($q2) => $q2->where('tahun_ajaran_id', $tahunAjaranId)))
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->when($mataPelajaranId, fn ($q) => $q->where('mata_pelajaran_id', $mataPelajaranId))
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('kode', 'like', "%{$search}%")->orWhere('deskripsi', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->get();

        if ($request->ajax()) {
            return view('guru.komponen-penilaian._daftar', ['komponenList' => $komponenList])->render();
        }

        return view('guru.komponen-penilaian.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
            'mataPelajaranList' => MataPelajaran::whereIn('id', $mapelIds)->orderBy('nama')->get(),
            'semesterId' => $semesterId,
            'mataPelajaranId' => $mataPelajaranId,
            'search' => $search,
            'komponenList' => $komponenList,
        ]);
    }


    public function create(Request $request): View
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');

        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Profil guru tidak ditemukan untuk akun ini.');

        $jadwalList = JadwalPelajaran::where('guru_id', $guru->id)->get();
        $mapelIds = $jadwalList->pluck('mata_pelajaran_id')->filter()->unique();
        $semesterIds = $jadwalList->pluck('semester_id')->unique();

        return view('guru.komponen-penilaian.create', [
            'mataPelajaranList' => MataPelajaran::whereIn('id', $mapelIds)->orderBy('nama')->get(),
            'semesterList' => Semester::whereIn('id', $semesterIds)->with('tahunAjaran')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');

        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Profil guru tidak ditemukan untuk akun ini.');

        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
        ]);

        $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->exists();

        abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi mata pelajaran dan semester ini.');

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

        return redirect()->route('guru.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil disimpan.');
    }

    public function edit(KomponenPenilaian $komponenPenilaian): View
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');
        $this->authorizeMengajarMapel($komponenPenilaian);

        $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

        return view('guru.komponen-penilaian.edit', [
            'komponenPenilaian' => $komponenPenilaian->load(['mataPelajaran', 'semester.tahunAjaran']),
            'dipakai' => $dipakai,
        ]);
    }

    public function update(Request $request, KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');
        $this->authorizeMengajarMapel($komponenPenilaian);

        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
        ]);

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

        return redirect()->route('guru.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil diperbarui.');
    }

    public function destroy(KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');
        $this->authorizeMengajarMapel($komponenPenilaian);

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

        return redirect()->route('guru.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil dihapus.');
    }

    private function mapelDiajar(int $guruId)
    {
        return JadwalPelajaran::where('guru_id', $guruId)->pluck('mata_pelajaran_id')->filter()->unique();
    }

    private function authorizeMengajarMapel(KomponenPenilaian $komponenPenilaian): void
    {
        $guru = auth()->user()->guru;
        $mengajar = $guru && JadwalPelajaran::where('guru_id', $guru->id)
            ->where('mata_pelajaran_id', $komponenPenilaian->mata_pelajaran_id)
            ->exists();

        abort_unless($mengajar, 403, 'Anda tidak mengajar mata pelajaran ini.');
    }
}
