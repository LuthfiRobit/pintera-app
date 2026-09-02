<?php

namespace App\Http\Controllers\Portal\Keuangan;

use App\Domains\Keuangan\Concerns\AuthorizesTagihanAccess;
use App\Domains\Keuangan\Models\Tagihan;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagihanController extends Controller
{
    use AuthorizesTagihanAccess;

    public function index(Request $request): View
    {
        $activeSiswa = $request->attributes->get('activeSiswa');

        if ($activeSiswa === null) {
            return view('portals.portal.keuangan.tanpa-anak');
        }

        $tagihans = Tagihan::where('tagihable_type', get_class($activeSiswa))
            ->where('tagihable_id', $activeSiswa->id)
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->with(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->orderBy('jatuh_tempo')
            ->get();

        $autoDebitEnabled = (bool) SystemSetting::getResolved('auto_debit_enabled', $activeSiswa->lembaga_id, true);

        return view('portals.portal.keuangan.tagihan.index', [
            'activeSiswa' => $activeSiswa,
            'tagihans' => $tagihans,
            'autoDebitEnabled' => $autoDebitEnabled,
        ]);
    }

    public function show(Tagihan $tagihan): View
    {
        $this->authorizeTagihanAccess($tagihan);

        $tagihan->load(['jenisTagihan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

        return view('portals.portal.keuangan.tagihan.show', ['tagihan' => $tagihan]);
    }
}
