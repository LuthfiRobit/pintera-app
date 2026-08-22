<?php

namespace App\Domains\Sdm\Exceptions;

use RuntimeException;

class ShiftAssignmentOverlapException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Pegawai ini sudah punya penugasan shift lain yang tumpang tindih dengan rentang tanggal ini.');
    }
}
