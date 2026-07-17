<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class LembagaController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('lembaga.view');

        $query = $request->user()->widestScopeLevel() === 'yayasan'
            ? Lembaga::query()
            : Lembaga::where('id', $request->user()->lembaga_id);

        $query->when($request->filled('cari'), function ($q) use ($request) {
            $cari = $request->query('cari');
            $q->where(function ($q) use ($cari) {
                $q->where('nama', 'like', "%{$cari}%")->orWhere('npsn', 'like', "%{$cari}%");
            });
        })
            ->when($request->filled('bentuk'), fn ($q) => $q->where('bentuk_pendidikan', $request->query('bentuk')))
            ->when($request->filled('status'), fn ($q) => $q->where('status_sekolah', $request->query('status')));

        $lembaga = $query->orderBy('nama')->paginate(10)->withQueryString();

        return view('admin.lembaga.index', ['lembaga' => $lembaga]);
    }

    public function create(Request $request): View
    {
        $this->authorize('lembaga.create');
        abort_unless($request->user()->widestScopeLevel() === 'yayasan', 403);

        return view('admin.lembaga.create', ['yayasanList' => Yayasan::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('lembaga.create');
        abort_unless($request->user()->widestScopeLevel() === 'yayasan', 403);

        $data = $this->validated($request);

        Lembaga::create($data);

        return redirect()->route('admin.lembaga.index')->with('status', 'Lembaga berhasil dibuat.');
    }

    public function edit(Request $request, Lembaga $lembaga): View
    {
        $this->authorize('lembaga.edit');
        $this->authorizeOwnLembaga($request, $lembaga);

        return view('admin.lembaga.edit', ['lembaga' => $lembaga]);
    }

    public function update(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $this->authorizeOwnLembaga($request, $lembaga);

        $lembaga->update($this->validated($request));

        return redirect()->route('admin.lembaga.index')->with('status', 'Lembaga berhasil diperbarui.');
    }

    private function authorizeOwnLembaga(Request $request, Lembaga $lembaga): void
    {
        $isYayasanScope = $request->user()->widestScopeLevel() === 'yayasan';
        $isOwnLembaga = $lembaga->id === $request->user()->lembaga_id;

        abort_unless($isYayasanScope || $isOwnLembaga, 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'yayasan_id' => ['required', 'exists:yayasan,id'],
            'npsn' => ['required', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:255'],
            'bentuk_pendidikan' => ['required', 'in:KB,TPA,SPS,TK,SD,SMP,SMA,SMK,SLB'],
            'status_sekolah' => ['required', 'in:negeri,swasta'],
            'naungan' => ['required', 'in:kemendikdasmen,kemenag'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
        ]);
    }
}
