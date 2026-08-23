<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KelompokMataPelajaran;
use App\Enums\StatusMataPelajaran;
use App\Enums\TipeMataPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MataPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('mata-pelajaran.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = MataPelajaran::orderBy('no_urut')->orderBy('nama');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('kode', 'like', '%' . $search . '%');
            });
        }

        if ($tipe = $request->input('tipe')) {
            $query->where('tipe', $tipe);
        }

        if ($kelompok = $request->input('kelompok')) {
            $query->where('kelompok', $kelompok);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.mata-pelajaran._daftar', [
                'mataPelajaranList' => $paginated,
                'perPage'           => $perPage,
            ]);
        }

        return view('admin.mata-pelajaran.index', [
            'mataPelajaranList' => $paginated,
            'tipeList'          => TipeMataPelajaran::cases(),
            'kelompokList'      => KelompokMataPelajaran::cases(),
            'statusList'        => StatusMataPelajaran::cases(),
            'perPage'           => $perPage,
            'totalMapel'        => MataPelajaran::count(),
            'countKurikulum'    => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),
            'countAspek'        => MataPelajaran::where('tipe', TipeMataPelajaran::AspekPerkembangan->value)->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('mata-pelajaran.create');

        return view('admin.mata-pelajaran.create', [
            'tipeList'     => TipeMataPelajaran::cases(),
            'kelompokList' => KelompokMataPelajaran::cases(),
            'statusList'   => StatusMataPelajaran::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('mata-pelajaran.create');

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan' ? session('active_lembaga_id') : $request->user()->lembaga_id;
        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif terlebih dahulu.'])->withInput();
        }

        $data = $request->validate([
            'kode' => [
                'required', 'string', 'max:20',
                Rule::unique('mata_pelajaran', 'kode')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'no_urut' => ['required', 'integer', 'min:1', 'max:9999'],
            'tipe' => ['required', 'in:mapel,aspek_perkembangan'],
            'kelompok' => ['nullable', 'string', Rule::enum(KelompokMataPelajaran::class)],
            'status' => ['required', 'string', Rule::enum(StatusMataPelajaran::class)],
        ]);

        $data['lembaga_id'] = $lembagaId;
        MataPelajaran::create($data);

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil disimpan.');
    }

    public function edit(MataPelajaran $mataPelajaran): View
    {
        $this->authorize('mata-pelajaran.edit');

        return view('admin.mata-pelajaran.edit', [
            'mataPelajaran' => $mataPelajaran,
            'tipeList'      => TipeMataPelajaran::cases(),
            'kelompokList'  => KelompokMataPelajaran::cases(),
            'statusList'    => StatusMataPelajaran::cases(),
        ]);
    }

    public function update(Request $request, MataPelajaran $mataPelajaran): RedirectResponse
    {
        $this->authorize('mata-pelajaran.edit');

        $lembagaId = $mataPelajaran->lembaga_id;

        $data = $request->validate([
            'kode' => [
                'required', 'string', 'max:20',
                Rule::unique('mata_pelajaran', 'kode')->where(fn ($query) => $query->where('lembaga_id', $lembagaId))->ignore($mataPelajaran->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'no_urut' => ['required', 'integer', 'min:1', 'max:9999'],
            'tipe' => ['required', 'in:mapel,aspek_perkembangan'],
            'kelompok' => ['nullable', 'string', Rule::enum(KelompokMataPelajaran::class)],
            'status' => ['required', 'string', Rule::enum(StatusMataPelajaran::class)],
        ]);

        $mataPelajaran->update($data);

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil diperbarui.');
    }
}
