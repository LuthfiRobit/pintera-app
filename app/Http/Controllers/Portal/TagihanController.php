<?php
// app/Http/Controllers/Portal/TagihanController.php

namespace App\Http\Controllers\Portal;

use App\Models\Cicilan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Services\PembayaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TagihanController extends BaseController
{
    public function index(): View
    {
        $pendaftaranList = Auth::guard('portal')->user()
            ->pendaftaran()
            ->with(['calonMurid', 'tagihan.skemaCicilan', 'tagihan.cicilan.pembayaran', 'tagihan.pembayaran'])
            ->latest('submitted_at')
            ->get();

        $pendaftaranList->each(function ($pendaftaran) {
            $pendaftaran->tagihan->each(function ($tagihan) {
                // Tie-break on id: the pembayaran table's created_at column has
                // only second precision, so two attempts recorded within the
                // same second would otherwise sort ambiguously. id always
                // increases with insertion order, so it's a reliable secondary key.
                $riwayat = $tagihan->pembayaran
                    ->concat($tagihan->cicilan->flatMap->pembayaran)
                    ->sortBy([['created_at', 'desc'], ['id', 'desc']])
                    ->values();
                $tagihan->setRelation('riwayatPembayaran', $riwayat);
            });
        });

        return view('portal.tagihan.index', ['pendaftaranList' => $pendaftaranList]);
    }

    public function buatSkemaCicilan(Request $request, Tagihan $tagihan, PembayaranService $service): RedirectResponse
    {
        $this->pastikanMilikSendiri($tagihan);

        $data = $request->validate([
            'jumlah_termin' => ['required', 'integer', 'min:2', 'max:'.(app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->maksCicilan($tagihan) ?? 2)],
        ]);

        try {
            $service->buatSkemaCicilan($tagihan, $data['jumlah_termin'], 'calon_siswa');
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['jumlah_termin' => $exception->getMessage()]);
        }

        return redirect()->route('portal.tagihan.index')->with('status', 'Skema cicilan berhasil dibuat.');
    }

    public function bayarLunas(Request $request, Tagihan $tagihan, PembayaranService $service): RedirectResponse
    {
        $this->pastikanMilikSendiri($tagihan);

        $request->validate(['bukti' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048']]);
        $path = $request->file('bukti')->store('bukti-transfer/'.$tagihan->pendaftaran_id, 'public');

        try {
            $service->catatPembayaran($tagihan, null, 'calon_siswa', $path, null);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['bukti' => $exception->getMessage()]);
        }

        return redirect()->route('portal.tagihan.index')->with('status', 'Bukti transfer berhasil dikirim, menunggu verifikasi admin.');
    }

    public function bayarCicilan(Request $request, Cicilan $cicilan, PembayaranService $service): RedirectResponse
    {
        $tagihan = $cicilan->skemaCicilan->tagihan;
        $this->pastikanMilikSendiri($tagihan);

        $request->validate(['bukti' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048']]);
        $path = $request->file('bukti')->store('bukti-transfer/'.$tagihan->pendaftaran_id, 'public');

        try {
            $service->catatPembayaran(null, $cicilan, 'calon_siswa', $path, null);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['bukti' => $exception->getMessage()]);
        }

        return redirect()->route('portal.tagihan.index')->with('status', 'Bukti transfer berhasil dikirim, menunggu verifikasi admin.');
    }

    private function pastikanMilikSendiri(Tagihan $tagihan): void
    {
        abort_unless($tagihan->pendaftaran->akun_pendaftar_id === Auth::guard('portal')->id(), 404);
    }
}
