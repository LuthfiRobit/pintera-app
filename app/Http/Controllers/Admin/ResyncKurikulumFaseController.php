<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Kelas\ResyncKurikulumFaseKelasAction;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
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
    use ResolveLembagaScopeTrait;

    public function __construct(private readonly ResyncKurikulumFaseKelasAction $action) {}

    public function index(Request $request): View
    {
        $this->authorize('kurikulum-assignment.view');

        $actor = $request->user();
        $scope = $actor->widestScopeLevel();
        $activeLembagaId = $this->resolveActiveLembagaId($actor);
        $activeLembaga = $activeLembagaId !== null ? Lembaga::find($activeLembagaId) : null;

        if ($activeLembaga !== null) {
            $lembagaId = $activeLembaga->id;
        } else {
            $lembagaId = $request->query('lembaga_id') !== null ? (int) $request->query('lembaga_id') : null;
        }

        $tahunAjaranId = $request->query('tahun_ajaran_id') !== null ? (int) $request->query('tahun_ajaran_id') : null;

        $diff = [];
        if ($lembagaId !== null && $tahunAjaranId !== null) {
            $this->authorizeScope($request, $lembagaId);
            $diff = $this->action->hitungDiff($lembagaId, $tahunAjaranId);
        }

        $lembagaList = match ($scope) {
            'platform' => Lembaga::orderBy('nama')->get(),
            'yayasan' => Lembaga::where('yayasan_id', $actor->yayasan_id)->orderBy('nama')->get(),
            default => collect($actor->lembaga ? [$actor->lembaga] : []),
        };

        $tahunAjaranList = $lembagaId !== null
            ? TahunAjaran::where('lembaga_id', $lembagaId)->orderByDesc('tanggal_mulai')->get()
            : collect();

        return view('admin.kurikulum-assignment.resync', [
            'lembagaList' => $lembagaList,
            'tahunAjaranList' => $tahunAjaranList,
            'lembagaId' => $lembagaId,
            'tahunAjaranId' => $tahunAjaranId,
            'diff' => $diff,
            'activeLembaga' => $activeLembaga,
            'isPlatformOrYayasan' => in_array($scope, ['platform', 'yayasan'], true),
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
        $actor = $request->user();
        $scope = $actor->widestScopeLevel();

        if ($scope === 'platform') {
            return;
        }

        $activeLembagaId = $this->resolveActiveLembagaId($actor);
        if ($activeLembagaId !== null) {
            abort_unless($lembagaId === $activeLembagaId, 403);

            return;
        }

        if ($scope === 'yayasan') {
            $milikYayasan = Lembaga::where('id', $lembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
            abort_unless($milikYayasan, 403);

            return;
        }

        abort_unless($lembagaId === $actor->lembaga_id, 403);
    }
}
