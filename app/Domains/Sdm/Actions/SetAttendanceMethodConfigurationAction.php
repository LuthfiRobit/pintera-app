<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Models\AttendanceMethodConfiguration;

final class SetAttendanceMethodConfigurationAction
{
    public function execute(int $yayasanId, ?int $lembagaId, AttendanceMethod $method, bool $isEnabled): AttendanceMethodConfiguration
    {
        return AttendanceMethodConfiguration::updateOrCreate(
            ['yayasan_id' => $yayasanId, 'lembaga_id' => $lembagaId, 'method' => $method],
            ['is_enabled' => $isEnabled]
        );
    }
}
