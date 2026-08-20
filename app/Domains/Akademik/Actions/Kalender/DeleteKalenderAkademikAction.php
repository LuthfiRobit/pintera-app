<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\Models\KalenderAkademik;

final class DeleteKalenderAkademikAction
{
    public function execute(KalenderAkademik $kalenderAkademik): void
    {
        $kalenderAkademik->delete();
    }
}
