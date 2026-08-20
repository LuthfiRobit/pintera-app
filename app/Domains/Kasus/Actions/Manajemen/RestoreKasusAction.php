<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Manajemen;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use Illuminate\Support\Facades\DB;

final class RestoreKasusAction
{
    public function execute(Kasus $kasus): void
    {
        DB::transaction(function () use ($kasus) {
            $kasus->restore();
            $kasus->sesi()->withTrashed()->restore();
            KasusTugas::withTrashed()->where('kasus_id', $kasus->id)->get()->each(function (KasusTugas $tugas) {
                $tugas->submissions()->withTrashed()->restore();
                $tugas->restore();
            });
            $kasus->evaluasi()->withTrashed()->restore();
            $kasus->consents()->withTrashed()->restore();
        });
    }
}
