<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class SiswaController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('siswa.view');

        return view('admin.siswa.index', [
            'siswaList' => Siswa::with('kelas')->orderBy('nama_lengkap')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('siswa.create');

        return view('admin.siswa.create', [
            'kelasList' => Kelas::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('siswa.create');

        $data = $this->validateSiswa($request);
        $data['sumber_data'] = SumberDataSiswa::Manual->value;

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah siswa.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        Siswa::create($data);

        return redirect()->route('admin.siswa.index')->with('status', 'Siswa berhasil disimpan.');
    }

    public function edit(Siswa $siswa): View
    {
        $this->authorize('siswa.edit');

        return view('admin.siswa.edit', [
            'siswa' => $siswa,
            'kelasList' => Kelas::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $data = $this->validateSiswa($request, $siswa);

        $siswa->update($data);

        return redirect()->route('admin.siswa.index')->with('status', 'Siswa berhasil diperbarui.');
    }

    private function validateSiswa(Request $request, ?Siswa $current = null): array
    {
        return $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'nis' => [
                'required', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($current) {
                    $exists = Siswa::withoutGlobalScopes()
                        ->where('lembaga_id', $current?->lembaga_id ?? auth()->user()->lembaga_id ?? session('active_lembaga_id'))
                        ->where('nis', $value)
                        ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
                        ->exists();
                    if ($exists) {
                        $fail('NIS sudah dipakai siswa lain di lembaga ini.');
                    }
                },
            ],
            'nisn' => ['nullable', 'string', 'max:20'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
