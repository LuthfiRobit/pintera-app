<?php
// app/Http/Controllers/Lembaga/Keuangan/PembayaranController.php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\Pembayaran\VerifikasiPembayaranAction;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembayaranController extends Controller
{
    use AuthorizesRequests;

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    private function labelJenis(Pembayaran $pembayaran): string
    {
        if ($pembayaran->cicilan_id) {
            $nominal = number_format($pembayaran->cicilan->nominal, 0, ',', '.');

            return "Cicilan Termin {$pembayaran->cicilan->urutan} — Rp {$nominal}";
        }

        $label = $pembayaran->tagihan->kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang';
        $nominal = number_format($pembayaran->tagihan->total_tagihan, 0, ',', '.');

        return "{$label} — Rp {$nominal}";
    }

    public function index(Request $request): View
    {
        $this->authorize('pembayaran.view');

        return view('portals.lembaga.keuangan.pembayaran.index', [
            'lembagaBelumDipilih' => $this->lembagaId($request) === null,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('pembayaran.view');

        $lembagaId = $this->lembagaId($request);

        if ($lembagaId === null) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 0, 'last_page' => 0, 'per_page' => 0, 'total' => 0],
            ]);
        }

        $query = Pembayaran::where('status', 'menunggu_verifikasi')
            ->where(function ($q) use ($lembagaId) {
                $q->whereHas('tagihan.pendaftaran', fn ($p) => $p->where('lembaga_id', $lembagaId))
                    ->orWhereHas('cicilan.skemaCicilan.tagihan.pendaftaran', fn ($p) => $p->where('lembaga_id', $lembagaId));
            })
            ->with(['tagihan.pendaftaran.calonMurid', 'cicilan.skemaCicilan.tagihan.pendaftaran.calonMurid'])
            ->latest('created_at');

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(function (Pembayaran $pembayaran) {
                $pendaftaran = $pembayaran->tagihan?->pendaftaran ?? $pembayaran->cicilan->skemaCicilan->tagihan->pendaftaran;

                return [
                    'id' => $pembayaran->id,
                    'nama_calon_murid' => $pendaftaran->calonMurid->nama_lengkap,
                    'kode_pendaftaran' => $pendaftaran->kode_pendaftaran,
                    'jenis' => $this->labelJenis($pembayaran),
                    'sumber' => $pembayaran->sumber,
                    'pendaftaran_id' => $pendaftaran->id,
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function verifikasi(Request $request, Pembayaran $pembayaran, VerifikasiPembayaranAction $action): RedirectResponse
    {
        $this->authorize('pembayaran.verifikasi');

        $pendaftaranLembagaId = $pembayaran->tagihan?->pendaftaran->lembaga_id
            ?? $pembayaran->cicilan->skemaCicilan->tagihan->pendaftaran->lembaga_id;
        abort_unless($pendaftaranLembagaId === $this->lembagaId($request), 404);

        $data = $request->validate([
            'keputusan' => ['required', 'in:lunas,ditolak'],
            'catatan_verifikasi' => ['required_if:keputusan,ditolak', 'nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($pembayaran, $data['keputusan'], $data['catatan_verifikasi'] ?? null, $request->user()->id);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['keputusan' => $exception->getMessage()]);
        }

        return redirect()->route('admin.pembayaran.index')->with('status', 'Pembayaran berhasil diverifikasi.');
    }
}