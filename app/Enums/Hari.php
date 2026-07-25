<?php

namespace App\Enums;

enum Hari: string
{
    case Senin = 'senin';
    case Selasa = 'selasa';
    case Rabu = 'rabu';
    case Kamis = 'kamis';
    case Jumat = 'jumat';
    case Sabtu = 'sabtu';
    case Minggu = 'minggu';

    public function label(): string
    {
        return match ($this) {
            self::Senin => 'Senin',
            self::Selasa => 'Selasa',
            self::Rabu => 'Rabu',
            self::Kamis => 'Kamis',
            self::Jumat => 'Jumat',
            self::Sabtu => 'Sabtu',
            self::Minggu => 'Minggu',
        };
    }

    public static function aktifDari(array $hariLiburMingguan): array
    {
        $petaKeAngka = [
            self::Senin->value => 1,
            self::Selasa->value => 2,
            self::Rabu->value => 3,
            self::Kamis->value => 4,
            self::Jumat->value => 5,
            self::Sabtu->value => 6,
            self::Minggu->value => 0,
        ];

        return array_values(array_filter(
            self::cases(),
            fn (self $hari) => ! in_array($petaKeAngka[$hari->value], $hariLiburMingguan, true)
        ));
    }
}
