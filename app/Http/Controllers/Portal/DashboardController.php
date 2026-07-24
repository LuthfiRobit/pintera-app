<?php

namespace App\Http\Controllers\Portal;

use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View|RedirectResponse
    {
        $akun = Auth::guard('portal')->user();

        $pendaftaranList = $akun->pendaftaran()
            ->with(['calonMurid', 'lembaga', 'jalurPpdb', 'gelombangPpdb'])
            ->latest('submitted_at')
            ->get();

        $lembaga = session('spmb_pilihan.lembaga_id')
            ? Lembaga::find(session('spmb_pilihan.lembaga_id'))
            : null;
        $jalur = session('spmb_pilihan.jalur_id')
            ? JalurPpdb::find(session('spmb_pilihan.jalur_id'))
            : null;

        if ($lembaga && $jalur) {
            if ($this->sudahDidaftarkan($pendaftaranList, $lembaga, $jalur)) {
                session()->forget('spmb_pilihan');
                session()->flash('status', 'Kamu sudah terdaftar pada jalur ini. Lihat riwayat pendaftaranmu di bawah.');
            } elseif ($this->punyaPendaftaranMenungguKeputusan($pendaftaranList)) {
                session()->forget('spmb_pilihan');
                session()->flash('status', 'Kamu masih memiliki pendaftaran yang menunggu keputusan. Selesaikan itu dulu sebelum mendaftar jalur baru.');
            } else {
                return redirect()->route('portal.wizard.data-diri');
            }
        }

        return view('portal.dashboard', ['pendaftaranList' => $pendaftaranList]);
    }

    private function sudahDidaftarkan($pendaftaranList, Lembaga $lembaga, JalurPpdb $jalur): bool
    {
        return $pendaftaranList->contains(
            fn (Pendaftaran $p) => $p->lembaga_id === $lembaga->id && $p->jalur_ppdb_id === $jalur->id
        );
    }

    private function punyaPendaftaranMenungguKeputusan($pendaftaranList): bool
    {
        return $pendaftaranList->contains(fn (Pendaftaran $p) => $p->status === 'menunggu_verifikasi');
    }
}
