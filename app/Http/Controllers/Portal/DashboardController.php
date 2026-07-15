<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $pendaftaranList = Auth::guard('portal')->user()
            ->pendaftaran()
            ->with(['calonMurid', 'lembaga', 'jalurPpdb', 'gelombangPpdb'])
            ->latest('submitted_at')
            ->get();

        return view('portal.dashboard', ['pendaftaranList' => $pendaftaranList]);
    }
}
