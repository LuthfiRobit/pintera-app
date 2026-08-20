<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class JadwalkanSesiData
{
    /**
     * @param  array<int, array{dijadwalkan_pada: string, peserta: string, lokasi_mode: string}>  $sesi
     */
    public function __construct(
        public array $sesi,
    ) {}
}
