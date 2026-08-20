<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class HariAktifLembagaData
{
    /**
     * @param  array<int, int>  $hariAktif  hari 0 (minggu) - 6 (sabtu) yang aktif
     */
    public function __construct(
        public array $hariAktif,
    ) {}
}
