<?php

namespace App\Http\Controllers\Lembaga\Akademik;

use App\Domains\Akademik\Actions\MataPelajaran\CreateMataPelajaranAction;
use App\Domains\Akademik\Actions\MataPelajaran\UpdateMataPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\MataPelajaranData;
use App\Domains\Akademik\Enums\BentukPendidikan;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Enums\KelompokMataPelajaran;
use App\Enums\StatusMataPelajaran;
use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MataPelajaranController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    public function index(Request $request): View
    {
        $this->authorize('mata-pelajaran.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = MataPelajaran::with('lembaga')->orderBy('no_urut')->orderBy('nama');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('kode', 'like', '%'.$search.'%');
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
            return view('portals.lembaga.akademik.mata-pelajaran._daftar', [
                'mataPelajaranList' => $paginated,
                'perPage' => $perPage,
                ...$this->scopeHeaderData($request),
            ]);
        }

        $lembagaAktifId = $this->resolveActiveLembagaId($request->user());

        return view('portals.lembaga.akademik.mata-pelajaran.index', [
            'mataPelajaranList' => $paginated,
            'tipeList' => TipeMataPelajaran::cases(),
            'kelompokList' => KelompokMataPelajaran::cases(),
            'statusList' => StatusMataPelajaran::cases(),
            'perPage' => $perPage,
            'totalMapel' => MataPelajaran::count(),
            'countKurikulum' => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),
            'isPaud' => in_array(
                Lembaga::find($lembagaAktifId)?->bentuk_pendidikan,
                [
                    BentukPendidikan::Kb->value,
                    BentukPendidikan::Tpa->value,
                    BentukPendidikan::Sps->value,
                    BentukPendidikan::Tk->value,
                ],
                true
            ),
            ...$this->scopeHeaderData($request),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('mata-pelajaran.create');

        $lembagaId = $this->resolveActiveLembagaId($request->user());
        if ($lembagaId === null) {
            return redirect()->route('admin.mata-pelajaran.index')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah mata pelajaran.']);
        }

        return view('portals.lembaga.akademik.mata-pelajaran.create', [
            'tipeList' => TipeMataPelajaran::cases(),
            'kelompokList' => KelompokMataPelajaran::cases(),
            'statusList' => StatusMataPelajaran::cases(),
            ...$this->scopeHeaderData($request),
        ]);
    }

    /**
     * Info scope yayasan/lembaga yang sedang aktif, ditampilkan sebagai badge di header
     * halaman (pola sama seperti admin/siswa/index.blade.php) -- HANYA relevan untuk aktor
     * berscope yayasan (punya switcher lembaga).
     *
     * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
     */
    private function scopeHeaderData(Request $request): array
    {
        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        $lembagaId = $this->resolveActiveLembagaId($request->user());

        return [
            'isYayasan' => $isYayasan,
            'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
        ];
    }

    public function store(Request $request, CreateMataPelajaranAction $action): RedirectResponse
    {
        $this->authorize('mata-pelajaran.create');

        $lembagaId = $this->resolveActiveLembagaId($request->user());
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
            'tipe' => ['required', 'in:mapel'],
            'kelompok' => ['nullable', 'string', Rule::enum(KelompokMataPelajaran::class)],
            'status' => ['required', 'string', Rule::enum(StatusMataPelajaran::class)],
        ]);

        $action->execute(MataPelajaranData::fromArray($data, $lembagaId));

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil disimpan.');
    }

    public function edit(MataPelajaran $mataPelajaran): View
    {
        $this->authorize('mata-pelajaran.edit');

        return view('portals.lembaga.akademik.mata-pelajaran.edit', [
            'mataPelajaran' => $mataPelajaran,
            'tipeList' => TipeMataPelajaran::cases(),
            'kelompokList' => KelompokMataPelajaran::cases(),
            'statusList' => StatusMataPelajaran::cases(),
        ]);
    }

    public function update(Request $request, MataPelajaran $mataPelajaran, UpdateMataPelajaranAction $action): RedirectResponse
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
            'tipe' => ['required', 'in:mapel'],
            'kelompok' => ['nullable', 'string', Rule::enum(KelompokMataPelajaran::class)],
            'status' => ['required', 'string', Rule::enum(StatusMataPelajaran::class)],
        ]);

        $action->execute($mataPelajaran, MataPelajaranData::fromArray($data, $lembagaId));

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil diperbarui.');
    }
}
