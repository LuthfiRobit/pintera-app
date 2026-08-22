<?php

namespace App\Domains\Sdm\Exceptions;

use RuntimeException;

class AttendanceOnHolidayException extends RuntimeException
{
    public function __construct(string $alasan)
    {
        parent::__construct("Tanggal ini libur: {$alasan}");
    }
}
