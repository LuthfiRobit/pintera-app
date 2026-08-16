<?php

namespace App\Domains\Pengadaan\Enums;

enum TingkatUrgensi: string
{
    case Biasa = 'biasa';
    case Mendesak = 'mendesak';
    case Kritis = 'kritis';

    public function label(): string
    {
        return match ($this) {
            self::Biasa => 'Biasa / Rutin',
            self::Mendesak => 'Mendesak',
            self::Kritis => 'Kritis / Darurat',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Biasa => 'slate',
            self::Mendesak => 'amber',
            self::Kritis => 'rose',
        };
    }
}
