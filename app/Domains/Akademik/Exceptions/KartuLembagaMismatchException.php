<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Exceptions;

class KartuLembagaMismatchException extends KartuValidasiException
{
    public function __construct()
    {
        parent::__construct('Siswa ini tidak terdaftar di lembaga Anda.');
    }
}
