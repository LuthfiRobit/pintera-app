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
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajaran,id'],
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'wali_kelas_guru_id' => ['nullable', 'exists:guru,id'],
            'pola_jam_id' => ['nullable', 'exists:pola_jam,id'],
        ]);

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
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajaran,id'],
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'wali_kelas_guru_id' => ['nullable', 'exists:guru,id'],
            'pola_jam_id' => ['nullable', 'exists:pola_jam,id'],
        ]);

        $kelas->update($data);

        return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil diperbarui.');
    }
}
