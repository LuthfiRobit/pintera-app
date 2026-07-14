<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\Pendaftaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class CekStatusController extends BaseController
{
    use ResolvesSpmbTenant;

    public function create(string $lembagaSlug): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);

        return view('spmb.cek-status', ['lembaga' => $lembaga]);
    }

    public function show(Request $request, string $lembagaSlug): View|RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);

        $data = $request->validate([
            'kode_pendaftaran' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('kode_pendaftaran', $data['kode_pendaftaran'])
            ->where('email_pendaftaran', $data['email'])
            ->first();

        if (! $pendaftaran) {
            return back()->withErrors(['kode_pendaftaran' => 'Kode pendaftaran dan email tidak ditemukan atau tidak cocok.']);
        }

        return view('spmb.status-hasil', ['lembaga' => $lembaga, 'pendaftaran' => $pendaftaran]);
    }

    public function unduhBukti(Request $request, string $lembagaSlug, string $kodePendaftaran): Response
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);

        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('kode_pendaftaran', $kodePendaftaran)
            ->where('email_pendaftaran', $request->query('email'))
            ->firstOrFail();

        $pdf = Pdf::loadView('pdf.bukti-pendaftaran', ['lembaga' => $lembaga, 'pendaftaran' => $pendaftaran]);

        return $pdf->stream('bukti-pendaftaran-'.$pendaftaran->kode_pendaftaran.'.pdf');
    }
}
