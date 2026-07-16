<?php

namespace App\Http\Controllers\Admin;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JenisTagihanController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('jenis-tagihan.view');

        return view('admin.jenis-tagihan.index', [
            'jenisTagihanList' => JenisTagihan::orderBy('nama')->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('jenis-tagihan.create');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.']);
        }

        return view('admin.jenis-tagihan.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jenis-tagihan.create');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.'])->withInput();
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'in:pendaftaran,daftar_ulang,lainnya'],
            'bisa_dicicil' => ['nullable', 'boolean'],
            'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
        ]);
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');

        $jenisTagihan = JenisTagihan::create($data);

        return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)
            ->with('status', 'Jenis tagihan berhasil ditambahkan. Atur nominal per jalur di bawah.');
    }

    public function edit(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.edit');

        return view('admin.jenis-tagihan.edit', ['jenisTagihan' => $jenisTagihan]);
    }

    public function update(Request $request, JenisTagihan $jenisTagihan): RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'in:pendaftaran,daftar_ulang,lainnya'],
            'bisa_dicicil' => ['nullable', 'boolean'],
            'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
        ]);
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');

        $jenisTagihan->update($data);

        return redirect()->route('admin.jenis-tagihan.edit', $jenisTagihan)->with('status', 'Jenis tagihan berhasil diperbarui.');
    }

    public function destroy(JenisTagihan $jenisTagihan): RedirectResponse
    {
        $this->authorize('jenis-tagihan.delete');

        if ($jenisTagihan->nominalJalur()->exists()) {
            return back()->withErrors(['jenis_tagihan' => 'Tidak bisa menghapus jenis tagihan yang sudah punya nominal terkonfigurasi.']);
        }

        $jenisTagihan->delete();

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil dihapus.');
    }

    public function nominal(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.edit');

        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $jenisTagihan->lembaga_id)->where('status_aktif', true)->first();

        return view('admin.jenis-tagihan.nominal', [
            'jenisTagihan' => $jenisTagihan,
            'jalurList' => $tahunAjaranAktif
                ? JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
                : collect(),
            'nominalMap' => NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->pluck('nominal', 'jalur_ppdb_id'),
            'tahunAjaranAktif' => $tahunAjaranAktif,
        ]);
    }

    public function simpanNominal(Request $request, JenisTagihan $jenisTagihan): RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $jalurIds = JalurPpdb::where('lembaga_id', $jenisTagihan->lembaga_id)->pluck('id');

        foreach ($data['nominal'] as $jalurPpdbId => $nominal) {
            if (! $jalurIds->contains((int) $jalurPpdbId) || $nominal === null || $nominal === '') {
                continue;
            }

            NominalTagihanJalur::updateOrCreate(
                ['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalurPpdbId],
                ['nominal' => $nominal]
            );
        }

        return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)->with('status', 'Nominal berhasil disimpan.');
    }
}
