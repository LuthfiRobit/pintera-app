<?php

namespace App\Http\Controllers\Guru;

use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Actions\Penilaian\CreateKomponenPenilaianAction;
use App\Domains\Akademik\Actions\Penilaian\DeleteKomponenPenilaianAction;
use App\Domains\Akademik\Actions\Penilaian\UpdateKomponenPenilaianAction;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Http\Requests\Akademik\StoreKomponenPenilaianSendiriRequest;
use App\Http\Requests\Akademik\UpdateKomponenPenilaianSendiriRequest;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KomponenPenilaianController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreateKomponenPenilaianAction $createKomponenPenilaianAction,
        private readonly UpdateKomponenPenilaianAction $updateKomponenPenilaianAction,
        private readonly DeleteKomponenPenilaianAction $deleteKomponenPenilaianAction,
    ) {
    }

    public function index(Request $request): View|string
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');

        $guru = $request->user()->guru;
        $mapelIds = $guru ? $this->mapelDiajar($guru->id) : collect();

        $tahunAjaranId = $request->query('tahun_ajaran_id') ?? TahunAjaran::where('status_aktif', true)->value('id');
        $semesterId = $request->query('semester_id');
        $mataPelajaranId = $request->query('mata_pelajaran_id');
        $search = $request->query('search');

        $komponenList = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')
            ->whereIn('subjek_id', $mapelIds)
            ->with(['subjek', 'semester.tahunAjaran'])
            ->when($tahunAjaranId, fn ($q) => $q->whereHas('semester', fn ($q2) => $q2->where('tahun_ajaran_id', $tahunAjaranId)))
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->when($mataPelajaranId, fn ($q) => $q->where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mataPelajaranId))
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('kode', 'like', "%{$search}%")->orWhere('deskripsi', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->get();

        if ($request->ajax()) {
            return view('portals.guru.akademik.komponen-penilaian._daftar', ['komponenList' => $komponenList])->render();
        }

        return view('portals.guru.akademik.komponen-penilaian.index', [
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

        return view('portals.guru.akademik.komponen-penilaian.create', [
            'mataPelajaranList' => MataPelajaran::whereIn('id', $mapelIds)->orderBy('nama')->get(),
            'elemenCpList' => ElemenCp::orderBy('no_urut')->get(),
            'semesterList' => Semester::whereIn('id', $semesterIds)->with('tahunAjaran')->orderByDesc('id')->get(),
            'bentukPendidikan' => $request->user()->lembaga?->bentuk_pendidikan,
        ]);
    }

    public function store(StoreKomponenPenilaianSendiriRequest $request): RedirectResponse|JsonResponse
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Profil guru tidak ditemukan untuk akun ini.');

        $data = $request->validated();
        if ($data['subjek_type'] === 'mata_pelajaran') {
            $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
                ->where('mata_pelajaran_id', $data['subjek_id'])
                ->where('semester_id', $data['semester_id'])
                ->exists();

            abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi mata pelajaran dan semester ini.');
        }

        try {
            $this->createKomponenPenilaianAction->execute($request->toDTO());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->collapse()->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors($e->errors());
        }

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

        return view('portals.guru.akademik.komponen-penilaian.edit', [
            'komponenPenilaian' => $komponenPenilaian->load(['subjek', 'semester.tahunAjaran']),
            'dipakai' => $dipakai,
            'elemenCpList' => ElemenCp::orderBy('no_urut')->get(),
            'bentukPendidikan' => auth()->user()->lembaga?->bentuk_pendidikan,
        ]);
    }

    public function update(UpdateKomponenPenilaianSendiriRequest $request, KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $this->authorizeMengajarMapel($komponenPenilaian);

        try {
            $this->updateKomponenPenilaianAction->execute($komponenPenilaian, $request->toDTO());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->collapse()->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors($e->errors());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil diperbarui.']);
        }

        return redirect()->route('guru.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil diperbarui.');
    }

    public function destroy(KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');
        $this->authorizeMengajarMapel($komponenPenilaian);

        try {
            $this->deleteKomponenPenilaianAction->execute($komponenPenilaian);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->collapse()->first();
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withErrors($e->errors());
        }

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
        if ($komponenPenilaian->subjek_type === 'mata_pelajaran') {
            $mengajar = $guru && JadwalPelajaran::where('guru_id', $guru->id)
                ->where('mata_pelajaran_id', $komponenPenilaian->subjek_id)
                ->exists();

            abort_unless($mengajar, 403, 'Anda tidak mengajar mata pelajaran ini.');
        }
    }
}
