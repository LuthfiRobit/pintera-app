<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Consent;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Notifications\ConsentDisetujuiNotification;
use Illuminate\Support\Facades\DB;

final class ApproveConsentAction
{
    public function execute(Kasus $kasus, KasusConsent $kasusConsent): void
    {
        DB::transaction(function () use ($kasus, $kasusConsent) {
            $kasusConsent->update(['status' => 'disetujui', 'disetujui_at' => now()]);

            if ($kasusConsent->jenis === 'sesi_pendampingan') {
                $kasus->update(['status' => StatusKasus::Ditugaskan]);
            }
        });

        if ($kasusConsent->jenis === 'sesi_pendampingan') {
            $this->notifyKasusDitugaskan($kasus);
        }
    }

    private function notifyKasusDitugaskan(Kasus $kasus): void
    {
        $guruPengaju = $kasus->diajukanOlehGuru()->withoutGlobalScope(TenantScope::class)->first();
        $guruPengaju?->notify(new ConsentDisetujuiNotification($kasus));

        // Avoid Spatie's ->role() query scope here: it throws RoleDoesNotExist when the
        // 'operator_akademik' role hasn't been created yet in the current guard (e.g. in tests
        // that don't need lembaga-admin notifications). whereHas() degrades to zero matches
        // instead, which is the correct behavior for a best-effort notification fan-out.
        $lembagaAdmins = User::withoutGlobalScope(TenantScope::class)
            ->whereHas('roles', fn ($query) => $query->where('name', 'operator_akademik'))
            ->where('lembaga_id', $kasus->lembaga_id)
            ->get();

        foreach ($lembagaAdmins as $admin) {
            $admin->notify(new ConsentDisetujuiNotification($kasus));
        }
    }
}
