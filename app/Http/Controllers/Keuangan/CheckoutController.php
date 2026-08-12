<?php
// app/Http/Controllers/Keuangan/CheckoutController.php

namespace App\Http\Controllers\Keuangan;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\PaymentException;
use App\Models\Pembayaran;
use App\Models\Scopes\TenantScope;
use App\Models\Tagihan;
use App\Services\Finance\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckoutController extends BaseController
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function create(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        $tagihanIds = (array) $request->query('tagihan_ids', []);

        $tagihans = Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('id', $tagihanIds)
            ->with(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->get();

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        return view('keuangan.checkout.create', [
            'activeSiswa' => $activeSiswa,
            'tagihans' => $tagihans,
            'totalTagihan' => $totalTagihan,
            'wallet' => $activeSiswa->wallet,
        ]);
    }

    public function va(Request $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, (array) $request->input('tagihan_ids', []));

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        $existing = $this->findPendingVaFor($tagihans);
        if ($existing !== null) {
            return redirect()->route('keuangan.checkout.show', $existing);
        }

        try {
            $pembayaran = $this->paymentService->createVaPayment($activeSiswa, $tagihans);
        } catch (PaymentException $e) {
            Log::error('Gagal membuat VA BRI: '.$e->getMessage());
            return back()->withErrors(['tagihan_ids' => 'Gagal membuat pembayaran, silakan coba lagi.']);
        }

        return redirect()->route('keuangan.checkout.show', $pembayaran);
    }

    public function qris(Request $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, (array) $request->input('tagihan_ids', []));

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        try {
            $pembayaran = $this->paymentService->createQrisPayment($activeSiswa, $tagihans);
        } catch (PaymentException $e) {
            Log::error('Gagal membuat QRIS: '.$e->getMessage());
            return back()->withErrors(['tagihan_ids' => 'Gagal membuat pembayaran, silakan coba lagi.']);
        }

        return redirect()->route('keuangan.checkout.show', $pembayaran);
    }

    public function wallet(Request $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, (array) $request->input('tagihan_ids', []));

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        try {
            $pembayaran = $this->paymentService->createWalletPayment($activeSiswa, $tagihans);
        } catch (InsufficientBalanceException|PaymentException $e) {
            return back()->withErrors(['tagihan_ids' => 'Saldo wallet tidak mencukupi untuk tagihan terpilih.']);
        }

        return redirect()->route('keuangan.checkout.sukses', $pembayaran);
    }

    public function sukses(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($request, $pembayaran);

        $pembayaran->load(['pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

        return view('keuangan.checkout.sukses', ['pembayaran' => $pembayaran]);
    }

    public function show(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($request, $pembayaran);

        return view('keuangan.checkout.show', ['pembayaran' => $pembayaran->load(['briVirtualAccount', 'briQrisPayment'])]);
    }

    public function status(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($request, $pembayaran);

        return response()->json(['status' => $pembayaran->status]);
    }

    private function resolveSelectedTagihan($activeSiswa, array $tagihanIds)
    {
        return Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('id', $tagihanIds)
            ->get();
    }

    private function findPendingVaFor($tagihans): ?Pembayaran
    {
        $requestedIds = $tagihans->pluck('id')->sort()->values()->all();

        $candidates = Pembayaran::where('metode', 'va_bri')
            ->where('status', 'menunggu_pembayaran')
            ->whereHas('pembayaranTagihan', fn ($q) => $q->whereIn('tagihan_id', $requestedIds))
            ->whereHas('briVirtualAccount', fn ($q) => $q->where('expired_at', '>', now()))
            ->with('pembayaranTagihan')
            ->get();

        return $candidates->first(function (Pembayaran $candidate) use ($requestedIds) {
            $candidateIds = $candidate->pembayaranTagihan->pluck('tagihan_id')->sort()->values()->all();

            return $candidateIds === $requestedIds;
        });
    }

    private function authorizePembayaran(Request $request, Pembayaran $pembayaran): void
    {
        $orangTua = Auth::user()->orangTua;
        $ownsChild = $orangTua !== null
            && $orangTua->siswa()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->whereKey($pembayaran->siswa_id)->exists();

        abort_unless($ownsChild, 403);
    }
}
