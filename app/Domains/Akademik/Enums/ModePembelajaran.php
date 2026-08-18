<?php

namespace App\Domains\Akademik\Enums;

enum ModePembelajaran
{
    case SesiMapel;
    case Tematik;

    public static function fromBentukPendidikan(string $bentukPendidikan): self
    {
        return match ($bentukPendidikan) {
            'SMP', 'SMA', 'SMK' => self::SesiMapel,
            default => self::Tematik,
        };
    }
}
