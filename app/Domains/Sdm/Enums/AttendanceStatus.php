<?php

namespace App\Domains\Sdm\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpa => 'Alpa',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Hadir => 'green',
            self::Izin => 'blue',
            self::Sakit => 'amber',
            self::Alpa => 'red',
        };
    }
}
