<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PortalController extends BaseController
{
    use ResolvesSpmbTenant;

    public function index(Request $request, string $lembagaSlug): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $gelombang = $this->cariGelombangAktif($lembaga);

        if (! $gelombang) {
            return view('spmb.tertutup', ['lembaga' => $lembaga]);
        }

        $dibatasi = $gelombang->jalur()->exists();

        $jalurList = JalurPpdb::where('tahun_ajaran_id', $gelombang->tahun_ajaran_id)
            ->where('status_aktif', true)
            ->when($dibatasi, fn ($q) => $q->whereHas('gelombang', fn ($q2) => $q2->whereKey($gelombang->id)))
            ->orderBy('nama')
            ->get();

        return view('spmb.pilih-jalur', ['lembaga' => $lembaga, 'jalurList' => $jalurList, 'gelombang' => $gelombang]);
    }

    public static function cariGelombangAktif(Lembaga $lembaga): ?GelombangPpdb
    {
        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

        if (! $tahunAjaranAktif) {
            return null;
        }

        return GelombangPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)
            ->where('tanggal_buka', '<=', now())
            ->where('tanggal_tutup', '>=', now())
            ->orderBy('tanggal_buka')
            ->first();
    }
}
