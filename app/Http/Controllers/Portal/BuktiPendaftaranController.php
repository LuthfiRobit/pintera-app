<?php

namespace App\Http\Controllers\Portal;

use App\Models\Pendaftaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class BuktiPendaftaranController extends BaseController
{
    public function unduh(Pendaftaran $pendaftaran): Response
    {
        abort_unless($pendaftaran->akun_pendaftar_id === Auth::guard('portal')->id(), 404);

        $pdf = Pdf::loadView('pdf.bukti-pendaftaran', [
            'lembaga' => $pendaftaran->lembaga,
            'pendaftaran' => $pendaftaran,
        ]);

        return $pdf->stream('bukti-pendaftaran-'.$pendaftaran->kode_pendaftaran.'.pdf');
    }
}
