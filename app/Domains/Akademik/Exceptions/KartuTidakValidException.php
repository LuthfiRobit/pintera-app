<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Exceptions;

class KartuTidakValidException extends KartuValidasiException
{
    public function __construct()
    {
        parent::__construct('Kode kartu tidak valid atau sudah tidak aktif.');
    }
}
