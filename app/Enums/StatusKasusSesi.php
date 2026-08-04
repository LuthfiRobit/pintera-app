<?php
// app/Enums/StatusKasusSesi.php

namespace App\Enums;

enum StatusKasusSesi: string
{
    case Terjadwal = 'terjadwal';
    case Selesai = 'selesai';
    case Batal = 'batal';
    case TidakHadir = 'tidak_hadir';

    public function label(): string
    {
        return match ($this) {
            self::Terjadwal => 'Terjadwal',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
            self::TidakHadir => 'Tidak Hadir',
        };
    }
}
