<?php

namespace App\Http\Controllers;

use App\Enums\StatusKasus;
use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Notifications\ConsentDisetujuiNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class KasusConsentController extends BaseController
{
    use AuthorizesRequests;

    public function approve(Kasus $kasus, KasusConsent $kasusConsent): RedirectResponse
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

        DB::transaction(function () use ($kasus, $kasusConsent) {
            $kasusConsent->update(['status' => 'disetujui', 'disetujui_at' => now()]);

            if ($kasusConsent->jenis === 'sesi_pendampingan') {
                $kasus->update(['status' => StatusKasus::Ditugaskan]);
            }
        });

        if ($kasusConsent->jenis === 'sesi_pendampingan') {
            $this->notifyKasusDitugaskan($kasus);
        }

        return redirect()->route('kasus.show', $kasus)->with('status', 'Persetujuan berhasil disimpan.');
    }

    private function notifyKasusDitugaskan(Kasus $kasus): void
    {
        $guruPengaju = $kasus->diajukanOlehGuru()->withoutGlobalScope(TenantScope::class)->first();
        $guruPengaju?->notify(new ConsentDisetujuiNotification($kasus));

        // Avoid Spatie's ->role() query scope here: it throws RoleDoesNotExist when the
        // 'admin_akademik' role hasn't been created yet in the current guard (e.g. in tests
        // that don't need lembaga-admin notifications). whereHas() degrades to zero matches
        // instead, which is the correct behavior for a best-effort notification fan-out.
        $lembagaAdmins = User::withoutGlobalScope(TenantScope::class)
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin_akademik'))
            ->where('lembaga_id', $kasus->lembaga_id)
            ->get();

        foreach ($lembagaAdmins as $admin) {
            $admin->notify(new ConsentDisetujuiNotification($kasus));
        }
    }
}
