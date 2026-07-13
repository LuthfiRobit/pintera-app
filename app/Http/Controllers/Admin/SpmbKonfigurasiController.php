<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\TahunAjaran;
use App\Services\SpmbKonfigurasiDuplikasi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class SpmbKonfigurasiController extends BaseController
{
    use AuthorizesRequests;

    public function duplikasi(Request $request, SpmbKonfigurasiDuplikasi $duplikasi): RedirectResponse
    {
        $this->authorize('spmb-konfigurasi.duplikasi');

        $tujuan = TahunAjaran::where('status_aktif', true)->firstOrFail();

        $data = $request->validate([
            'tahun_ajaran_sumber_id' => [
                'required',
                Rule::exists('tahun_ajaran', 'id')->where(fn ($query) => $query->whereIn('id', TahunAjaran::pluck('id'))),
                function ($attribute, $value, $fail) use ($tujuan) {
                    if (GelombangPpdb::where('tahun_ajaran_id', $tujuan->id)->exists() || JalurPpdb::where('tahun_ajaran_id', $tujuan->id)->exists()) {
                        $fail('Tahun ajaran ini sudah punya konfigurasi SPMB, tidak bisa disalin ulang.');
                    }
                },
            ],
        ]);

        $sumber = TahunAjaran::findOrFail($data['tahun_ajaran_sumber_id']);

        $jumlah = $duplikasi->duplikasi($sumber, $tujuan);

        activity('spmb_konfigurasi')
            ->causedBy($request->user())
            ->withProperties([
                'dari_tahun_ajaran' => $sumber->nama,
                'ke_tahun_ajaran' => $tujuan->nama,
                'jumlah' => $jumlah,
            ])
            ->log('Konfigurasi SPMB disalin dari '.$sumber->nama.' ke '.$tujuan->nama);

        return redirect()->route('admin.jalur-ppdb.index')
            ->with('status', 'Konfigurasi berhasil disalin dari '.$sumber->nama.'.');
    }
}
