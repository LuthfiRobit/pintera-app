<?php

namespace App\DataTransferObjects;

use App\Models\Guru;
use App\Models\Karyawan;

final class KonselorKandidat
{
    public function __construct(
        public readonly string $tipe,
        public readonly Guru|Karyawan $model,
    ) {}
}
