<?php

namespace App\Enums;

enum StatusSesiPembelajaran: string
{
    case Terlaksana = 'terlaksana';
    case Diganti = 'diganti';
    case Kosong = 'kosong';

    public function label(): string
    {
        return match ($this) {
            self::Terlaksana => 'Terlaksana',
            self::Diganti => 'Diganti',
            self::Kosong => 'Kosong',
        };
    }
}
