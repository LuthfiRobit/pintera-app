<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\PolaJam;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KelasController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kelas.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = Kelas::with(['tahunAjaran', 'waliKelas'])->orderBy('nama');

        if ($search = $request->input('search')) {
            $query->where('nama', 'like', '%' . $search . '%');
        }

        if ($tahunAjaranId = $request->input('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        return view('admin.kelas.index', [
            'kelasList'       => $query->paginate($perPage)->withQueryString(),
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'perPage'         => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('kelas.create');

        return view('admin.kelas.create', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
            'polaJamList' => PolaJam::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kelas.create');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer'],
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'wali_kelas_guru_id' => ['nullable', 'integer'],
            'pola_jam_id' => ['nullable', 'integer'],
        ]);

        $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
        abort_if($tahunAjaran === null, 404);
        $data['tahun_ajaran_id'] = $tahunAjaran->id;

        if (! empty($data['wali_kelas_guru_id'])) {
            $guru = Guru::find($data['wali_kelas_guru_id']);
            abort_if($guru === null || $guru->lembaga_id !== $tahunAjaran->lembaga_id, 404);
            $data['wali_kelas_guru_id'] = $guru->id;
        }

        if (! empty($data['pola_jam_id'])) {
            $polaJam = PolaJam::find($data['pola_jam_id']);
            abort_if($polaJam === null || $polaJam->lembaga_id !== $tahunAjaran->lembaga_id, 404);
            $data['pola_jam_id'] = $polaJam->id;
        }

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat kelas.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        Kelas::create($data);

        return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil disimpan.');
    }

    public function edit(Kelas $kelas): View
    {
        $this->authorize('kelas.edit');

        return view('admin.kelas.edit', [
            'kelas' => $kelas,
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
            'polaJamList' => PolaJam::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Kelas $kelas): RedirectResponse
    {
        $this->authorize('kelas.edit');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer'],
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'wali_kelas_guru_id' => ['nullable', 'integer'],
            'pola_jam_id' => ['nullable', 'integer'],
        ]);

        $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
        abort_if($tahunAjaran === null || $tahunAjaran->lembaga_id !== $kelas->lembaga_id, 404);
        $data['tahun_ajaran_id'] = $tahunAjaran->id;

        if (! empty($data['wali_kelas_guru_id'])) {
            $guru = Guru::find($data['wali_kelas_guru_id']);
            abort_if($guru === null || $guru->lembaga_id !== $kelas->lembaga_id, 404);
            $data['wali_kelas_guru_id'] = $guru->id;
        }

        if (! empty($data['pola_jam_id'])) {
            $polaJam = PolaJam::find($data['pola_jam_id']);
            abort_if($polaJam === null || $polaJam->lembaga_id !== $kelas->lembaga_id, 404);
            $data['pola_jam_id'] = $polaJam->id;
        }

        $kelas->update($data);

        return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil diperbarui.');
    }
}
