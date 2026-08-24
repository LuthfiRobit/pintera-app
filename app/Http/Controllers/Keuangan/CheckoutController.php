<?php
// app/Http/Controllers/Keuangan/CheckoutController.php

namespace App\Http\Controllers\Keuangan;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\PaymentException;
use App\Domains\Keuangan\Concerns\AuthorizesPembayaran;
use App\Http\Requests\Keuangan\StoreManualTransferRequest;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Models\Scopes\TenantScope;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckoutController extends BaseController
{
    use AuthorizesPembayaran;

    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function create(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

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
        $requestedIds = (array) $request->input('tagihan_ids', []);

        return redirect()->route('keuangan.checkout.va-info', ['tagihan_ids' => $requestedIds]);
    }

    public function vaInfo(Request $request): View|RedirectResponse
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return redirect()->route('keuangan.tagihan.index');
        }

        $requestedIds = (array) $request->query('tagihan_ids', []);
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, $requestedIds);

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        try {
            $va = $this->paymentService->getOrCreatePermanentVa($activeSiswa);
        } catch (PaymentException $e) {
            Log::error('Gagal membuat VA BRI Permanen: '.$e->getMessage());
            return back()->withErrors(['tagihan_ids' => 'Gagal mendapatkan VA, silakan coba lagi.']);
        }

        return view('keuangan.checkout.va-info', [
            'va' => $va,
            'totalTagihan' => $totalTagihan,
            'tagihans' => $tagihans,
        ]);
    }

    public function qris(Request $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $requestedIds = (array) $request->input('tagihan_ids', []);
        $topupAmount = (float) $request->input('topup_amount', 0);
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, $requestedIds);

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        if ($tagihans->count() !== count(array_unique($requestedIds))) {
            return redirect()->route('keuangan.tagihan.index')
                ->withErrors(['tagihan_ids' => 'Sebagian tagihan yang dipilih sudah lunas, silakan cek kembali.']);
        }

        if ($topupAmount <= 0) {
            $existing = $this->findPendingPembayaranFor('qris', $tagihans);
            if ($existing !== null) {
                return redirect()->route('keuangan.checkout.show', $existing);
            }
        }

        try {
            if ($topupAmount > 0) {
                $pembayaran = $this->paymentService->createQrisPaymentWithTopup($activeSiswa, $tagihans, $topupAmount);
            } else {
                $pembayaran = $this->paymentService->createQrisPayment($activeSiswa, $tagihans);
            }
        } catch (PaymentException $e) {
            Log::error('Gagal membuat QRIS: '.$e->getMessage());
            return back()->withErrors(['tagihan_ids' => 'Gagal membuat pembayaran, silakan coba lagi.']);
        }

        return redirect()->route('keuangan.checkout.show', $pembayaran);
    }

    public function wallet(Request $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $requestedIds = (array) $request->input('tagihan_ids', []);
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, $requestedIds);

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        if ($tagihans->count() !== count(array_unique($requestedIds))) {
            return redirect()->route('keuangan.tagihan.index')
                ->withErrors(['tagihan_ids' => 'Sebagian tagihan yang dipilih sudah lunas, silakan cek kembali.']);
        }

        try {
            $pembayaran = $this->paymentService->createWalletPayment($activeSiswa, $tagihans);
        } catch (InsufficientBalanceException|PaymentException $e) {
            return back()->withErrors(['tagihan_ids' => 'Saldo wallet tidak mencukupi untuk tagihan terpilih.']);
        }

        return redirect()->route('keuangan.checkout.sukses', $pembayaran);
    }

    public function transfer(StoreManualTransferRequest $request)
    {
        $activeSiswa = $request->attributes->get('activeSiswa');
        $requestedIds = (array) $request->input('tagihan_ids', []);
        $tagihans = $this->resolveSelectedTagihan($activeSiswa, $requestedIds);

        if ($tagihans->isEmpty()) {
            return back()->withErrors(['tagihan_ids' => 'Tidak ada tagihan valid yang dipilih.']);
        }

        if ($tagihans->count() !== count(array_unique($requestedIds))) {
            return redirect()->route('keuangan.tagihan.index')
                ->withErrors(['tagihan_ids' => 'Sebagian tagihan yang dipilih sudah lunas, silakan cek kembali.']);
        }

        $totalTagihan = $tagihans->reduce(
            fn (float $carry, Tagihan $tagihan) => $carry + ($tagihan->net_amount - $tagihan->paid_amount),
            0.0
        );

        try {
            $path = $request->file('transfer_proof')->store('bukti-transfer', 'public');

            $pembayaran = $this->paymentService->createManualPayment($activeSiswa, $tagihans, [
                'amount' => $totalTagihan,
                'transfer_proof_path' => $path,
                'bank_origin' => $request->input('bank_origin'),
                'transfer_date' => $request->input('transfer_date'),
                'requested_by' => Auth::id(),
            ]);
        } catch (PaymentException $e) {
            return back()->withErrors(['tagihan_ids' => 'Gagal mengirim bukti transfer, silakan coba lagi.']);
        }

        return redirect()->route('keuangan.checkout.menunggu-verifikasi', $pembayaran);
    }

    public function menungguVerifikasi(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        return view('keuangan.checkout.menunggu-verifikasi', ['pembayaran' => $pembayaran->load('manualRequest')]);
    }

    public function sukses(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        $pembayaran->load(['pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

        return view('keuangan.checkout.sukses', ['pembayaran' => $pembayaran]);
    }

    public function show(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        abort_unless($pembayaran->metode === 'qris', 404);

        $pembayaran->load(['briQrisPayment', 'pembayaranTagihan']);

        $qrCodeDataUri = null;
        if ($pembayaran->briQrisPayment) {
            $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(180)->generate($pembayaran->briQrisPayment->qr_code);
            $qrCodeDataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);
        }

        return view('keuangan.checkout.show', [
            'pembayaran' => $pembayaran,
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);
    }

    public function status(Request $request, Pembayaran $pembayaran)
    {
        $this->authorizePembayaran($pembayaran);

        return response()->json(['status' => $pembayaran->status]);
    }

    private function resolveSelectedTagihan($activeSiswa, array $tagihanIds)
    {
        if ($activeSiswa === null) {
            return collect();
        }

        return Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('id', $tagihanIds)
            ->get();
    }

    private function findPendingPembayaranFor(string $metode, $tagihans): ?Pembayaran
    {
        $relation = $metode === 'qris' ? 'briQrisPayment' : 'briVirtualAccount';
        $requestedIds = $tagihans->pluck('id')->sort()->values()->all();

        $candidates = Pembayaran::where('metode', $metode)
            ->where('status', 'menunggu_pembayaran')
            ->where('topup_status', 'none')
            ->whereHas('pembayaranTagihan', fn ($q) => $q->whereIn('tagihan_id', $requestedIds))
            ->whereHas($relation, fn ($q) => $q->where('expired_at', '>', now()))
            ->with('pembayaranTagihan')
            ->get();

        return $candidates->first(function (Pembayaran $candidate) use ($requestedIds) {
            $candidateIds = $candidate->pembayaranTagihan->pluck('tagihan_id')->sort()->values()->all();

            return $candidateIds === $requestedIds;
        });
    }
}
