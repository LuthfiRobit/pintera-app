<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Kelas\ResyncKurikulumFaseKelasAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class ResyncKurikulumFaseController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(private readonly ResyncKurikulumFaseKelasAction $action) {}

    public function index(Request $request): View
    {
        $this->authorize('kurikulum-assignment.view');

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
        $lembagaId = $request->query('lembaga_id') !== null ? (int) $request->query('lembaga_id') : ($isPlatformOrYayasan ? null : $request->user()->lembaga_id);
        $tahunAjaranId = $request->query('tahun_ajaran_id') !== null ? (int) $request->query('tahun_ajaran_id') : null;

        $diff = [];
        if ($lembagaId !== null && $tahunAjaranId !== null) {
            $this->authorizeScope($request, $lembagaId);
            $diff = $this->action->hitungDiff($lembagaId, $tahunAjaranId);
        }

        return view('admin.kurikulum-assignment.resync', [
            'lembagaList' => $isPlatformOrYayasan ? Lembaga::orderBy('nama')->get() : collect([$request->user()->lembaga]),
            'tahunAjaranList' => $lembagaId !== null ? TahunAjaran::where('lembaga_id', $lembagaId)->orderByDesc('tanggal_mulai')->get() : collect(),
            'lembagaId' => $lembagaId,
            'tahunAjaranId' => $tahunAjaranId,
            'diff' => $diff,
            'isPlatformOrYayasan' => $isPlatformOrYayasan,
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.edit');

        $validated = $request->validate([
            'lembaga_id' => ['required', 'integer', 'exists:lembaga,id'],
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajaran,id'],
            'kelas_ids' => ['required', 'array', 'min:1'],
            'kelas_ids.*' => ['integer'],
        ]);

        $this->authorizeScope($request, (int) $validated['lembaga_id']);

        $kelasMilikLembaga = Kelas::where('lembaga_id', $validated['lembaga_id'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->whereIn('id', $validated['kelas_ids'])
            ->pluck('id');

        abort_unless($kelasMilikLembaga->count() === count($validated['kelas_ids']), 403);

        $this->action->terapkan($kelasMilikLembaga->all());

        return redirect()
            ->route('admin.kurikulum-assignment.resync', ['lembaga_id' => $validated['lembaga_id'], 'tahun_ajaran_id' => $validated['tahun_ajaran_id']])
            ->with('status', 'Kurikulum/fase kelas terpilih berhasil disinkronkan.');
    }

    private function isPlatformOrYayasan(Request $request): bool
    {
        return in_array($request->user()->widestScopeLevel(), ['platform', 'yayasan'], true);
    }

    private function authorizeScope(Request $request, int $lembagaId): void
    {
        abort_unless($this->isPlatformOrYayasan($request) || $lembagaId === $request->user()->lembaga_id, 403);
    }
}
