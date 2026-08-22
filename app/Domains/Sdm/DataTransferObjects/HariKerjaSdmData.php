<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

final readonly class HariKerjaSdmData
{
    /**
     * @param  array<int, int>  $hariKerja  hari 0 (minggu) - 6 (sabtu) yang menjadi hari kerja SDM
     */
    public function __construct(
        public array $hariKerja,
    ) {}
}
