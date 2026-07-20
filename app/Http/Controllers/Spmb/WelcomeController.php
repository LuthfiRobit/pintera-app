<?php

namespace App\Http\Controllers\Spmb;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class WelcomeController extends BaseController
{
    public function index(): View
    {
        $lembagaList = Lembaga::with('yayasan')->orderBy('nama')->get();
        $lembagaIds = $lembagaList->pluck('id');

        $tahunAjaranAktifByLembaga = TahunAjaran::whereIn('lembaga_id', $lembagaIds)
            ->where('status_aktif', true)
            ->get()
            ->keyBy('lembaga_id');
        $tahunAjaranIds = $tahunAjaranAktifByLembaga->pluck('id');

        $jalurAktifCountByTahunAjaran = JalurPpdb::whereIn('tahun_ajaran_id', $tahunAjaranIds)
            ->where('status_aktif', true)
            ->selectRaw('tahun_ajaran_id, count(*) as jumlah')
            ->groupBy('tahun_ajaran_id')
            ->pluck('jumlah', 'tahun_ajaran_id');

        $jenisPendaftaranByLembaga = JenisTagihan::whereIn('lembaga_id', $lembagaIds)
            ->where('kategori', 'pendaftaran')
            ->get()
            ->keyBy('lembaga_id');
        $jenisTagihanIds = $jenisPendaftaranByLembaga->pluck('id');

        $biayaTermurahByJenisTagihan = NominalTagihanJalur::whereIn('jenis_tagihan_id', $jenisTagihanIds)
            ->whereHas('jalurPpdb', fn ($q) => $q->whereIn('tahun_ajaran_id', $tahunAjaranIds))
            ->selectRaw('jenis_tagihan_id, min(nominal) as termurah')
            ->groupBy('jenis_tagihan_id')
            ->pluck('termurah', 'jenis_tagihan_id');

        $lembagaList = $lembagaList->map(function (Lembaga $lembaga) use ($tahunAjaranAktifByLembaga, $jalurAktifCountByTahunAjaran, $jenisPendaftaranByLembaga, $biayaTermurahByJenisTagihan) {
            $gelombang = PortalController::cariGelombangAktif($lembaga);

            $tahunAjaranAktif = $tahunAjaranAktifByLembaga->get($lembaga->id);
            $jalurAktifCount = $tahunAjaranAktif
                ? (int) ($jalurAktifCountByTahunAjaran->get($tahunAjaranAktif->id) ?? 0)
                : 0;

            $biayaTermurah = null;
            if ($tahunAjaranAktif) {
                $jenisPendaftaran = $jenisPendaftaranByLembaga->get($lembaga->id);

                if ($jenisPendaftaran) {
                    $biayaTermurah = $biayaTermurahByJenisTagihan->get($jenisPendaftaran->id);
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
