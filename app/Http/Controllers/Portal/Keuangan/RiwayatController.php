<?php
// app/Http/Controllers/Portal/Keuangan/RiwayatController.php

namespace App\Http\Controllers\Portal\Keuangan;

use App\Domains\Keuangan\Concerns\AuthorizesPembayaran;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiwayatController extends Controller
{
    use AuthorizesPembayaran;

    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('portals.portal.keuangan.tanpa-anak');
        }

        $validated = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'metode' => ['nullable', 'string'],
        ]);

        $dari = $validated['dari'] ?? null;
        $sampai = $validated['sampai'] ?? null;
        $metode = $validated['metode'] ?? null;

        $dateRangeValid = ! ($dari && $sampai && $dari > $sampai);

        $pembayarans = Pembayaran::where('siswa_id', $activeSiswa->id)
            ->where(fn ($q) => $q->where('channel_reference', '!=', 'WALLET_PERMANENT')->orWhereNull('channel_reference'))
            ->when($dateRangeValid && $dari, fn ($q) => $q->where('created_at', '>=', $dari.' 00:00:00'))
            ->when($dateRangeValid && $sampai, fn ($q) => $q->where('created_at', '<=', $sampai.' 23:59:59'))
            ->when($metode, fn ($q) => $q->where('metode', $metode))
            ->with(['pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends($request->query());

        $statsQuery = fn () => Pembayaran::where('siswa_id', $activeSiswa->id)
            ->where(fn ($q) => $q->where('channel_reference', '!=', 'WALLET_PERMANENT')->orWhereNull('channel_reference'))
            ->when($dateRangeValid && $dari, fn ($q) => $q->where('created_at', '>=', $dari.' 00:00:00'))
            ->when($dateRangeValid && $sampai, fn ($q) => $q->where('created_at', '<=', $sampai.' 23:59:59'))
            ->when($metode, fn ($q) => $q->where('metode', $metode));

        $totalLunasNominal = $statsQuery()->where('status', 'lunas')->sum('amount');
        $totalMenungguCount = $statsQuery()->whereIn('status', ['menunggu_pembayaran', 'menunggu_verifikasi'])->count();
        $totalTransaksiCount = $statsQuery()->count();

        return view('portals.portal.keuangan.riwayat.index', [
            'activeSiswa' => $activeSiswa,
            'pembayarans' => $pembayarans,
            'dari' => $dari,
            'sampai' => $sampai,
            'metode' => $metode,
            'filterActive' => $metode || ($dateRangeValid && ($dari || $sampai)),
            'totalLunasNominal' => $totalLunasNominal,
            'totalMenungguCount' => $totalMenungguCount,
            'totalTransaksiCount' => $totalTransaksiCount,
        ]);
    }

    public function kwitansi(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        abort_unless($pembayaran->status === 'lunas', 404);

        $pembayaran->load([
            'pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
            'siswa' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
            'siswa.lembaga.yayasan',
            'siswa.kelas' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
        ]);

        $pdf = Pdf::loadView('pdf.kwitansi', [
            'pembayaran' => $pembayaran,
            'siswa' => $pembayaran->siswa,
            'lembaga' => $pembayaran->siswa->lembaga,
            'yayasan' => $pembayaran->siswa->lembaga->yayasan,
        ]);

        return $pdf->stream('kwitansi-'.$pembayaran->id.'.pdf');
    }
}