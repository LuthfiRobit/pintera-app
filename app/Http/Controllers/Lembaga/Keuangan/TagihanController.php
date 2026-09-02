<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\Tagihan\BuatSkemaCicilanAction;
use App\Domains\Keuangan\Actions\Tagihan\CatatManualCicilanAction;
use App\Domains\Keuangan\Actions\Tagihan\CatatManualTagihanAction;
use App\Domains\Keuangan\Actions\Tagihan\SelesaikanTinjauanTagihanAction;
use App\Domains\Keuangan\Actions\Tagihan\SimpanNominalCicilanAction;
use App\Domains\Keuangan\Models\Cicilan;
use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\TagihanCicilanEligibilityService;
use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagihanController extends Controller
{
    use AuthorizesRequests;

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    // Skema cicilan hanya berlaku untuk tagihan PPDB (tagihable_type = Pendaftaran::class) --
    // TagihanCicilanEligibilityService sudah membuat ini mustahil dicapai lewat tagihan siswa
    // reguler (TagihanItem cuma pernah diisi oleh TagihanGenerator, yang cuma dipanggil untuk
    // Pendaftaran), tapi endpoint ini tidak boleh crash (null->lembaga_id) kalau tetap ada yang
    // mencoba akses paksa dengan id Tagihan siswa reguler -- 404 rapi, bukan 500.
    private function resolvePendaftaranLembagaId(Tagihan $tagihan): int
    {
        $lembagaId = $tagihan->pendaftaran?->lembaga_id;
        abort_if($lembagaId === null, 404);

        return $lembagaId;
    }

    public function index(Request $request): View
    {
        $this->authorize('tagihan.view');

        return view('portals.lembaga.keuangan.tagihan.index', [
            'lembagaBelumDipilih' => $this->lembagaId($request) === null,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('tagihan.view');

        $lembagaId = $this->lembagaId($request);

        if ($lembagaId === null) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 0, 'last_page' => 0, 'per_page' => 0, 'total' => 0],
            ]);
        }

        $query = Tagihan::whereHas('pendaftaran', fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->with(['pendaftaran.calonMurid']);

        if ($search = trim((string) $request->string('search'))) {
            $query->whereHas('pendaftaran', function ($q) use ($search) {
                $q->where('kode_pendaftaran', 'like', '%'.$search.'%')
                    ->orWhereHas('calonMurid', fn ($cm) => $cm->search($search));
            });
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($kategori = $request->string('kategori')->value()) {
            $query->where('kategori', $kategori);
        }

        $sortable = ['created_at', 'total_tagihan'];
        $sort = in_array($request->string('sort')->value(), $sortable, true) ? $request->string('sort')->value() : 'created_at';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Tagihan $tagihan) => [
                'id' => $tagihan->id,
                'nama_calon_murid' => $tagihan->pendaftaran->calonMurid->nama_lengkap,
                'kode_pendaftaran' => $tagihan->pendaftaran->kode_pendaftaran,
                'kategori' => $tagihan->kategori,
                'total_tagihan' => (float) $tagihan->total_tagihan,
                'status' => $tagihan->status,
                'pendaftaran_id' => $tagihan->pendaftaran_id,
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function buatSkemaCicilan(
        Request $request,
        Tagihan $tagihan,
        BuatSkemaCicilanAction $action,
        TagihanCicilanEligibilityService $eligibility,
    ): RedirectResponse {
        $this->authorize('cicilan.kelola');
        abort_unless($this->resolvePendaftaranLembagaId($tagihan) === $this->lembagaId($request), 404);

        $maks = $eligibility->maksCicilan($tagihan) ?? 2;

        $data = $request->validate([
            'jumlah_termin' => ['required', 'integer', 'min:2', "max:{$maks}"],
        ]);

        try {
            $action->execute($tagihan, $data['jumlah_termin'], 'admin', $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['jumlah_termin' => $exception->getMessage()]);
        }

        return back()->with('status', 'Skema cicilan berhasil dibuat.');
    }

    public function simpanNominalCicilan(
        Request $request,
        SkemaCicilan $skemaCicilan,
        SimpanNominalCicilanAction $action,
    ): RedirectResponse {
        $this->authorize('cicilan.kelola');
        abort_unless($this->resolvePendaftaranLembagaId($skemaCicilan->tagihan) === $this->lembagaId($request), 404);

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $action->execute($skemaCicilan, array_map('intval', $data['nominal']));
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['nominal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Nominal cicilan berhasil diperbarui.');
    }

    public function catatManualTagihan(
        Request $request,
        Tagihan $tagihan,
        CatatManualTagihanAction $action,
    ): RedirectResponse {
        $this->authorize('pembayaran.catat-manual');
        abort_unless($this->resolvePendaftaranLembagaId($tagihan) === $this->lembagaId($request), 404);

        try {
            $action->execute($tagihan, 'admin', $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['pembayaran' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pembayaran berhasil dicatat.');
    }

    public function catatManualCicilan(
        Request $request,
        Cicilan $cicilan,
        CatatManualCicilanAction $action,
    ): RedirectResponse {
        $this->authorize('pembayaran.catat-manual');
        abort_unless($this->resolvePendaftaranLembagaId($cicilan->skemaCicilan->tagihan) === $this->lembagaId($request), 404);

        try {
            $action->execute($cicilan, 'admin', $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['pembayaran' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pembayaran termin berhasil dicatat.');
    }

    public function tandaiSelesaiDitinjau(
        Request $request,
        Tagihan $tagihan,
        SelesaikanTinjauanTagihanAction $action,
    ): RedirectResponse {
        $this->authorize('tagihan.view');

        $action->execute($tagihan);

        return back()->with('status', 'Tagihan telah ditandai selesai ditinjau.');
    }

    public function perluDitinjau(Request $request): View
    {
        $this->authorize('tagihan.view');

        $lembagaId = $this->lembagaId($request);

        $tagihanList = Tagihan::with(['jenisTagihan', 'tagihable'])
            ->where('perlu_ditinjau_ulang', true)
            ->when($lembagaId, function ($query, $lembagaId) {
                $query->where(function ($q) use ($lembagaId) {
                    $q->whereHas('jenisTagihan', fn ($jt) => $jt->where('lembaga_id', $lembagaId))
                        ->orWhereHas('pendaftaran', fn ($p) => $p->where('lembaga_id', $lembagaId))
                        ->orWhereHasMorph('tagihable', [Siswa::class], fn ($s) => $s->withoutGlobalScopes()->where('lembaga_id', $lembagaId));
                });
            })
            ->latest('updated_at')
            ->paginate(20);

        return view('portals.lembaga.keuangan.tagihan.perlu-ditinjau', compact('tagihanList'));
    }
}
