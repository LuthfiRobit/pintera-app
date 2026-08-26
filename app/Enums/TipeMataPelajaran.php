<?php

namespace App\Enums;

enum TipeMataPelajaran: string
{
    case Mapel = 'mapel';

    public function label(): string
    {
        return match ($this) {
            self::Mapel => 'Mata Pelajaran',
        };
    }
}
