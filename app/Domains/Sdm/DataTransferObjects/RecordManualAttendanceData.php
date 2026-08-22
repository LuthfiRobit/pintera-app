<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

use App\Domains\Sdm\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;

final readonly class RecordManualAttendanceData
{
    public function __construct(
        public int $lembagaId,
        public string $arah,
        public AttendanceStatus $status,
        public CarbonImmutable $waktu,
        public int $dicatatOlehUserId,
        public ?int $attendancePointId = null,
        public ?string $catatan = null,
    ) {}
}
