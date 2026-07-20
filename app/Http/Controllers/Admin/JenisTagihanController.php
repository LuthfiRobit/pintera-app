<?php

namespace App\Http\Controllers\Admin;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JenisTagihanController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('jenis-tagihan.view');

        return view('admin.jenis-tagihan.index', [
            'jenisTagihanList' => JenisTagihan::withCount(['nominalJalur', 'tagihanItem'])->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.create');

        $isYayasanScope = $request->user()->widestScopeLevel() === 'yayasan';
        if ($isYayasanScope) {
            $lembagaId = session('active_lembaga_id');
            if ($lembagaId === null) {
                $message = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.';

                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'errors' => ['lembaga_id' => [$message]]], 422);
                }

                return back()->withErrors(['lembaga_id' => $message])->withInput();
            }
        } else {
            $lembagaId = $request->user()->lembaga_id;
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tagihan', 'nama')
                ->where(fn ($query) => $query->where('lembaga_id', $lembagaId))],
            'kategori' => ['required', 'in:pendaftaran,daftar_ulang,lainnya'],
            'bisa_dicicil' => ['nullable', 'boolean'],
            'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
        ]);
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');
        if ($isYayasanScope) {
            $data['lembaga_id'] = $lembagaId;
        }

        $jenisTagihan = JenisTagihan::create($data);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $jenisTagihan->fresh(),
                'redirect' => $jenisTagihan->kategori !== 'lainnya'
                    ? route('admin.jenis-tagihan.nominal', $jenisTagihan)
                    : null,
            ], 201);
        }

        if ($jenisTagihan->kategori === 'lainnya') {
            return redirect()->route('admin.jenis-tagihan.index')
                ->with('status', 'Jenis tagihan berhasil ditambahkan. Kategori "Lainnya" belum punya mekanisme penentuan nominal — itu akan dibangun bersama modul yang memakainya nanti (misalnya SPP).');
        }

        return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)
            ->with('status', 'Jenis tagihan berhasil ditambahkan. Atur nominal per jalur di bawah.');
    }

    public function update(Request $request, JenisTagihan $jenisTagihan): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tagihan', 'nama')
                ->where(fn ($query) => $query->where('lembaga_id', $jenisTagihan->lembaga_id))
                ->ignore($jenisTagihan->id)],
            'kategori' => ['required', 'in:pendaftaran,daftar_ulang,lainnya'],
            'bisa_dicicil' => ['nullable', 'boolean'],
            'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
        ]);
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');

        $jenisTagihan->update($data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $jenisTagihan->fresh()]);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisTagihan $jenisTagihan): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.delete');

        $jumlahTagihan = $jenisTagihan->tagihanItem()->count();
        if ($jumlahTagihan > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah dipakai di {$jumlahTagihan} tagihan milik calon murid."
            );
        }

        $jumlahNominal = $jenisTagihan->nominalJalur()->count();
        if ($jumlahNominal > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah ada {$jumlahNominal} nominal jalur yang dikonfigurasi. Hapus dulu di halaman Kelola Nominal."
            );
        }

        $jenisTagihan->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis tagihan berhasil dihapus.']);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['jenis_tagihan' => $message]);
    }

    public function nominal(JenisTagihan $jenisTagihan): View|RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if ($jenisTagihan->kategori === 'lainnya') {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang. Kategori "Lainnya" belum punya mekanisme penentuan nominal.']);
        }

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

        if ($jenisTagihan->kategori === 'lainnya') {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang.']);
        }

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
