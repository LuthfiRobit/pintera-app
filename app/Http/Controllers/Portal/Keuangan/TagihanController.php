<?php

namespace App\Http\Controllers\Portal\Keuangan;

use App\Domains\Keuangan\Models\Tagihan;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagihanController extends Controller
{
    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('keuangan.tanpa-anak');
        }

        $tagihans = Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->with(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->orderBy('jatuh_tempo')
            ->get();

        $autoDebitEnabled = (bool) SystemSetting::getResolved('auto_debit_enabled', $activeSiswa->lembaga_id, false);

        return view('portals.portal.keuangan.tagihan.index', [
            'activeSiswa' => $activeSiswa,
            'tagihans' => $tagihans,
            'autoDebitEnabled' => $autoDebitEnabled,
        ]);
    }
}