<?php

namespace App\Http\Controllers\Admin;

use App\Models\KalenderAkademik;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KalenderAkademikController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kalender-akademik.view');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');

        return view('admin.kalender-akademik.index', [
            'entriList' => KalenderAkademik::where(function ($query) use ($lembagaId) {
                $query->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId);
            })->orderBy('tanggal')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('kalender-akademik.kelola');

        return view('admin.kalender-akademik.create', [
            'bolehNasional' => $this->authorizeNasional(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
            'berlaku_nasional' => ['nullable', 'boolean'],
        ]);

        $nasional = $request->boolean('berlaku_nasional');

        if ($nasional) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        if (! $nasional && $request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat entri kalender.'])->withInput();
        }

        $lembagaId = $nasional ? null : ($request->user()->lembaga_id ?? session('active_lembaga_id'));

        $duplikat = KalenderAkademik::where('tanggal', $data['tanggal'])
            ->where(fn ($q) => $lembagaId === null ? $q->whereNull('lembaga_id') : $q->where('lembaga_id', $lembagaId))
            ->exists();

        if ($duplikat) {
            return back()->withErrors(['tanggal' => 'Sudah ada entri kalender untuk tanggal dan cakupan ini.'])->withInput();
        }

        KalenderAkademik::create([
            'lembaga_id' => $lembagaId,
            'tanggal' => $data['tanggal'],
            'nama' => $data['nama'],
            'tipe' => $data['tipe'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        return redirect()->route('admin.kalender-akademik.index')->with('status', 'Entri kalender berhasil disimpan.');
    }

    public function edit(Request $request, KalenderAkademik $kalenderAkademik): View
    {
        $this->authorize('kalender-akademik.kelola');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');

        if ($kalenderAkademik->lembaga_id !== null && $kalenderAkademik->lembaga_id !== $lembagaId) {
            abort(404);
        }

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        return view('admin.kalender-akademik.edit', ['entri' => $kalenderAkademik]);
    }

    public function update(Request $request, KalenderAkademik $kalenderAkademik): RedirectResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');

        if ($kalenderAkademik->lembaga_id !== null && $kalenderAkademik->lembaga_id !== $lembagaId) {
            abort(404);
        }

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $kalenderAkademik->update($data);

        return redirect()->route('admin.kalender-akademik.index')->with('status', 'Entri kalender berhasil diperbarui.');
    }

    private function authorizeNasional(): bool
    {
        return auth()->user()?->can('kalender-akademik.kelola-nasional') ?? false;
    }
}
