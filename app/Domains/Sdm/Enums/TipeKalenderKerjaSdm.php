<?php

namespace App\Domains\Sdm\Enums;

enum TipeKalenderKerjaSdm: string
{
    case Libur = 'libur';
    case Kerja = 'kerja';

    public function label(): string
    {
        return match ($this) {
            self::Libur => 'Libur',
            self::Kerja => 'Tetap Masuk (Override)',
        };
    }
}
