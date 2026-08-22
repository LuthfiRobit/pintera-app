<?php

namespace App\Domains\Sdm\Exceptions;

use RuntimeException;

class QrTokenLembagaMismatchException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('QR ini milik pegawai dari lembaga lain dan tidak dapat discan di sini.');
    }
}
