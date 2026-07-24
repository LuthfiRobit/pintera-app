<?php

namespace App\Enums;

enum SumberDataSiswa: string
{
    case Spmb = 'spmb';
    case Import = 'import';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Spmb => 'SPMB',
            self::Import => 'Import',
            self::Manual => 'Input Manual',
        };
    }
}
