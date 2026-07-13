<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GelombangPpdbController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('manage-ppdb');

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->first();

        $tahunAjaranSebelumnya = $tahunAjaranAktif
            ? TahunAjaran::where('id', '!=', $tahunAjaranAktif->id)
                ->where('tanggal_mulai', '<', $tahunAjaranAktif->tanggal_mulai)
                ->orderByDesc('tanggal_mulai')
                ->first()
            : null;

        // Only offer the "copy from previous year" callout if that candidate
        // year actually has Gelombang or Jalur data to copy — otherwise the
        // button would silently succeed while copying zero rows.
        if ($tahunAjaranSebelumnya
            && ! GelombangPpdb::where('tahun_ajaran_id', $tahunAjaranSebelumnya->id)->exists()
            && ! JalurPpdb::where('tahun_ajaran_id', $tahunAjaranSebelumnya->id)->exists()) {
            $tahunAjaranSebelumnya = null;
        }

        return view('admin.gelombang-ppdb.index', [
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'gelombangList' => $tahunAjaranAktif
                ? GelombangPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('tanggal_buka')->get()
                : collect(),
            'tahunAjaranSebelumnya' => $tahunAjaranSebelumnya,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('manage-ppdb');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return redirect()->route('admin.gelombang-ppdb.index')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah gelombang.']);
        }

        return view('admin.gelombang-ppdb.create', [
            'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->firstOrFail(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah gelombang.'])->withInput();
        }

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->firstOrFail();
        $data = $this->validated($request, $tahunAjaranAktif->id);
        $data['tahun_ajaran_id'] = $tahunAjaranAktif->id;
        // BelongsToTenant only auto-fills lembaga_id when the acting user's
        // widestScopeLevel() === 'lembaga'. A yayasan-scoped actor with
        // manage-ppdb (via yayasan_super_admin) would otherwise leave this
        // NOT NULL column unset. The resolved active TahunAjaran's own
        // lembaga_id is authoritative for both scopes, so set it explicitly.
        $data['lembaga_id'] = $tahunAjaranAktif->lembaga_id;

        GelombangPpdb::create($data);

        return redirect()->route('admin.gelombang-ppdb.index')->with('status', 'Gelombang berhasil ditambahkan.');
    }

    public function edit(GelombangPpdb $gelombangPpdb): View
    {
        $this->authorize('manage-ppdb');

        return view('admin.gelombang-ppdb.edit', ['gelombang' => $gelombangPpdb]);
    }

    public function update(Request $request, GelombangPpdb $gelombangPpdb): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $gelombangPpdb->update($this->validated($request, $gelombangPpdb->tahun_ajaran_id, $gelombangPpdb));

        return redirect()->route('admin.gelombang-ppdb.index')->with('status', 'Gelombang berhasil diperbarui.');
    }

    private function validated(Request $request, int $tahunAjaranId, ?GelombangPpdb $current = null): array
    {
        return $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('gelombang_ppdb', 'nama')
                    ->where(fn ($query) => $query->where('tahun_ajaran_id', $tahunAjaranId))
                    ->ignore($current?->id),
            ],
            'tanggal_buka' => ['required', 'date'],
            'tanggal_tutup' => ['required', 'date', 'after:tanggal_buka'],
            'kuota' => ['required', 'integer', 'min:1'],
        ]);
    }
}
