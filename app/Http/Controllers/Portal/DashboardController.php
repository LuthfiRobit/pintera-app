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

        if ($lembaga && $jalur && ! $this->sudahDidaftarkan($pendaftaranList, $lembaga, $jalur)) {
            return redirect()->route('portal.wizard.data-diri');
        }

        return view('portal.dashboard', ['pendaftaranList' => $pendaftaranList]);
    }

    private function sudahDidaftarkan($pendaftaranList, Lembaga $lembaga, JalurPpdb $jalur): bool
    {
        return $pendaftaranList->contains(
            fn (Pendaftaran $p) => $p->lembaga_id === $lembaga->id && $p->jalur_ppdb_id === $jalur->id
        );
    }
}
