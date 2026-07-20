<?php

namespace App\Http\Controllers\Spmb;

use App\Models\Lembaga;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class WelcomeController extends BaseController
{
    public function index(): View
    {
        $lembagaList = Lembaga::with('yayasan')->orderBy('nama')->get()->map(function (Lembaga $lembaga) {
            $gelombang = PortalController::cariGelombangAktif($lembaga);

            $tahunAjaranAktif = \App\Models\TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            $jalurAktifCount = $tahunAjaranAktif
                ? \App\Models\JalurPpdb::where('lembaga_id', $lembaga->id)
                    ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                    ->where('status_aktif', true)
                    ->count()
                : 0;

            $biayaTermurah = null;
            if ($tahunAjaranAktif) {
                $jenisPendaftaran = \App\Models\JenisTagihan::where('lembaga_id', $lembaga->id)
                    ->where('kategori', 'pendaftaran')->first();

                if ($jenisPendaftaran) {
                    $biayaTermurah = \App\Models\NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)
                        ->whereHas('jalurPpdb', fn ($q) => $q->where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaranAktif->id))
                        ->min('nominal');
                }
            }

            return [
                'lembaga' => $lembaga,
                'gelombang' => $gelombang,
                'jalurAktifCount' => $jalurAktifCount,
                'biayaTermurah' => $biayaTermurah,
            ];
        });

        $jumlahSedangBuka = $lembagaList->filter(fn ($item) => $item['gelombang'] !== null)->count();
        $jumlahJalurAktif = $lembagaList->sum('jalurAktifCount');
        $jenjangList = $lembagaList->pluck('lembaga.bentuk_pendidikan')->filter()->unique()->sort()->values();

        $gelombangTerdekat = \App\Models\GelombangPpdb::where('tanggal_buka', '<=', now())
            ->where('tanggal_tutup', '>=', now())
            ->with('lembaga')
            ->orderBy('tanggal_tutup')
            ->first();

        $yayasan = Lembaga::with('yayasan')->first()?->yayasan;

        return view('spmb.welcome', [
            'lembagaList' => $lembagaList,
            'jumlahLembaga' => $lembagaList->count(),
            'jumlahSedangBuka' => $jumlahSedangBuka,
            'jumlahJalurAktif' => $jumlahJalurAktif,
            'jenjangList' => $jenjangList,
            'gelombangTerdekat' => $gelombangTerdekat,
            'yayasan' => $yayasan,
        ]);
    }
}
