<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Kelas\CreateKelasAction;
use App\Domains\Akademik\Actions\Kelas\UpdateKelasAction;
use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Akademik\Services\FaseDefaultResolver;
use App\Http\Requests\Akademik\StoreKelasRequest;
use App\Http\Requests\Akademik\UpdateKelasRequest;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KelasController extends BaseController
{
    use AuthorizesRequests;

    public function faseSuggestion(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('kelas.view') || $request->user()->can('kelas.create'), 403);

        $lembaga = $request->user()->lembaga;
        $bentukPendidikan = $lembaga?->bentuk_pendidikan ?? '';
        $tingkat = $request->query('tingkat') ?: null;

        $fase = app(FaseDefaultResolver::class)->resolve(
            $bentukPendidikan,
            $tingkat,
            $lembaga?->id
        );

        return response()->json([
            'suggestion' => $fase ? ['id' => $fase->id, 'kode' => $fase->kode, 'nama' => $fase->nama] : null,
        ]);
    }

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

        $kelasList = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.kelas._daftar', [
                'kelasList' => $kelasList,
                'perPage' => $perPage,
            ]);
        }

        return view('admin.kelas.index', [
            'kelasList'       => $kelasList,
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'perPage'         => $perPage,
            'totalKelas'      => Kelas::count(),
            'totalTaAktif'    => Kelas::whereHas('tahunAjaran', fn($q) => $q->where('status_aktif', true))->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('kelas.create');

        return view('admin.kelas.create', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
            'polaJamList' => PolaJam::orderBy('nama')->get(),
            'faseList' => Fase::orderBy('urutan')->get(),
        ]);
    }

    public function store(StoreKelasRequest $request, CreateKelasAction $action): RedirectResponse
    {
        $this->authorize('kelas.create');

        $data = KelasData::fromValidated($request->validated());

        $lembagaIdOverride = null;
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaIdOverride = session('active_lembaga_id');

            if ($lembagaIdOverride === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat kelas.'])->withInput();
            }
        }

        $action->execute($data, $lembagaIdOverride);

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
            'faseList' => Fase::orderBy('urutan')->get(),
        ]);
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas, UpdateKelasAction $action): RedirectResponse
    {
        $this->authorize('kelas.edit');

        $data = KelasData::fromValidated($request->validated());

        $action->execute($kelas, $data);

        return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil diperbarui.');
    }
}
