<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class TahunAjaranController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    public function index(Request $request): View
    {
        $this->authorize('tahun-ajaran.view');

        return view('admin.tahun-ajaran.index', [
            'tahunAjaranList' => TahunAjaran::with(['semester', 'lembaga'])->get(),
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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('tahun-ajaran.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:20'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ]);

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = $this->resolveActiveLembagaId($request->user());

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat tahun ajaran.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }

        TahunAjaran::create($data);

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Tahun ajaran berhasil dibuat.');
    }

    public function update(Request $request, TahunAjaran $tahunAjaran): RedirectResponse
    {
        $this->authorize('tahun-ajaran.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:20'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ]);

        $tahunAjaran->update($data);

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Tahun ajaran berhasil diperbarui.');
    }

    public function activate(TahunAjaran $tahunAjaran): RedirectResponse
    {
        $this->authorize('tahun-ajaran.activate');

        $tahunAjaran->activate();

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Tahun ajaran berhasil diaktifkan.');
    }
}
