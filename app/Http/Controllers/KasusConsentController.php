<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Consent\ApproveConsentAction;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;

class KasusConsentController extends BaseController
{
    use AuthorizesRequests;

    public function approve(Kasus $kasus, KasusConsent $kasusConsent, ApproveConsentAction $action): RedirectResponse
    {
        $this->authorize('kasus.consent');

        $orangTua = auth()->user()->orangTua;
        abort_if($orangTua === null, 403);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        $isKontakUtama = $siswa->orangTua()
            ->where('orang_tua_id', $orangTua->id)
            ->wherePivot('is_kontak_utama', true)
            ->exists();

        abort_if(! $isKontakUtama, 403, 'Anda bukan kontak utama untuk siswa ini.');
        abort_if($kasusConsent->kasus_id !== $kasus->id, 404);

        $action->execute($kasus, $kasusConsent);

        return redirect()->route('kasus.show', $kasus)->with('status', 'Persetujuan berhasil disimpan.');
    }
}
