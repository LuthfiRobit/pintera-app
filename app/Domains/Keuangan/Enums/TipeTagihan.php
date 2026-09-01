<?php

namespace App\Domains\Keuangan\Enums;

enum TipeTagihan: string
{
    case Harian = 'harian';
    case Mingguan = 'mingguan';
    case Bulanan = 'bulanan';
    case Tahunan = 'tahunan';
    case Sekali = 'sekali';

    public function label(): string
    {
        return match ($this) {
            self::Harian => 'Harian',
            self::Mingguan => 'Mingguan',
            self::Bulanan => 'Bulanan',
            self::Tahunan => 'Tahunan',
            self::Sekali => 'Sekali',
        };
    }
}
