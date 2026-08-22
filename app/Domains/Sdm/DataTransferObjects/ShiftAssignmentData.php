<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

final readonly class ShiftAssignmentData
{
    public function __construct(
        public int $lembagaId,
        public int $jenisShiftId,
        public string $tanggalMulai,
        public ?string $tanggalSelesai = null,
        public ?array $hariKerja = null,
    ) {}
}
