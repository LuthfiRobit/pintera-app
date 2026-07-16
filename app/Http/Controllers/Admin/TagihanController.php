<?php
// app/Http/Controllers/Admin/TagihanController.php

namespace App\Http\Controllers\Admin;

use App\Models\Cicilan;
use App\Models\Pendaftaran;
use App\Models\SkemaCicilan;
use App\Models\Tagihan;
use App\Services\PembayaranService;
use App\Services\TagihanGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class TagihanController extends BaseController
{
    use AuthorizesRequests;

    /**
     * Same duplicated-per-controller pattern as PendaftaranAdminController and
     * SkPpdbController: Tagihan has no lembaga_id of its own (derived
     * transitively via pendaftaran_id), so every action here must resolve and
     * apply the acting user's effective lembaga scope manually.
     */
    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    public function buatSusulan(Request $request, Pendaftaran $pendaftaran, TagihanGenerator $generator): RedirectResponse
    {
        $this->authorize('tagihan.buat-susulan');
        abort_unless($pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'kategori' => ['required', 'in:pendaftaran,daftar_ulang'],
        ]);

        $tagihan = $generator->generate($pendaftaran, $data['kategori']);

        if (! $tagihan) {
            return back()->withErrors([
                'kategori' => 'Tagihan sudah ada, atau belum ada nominal yang dikonfigurasi untuk jalur ini.',
            ]);
        }

        return back()->with('status', 'Tagihan susulan berhasil dibuat.');
    }

    public function index(Request $request): View
    {
        $this->authorize('tagihan.view');

        return view('admin.tagihan.index', [
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
                    ->orWhereHas('calonMurid', fn ($cm) => $cm->where('nama_lengkap', 'like', '%'.$search.'%'));
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

    public function buatSkemaCicilan(Request $request, Tagihan $tagihan, PembayaranService $service): RedirectResponse
    {
        $this->authorize('cicilan.kelola');
        abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'jumlah_termin' => ['required', 'integer', 'min:2', 'max:'.($tagihan->maksCicilan() ?? 2)],
        ]);

        try {
            $service->buatSkemaCicilan($tagihan, $data['jumlah_termin'], 'admin', $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['jumlah_termin' => $exception->getMessage()]);
        }

        return back()->with('status', 'Skema cicilan berhasil dibuat.');
    }

    public function simpanNominalCicilan(Request $request, SkemaCicilan $skemaCicilan, PembayaranService $service): RedirectResponse
    {
        $this->authorize('cicilan.kelola');
        abort_unless($skemaCicilan->tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $service->simpanNominalManual($skemaCicilan, array_map('intval', $data['nominal']));
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['nominal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Nominal cicilan berhasil diperbarui.');
    }

    public function catatManualTagihan(Request $request, Tagihan $tagihan, PembayaranService $service): RedirectResponse
    {
        $this->authorize('pembayaran.catat-manual');
        abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        try {
            $service->catatPembayaran($tagihan, null, 'admin', null, $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['pembayaran' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pembayaran berhasil dicatat.');
    }

    public function catatManualCicilan(Request $request, Cicilan $cicilan, PembayaranService $service): RedirectResponse
    {
        $this->authorize('pembayaran.catat-manual');
        abort_unless($cicilan->skemaCicilan->tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        try {
            $service->catatPembayaran(null, $cicilan, 'admin', null, $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['pembayaran' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pembayaran termin berhasil dicatat.');
    }
}
