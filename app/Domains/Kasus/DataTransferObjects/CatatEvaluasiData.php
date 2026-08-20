<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class CatatEvaluasiData
{
    public function __construct(
        public string $catatan,
        public string $keputusan,
        public int $dibuatOlehUserId,
    ) {}
}
