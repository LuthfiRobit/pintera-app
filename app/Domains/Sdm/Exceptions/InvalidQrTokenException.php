<?php

namespace App\Domains\Sdm\Exceptions;

use RuntimeException;

class InvalidQrTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('QR tidak valid atau sudah tidak aktif.');
    }
}
