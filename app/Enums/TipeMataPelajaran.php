<?php

namespace App\Enums;

enum TipeMataPelajaran: string
{
    case Mapel = 'mapel';
    case AspekPerkembangan = 'aspek_perkembangan';

    public function label(): string
    {
        return match ($this) {
            self::Mapel => 'Mata Pelajaran',
            self::AspekPerkembangan => 'Aspek Perkembangan',
        };
    }
}
