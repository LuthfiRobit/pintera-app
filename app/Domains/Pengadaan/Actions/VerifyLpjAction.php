<?php

namespace App\Domains\Pengadaan\Actions;

use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\LpjPengadaan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyLpjAction
{
    public function execute(LpjPengadaan $lpj, int $verifierUserId, bool $isApproved, ?string $notes = null): void
    {
        if ($lpj->status_lpj !== StatusLpj::Submitted) {
            throw ValidationException::withMessages([
                'lpj' => 'Hanya LPJ dengan status Submitted yang dapat diverifikasi.',
            ]);
        }

        DB::transaction(function () use ($lpj, $verifierUserId, $isApproved, $notes) {
            $lpj->catatan_verifikasi = $notes;
            $lpj->verified_by_user_id = $verifierUserId;
            $lpj->verified_at = now();

            if ($isApproved) {
                $lpj->status_lpj = StatusLpj::Verified;
                $proposal = $lpj->proposal;
                if ($proposal) {
                    $proposal->status = StatusPengajuan::Completed;
                    $proposal->save();
                }
            } else {
                $lpj->status_lpj = StatusLpj::RevisionRequired;
            }

            $lpj->save();
        });
    }
}
