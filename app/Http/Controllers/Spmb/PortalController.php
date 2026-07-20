<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class PortalController extends BaseController
{
    use ResolvesSpmbTenant;

    public function index(Request $request, string $lembagaSlug): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        $gelombang = static::cariGelombangAktif($lembaga);

        if (! $tahunAjaranAktif) {
            $jalurList = collect();
        } else {
            $jalurQuery = JalurPpdb::where('status_aktif', true)->where('tahun_ajaran_id', $tahunAjaranAktif->id);

            if ($gelombang && $gelombang->jalur()->exists()) {
                $jalurQuery->whereHas('gelombang', fn ($q) => $q->whereKey($gelombang->id));
            }

            $jalurList = $jalurQuery->orderBy('id')->get()->map(function (JalurPpdb $jalur) use ($lembaga, $gelombang) {
                $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'pendaftaran')->first();
                $nominal = $jenisPendaftaran
                    ? NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)->where('jalur_ppdb_id', $jalur->id)->first()
                    : null;

                $tesList = $gelombang
                    ? SeleksiPpdb::where('jalur_ppdb_id', $jalur->id)->where('gelombang_ppdb_id', $gelombang->id)->with('jenisTesMaster')->get()
                    : collect();

                return [
                    'jalur' => $jalur,
                    'featured' => $jalur->nama === 'Reguler',
                    'nominal' => $nominal,
                    'tesList' => $tesList,
                    'kuota' => $gelombang?->kuota,
                ];
            });
        }

        return view('spmb.pilih-jalur', [
            'lembaga' => $lembaga,
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'gelombang' => $gelombang,
            'jalurList' => $jalurList,
        ]);
    }

    public function daftarJalur(Request $request, string $lembagaSlug, JalurPpdb $jalur): RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->resolveGelombangAktifUntukJalur($lembaga, $jalur);

        $request->session()->put('spmb_pilihan.lembaga_id', $lembaga->id);
        $request->session()->put('spmb_pilihan.jalur_id', $jalur->id);

        if (Route::has('spmb.register')) {
            return redirect()->route('spmb.register');
        }

        return redirect()
            ->route('spmb.index', ['lembagaSlug' => $lembaga->slug])
            ->with('status', 'Fitur pendaftaran akan segera hadir.');
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
