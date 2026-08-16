<?php

namespace App\Http\Controllers\Lembaga\Sarpras;

use App\Domains\Sarpras\Models\Ruangan;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class KirController extends Controller
{
    public function show(Ruangan $ruangan): View
    {
        $this->authorize('sarpras.ruangan.view');

        $ruangan->load(['gedung', 'penanggungJawab', 'aset.kategori']);

        return view('portals.lembaga.sarpras.kir.show', compact('ruangan'));
    }

    public function exportPdf(Ruangan $ruangan): Response
    {
        $this->authorize('sarpras.kir.export');

        $ruangan->load(['gedung', 'penanggungJawab', 'aset.kategori', 'lembaga.yayasan']);

        $pdf = Pdf::loadView('pdf.kartu-inventaris-ruangan', compact('ruangan'))
            ->setPaper('a4', 'landscape');

        $filename = 'KIR_' . str_replace(' ', '_', $ruangan->nama_ruangan) . '_' . now()->format('Ymd') . '.pdf';

        return $pdf->stream($filename);
    }
}
