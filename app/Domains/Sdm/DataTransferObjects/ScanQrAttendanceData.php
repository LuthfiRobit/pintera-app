<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

final readonly class ScanQrAttendanceData
{
    public function __construct(
        public string $token,
        public string $arah,
        public int $lembagaId,
        public int $dicatatOlehUserId,
        public ?int $attendancePointId = null,
    ) {}
}
