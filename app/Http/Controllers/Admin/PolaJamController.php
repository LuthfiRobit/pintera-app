<?php

namespace App\Http\Controllers\Admin;

use App\Models\Kelas;
use App\Models\PolaJam;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PolaJamController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('pola-jam.view');

        return view('admin.pola-jam.index', [
            'polaJamList' => PolaJam::with(['jamPelajaran', 'lembaga', 'kelas.tahunAjaran'])->orderBy('nama')->get(),
            'kelasList' => Kelas::with('tahunAjaran')->orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('pola-jam.create');

        return view('admin.pola-jam.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        PolaJam::create($data);

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dibuat.');
    }

    public function edit(PolaJam $polaJam): View
    {
        $this->authorize('pola-jam.edit');

        return view('admin.pola-jam.edit', ['polaJam' => $polaJam]);
    }

    public function update(Request $request, PolaJam $polaJam): RedirectResponse
    {
        $this->authorize('pola-jam.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $polaJam->update($data);

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil diperbarui.');
    }

    public function destroy(PolaJam $polaJam): RedirectResponse
    {
        $this->authorize('pola-jam.delete');

        if ($polaJam->kelas()->exists()) {
            return back()->withErrors(['pola_jam' => 'Pola jam ini masih dipakai oleh satu atau lebih kelas — lepaskan dulu sebelum menghapus.']);
        }

        if ($polaJam->jamPelajaran()->whereHas('jadwalPelajaran')->exists()) {
            return back()->withErrors(['pola_jam' => 'Pola jam ini memiliki jam pelajaran yang sudah dipakai di Jadwal Pelajaran — hapus jadwalnya dulu sebelum menghapus pola jam ini.']);
        }

        $polaJam->delete();

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dihapus.');
    }

    public function assignKelas(Request $request, PolaJam $polaJam): RedirectResponse
    {
        $this->authorize('kelas.edit');

        $data = $request->validate([
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => ['integer'],
        ]);
        $kelasIds = $data['kelas_ids'] ?? [];

        $kelasTerpilih = Kelas::whereIn('id', $kelasIds)->get();

        if ($kelasTerpilih->count() !== count($kelasIds)) {
            return back()->withErrors(['kelas_ids' => 'Salah satu kelas yang dipilih tidak ditemukan.']);
        }

        foreach ($kelasTerpilih as $kelas) {
            if ($kelas->lembaga_id !== $polaJam->lembaga_id) {
                return back()->withErrors(['kelas_ids' => 'Kelas dan pola jam harus berasal dari lembaga yang sama.']);
            }
        }

        Kelas::where('pola_jam_id', $polaJam->id)->whereNotIn('id', $kelasIds)->update(['pola_jam_id' => null]);
        Kelas::whereIn('id', $kelasIds)->update(['pola_jam_id' => $polaJam->id]);

        return redirect()->route('admin.pola-jam.index')->with('status', 'Tautan kelas untuk pola jam ini berhasil disimpan.');
    }
}
