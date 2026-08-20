<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Manajemen;

use App\Domains\Kasus\Models\Kasus;
use Illuminate\Support\Facades\DB;

final class DestroyKasusAction
{
    public function execute(Kasus $kasus): void
    {
        DB::transaction(function () use ($kasus) {
            foreach ($kasus->tugas as $tugas) {
                $tugas->submissions()->delete();
            }
            $kasus->sesi()->delete();
            $kasus->tugas()->delete();
            $kasus->evaluasi()->delete();
            $kasus->consents()->delete();
            $kasus->delete();
        });
    }
}
