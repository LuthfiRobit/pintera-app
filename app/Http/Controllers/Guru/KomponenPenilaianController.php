<?php

namespace App\Http\Controllers\Guru;

use App\Models\JadwalPelajaran;
use App\Models\KomponenPenilaian;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KomponenPenilaianController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');

        $guru = $request->user()->guru;
        $mapelIds = $guru ? $this->mapelDiajar($guru->id) : collect();

        $mataPelajaranId = $request->query('mata_pelajaran_id');

        $komponenList = KomponenPenilaian::whereIn('mata_pelajaran_id', $mapelIds)
            ->with(['mataPelajaran', 'semester.tahunAjaran'])
            ->when($mataPelajaranId, fn ($q) => $q->where('mata_pelajaran_id', $mataPelajaranId))
            ->orderByDesc('id')
            ->get();

        return view('guru.komponen-penilaian.index', [
            'mataPelajaranList' => MataPelajaran::whereIn('id', $mapelIds)->orderBy('nama')->get(),
            'mataPelajaranId' => $mataPelajaranId,
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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');

        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Profil guru tidak ditemukan untuk akun ini.');

        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'kktp' => ['nullable', 'string'],
        ]);

        $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->exists();

        abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi mata pelajaran dan semester ini.');

        KomponenPenilaian::create($data);

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

    public function update(Request $request, KomponenPenilaian $komponenPenilaian): RedirectResponse
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');
        $this->authorizeMengajarMapel($komponenPenilaian);

        // Unlike the admin-side controller, mata_pelajaran_id/semester_id are never
        // editable here — a guru reassigning a TP to a different mapel/semester isn't a
        // real workflow; delete and recreate under the right one instead.
        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'kktp' => ['nullable', 'string'],
        ]);

        $komponenPenilaian->kode = $data['kode'] ?? null;
        $komponenPenilaian->deskripsi = $data['deskripsi'];
        $komponenPenilaian->kktp = $data['kktp'] ?? null;
        $komponenPenilaian->save();

        return redirect()->route('guru.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil diperbarui.');
    }

    public function destroy(KomponenPenilaian $komponenPenilaian): RedirectResponse
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');
        $this->authorizeMengajarMapel($komponenPenilaian);

        if ($komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists()) {
            return back()->withErrors(['komponen_penilaian' => 'Komponen ini sudah dipakai pada asesmen atau nilai siswa — tidak bisa dihapus.']);
        }

        $komponenPenilaian->delete();

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
