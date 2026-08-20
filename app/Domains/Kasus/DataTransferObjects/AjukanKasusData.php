<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class AjukanKasusData
{
    public function __construct(
        public int $siswaId,
        public string $kategoriMasalah,
        public string $deskripsi,
        public ?string $lampiranPath,
    ) {}
}
