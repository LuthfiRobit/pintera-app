<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JalurPpdbController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('jalur-ppdb.view');

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

        return view('admin.jalur-ppdb.index', [
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'jalurList' => $tahunAjaranAktif
                ? JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
                : collect(),
            'tahunAjaranSebelumnya' => $tahunAjaranSebelumnya,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('jalur-ppdb.create');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return redirect()->route('admin.jalur-ppdb.index')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jalur.']);
        }

        return view('admin.jalur-ppdb.create', [
            'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->firstOrFail(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jalur-ppdb.create');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jalur.'])->withInput();
        }

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->firstOrFail();
        $data = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('jalur_ppdb', 'nama')
                    ->where(fn ($query) => $query->where('tahun_ajaran_id', $tahunAjaranAktif->id)),
            ],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['tahun_ajaran_id'] = $tahunAjaranAktif->id;
        // BelongsToTenant only auto-fills lembaga_id when the acting user's
        // widestScopeLevel() === 'lembaga'. A yayasan-scoped actor with
        // manage-ppdb (via yayasan_super_admin) would otherwise leave this
        // NOT NULL column unset. The resolved active TahunAjaran's own
        // lembaga_id is authoritative for both scopes, so set it explicitly.
        $data['lembaga_id'] = $tahunAjaranAktif->lembaga_id;

        $jalur = JalurPpdb::create($data);

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Jalur berhasil ditambahkan. Lengkapi formulir, dokumen, dan jadwal seleksinya di bawah.');
    }

    public function edit(JalurPpdb $jalurPpdb): View
    {
        $this->authorize('jalur-ppdb.edit');

        $jalurPpdb->load(['formulirField', 'dokumenSyarat', 'seleksi.gelombangPpdb', 'seleksi.jenisTesMaster']);

        return view('admin.jalur-ppdb.edit', [
            'jalur' => $jalurPpdb,
            'gelombangList' => GelombangPpdb::where('tahun_ajaran_id', $jalurPpdb->tahun_ajaran_id)->orderBy('nama')->get(),
            'jenisTesList' => JenisTesMaster::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, JalurPpdb $jalurPpdb): RedirectResponse
    {
        $this->authorize('jalur-ppdb.edit');

        $data = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('jalur_ppdb', 'nama')
                    ->where(fn ($query) => $query->where('tahun_ajaran_id', $jalurPpdb->tahun_ajaran_id))
                    ->ignore($jalurPpdb->id),
            ],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'status_aktif' => ['required', 'boolean'],
        ]);

        $jalurPpdb->update($data);

        return redirect()->route('admin.jalur-ppdb.edit', $jalurPpdb)->with('status', 'Jalur berhasil diperbarui.');
    }
}
