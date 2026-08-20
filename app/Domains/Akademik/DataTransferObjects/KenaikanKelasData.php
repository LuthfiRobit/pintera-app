<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KenaikanKelasData
{
    /**
     * @param  array<int, array{tindakan: string, kelas_baru_id: ?int, salin_jadwal: ?bool, semester_tujuan_id: ?int}>  $mapping  keyed by kelas lama id
     */
    public function __construct(
        public array $mapping,
    ) {}
}
