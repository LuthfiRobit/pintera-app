<?php

namespace App\Enums;

enum TipeKalenderAkademik: string
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
