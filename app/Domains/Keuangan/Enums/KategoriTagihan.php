<?php

namespace App\Domains\Keuangan\Enums;

enum KategoriTagihan: string
{
    case Pendaftaran = 'pendaftaran';
    case DaftarUlang = 'daftar_ulang';
    case Spp = 'spp';
    case Tahunan = 'tahunan';
    case Kegiatan = 'kegiatan';
    case Custom = 'custom';
    case Lainnya = 'lainnya';

    public function isPpdb(): bool
    {
        return in_array($this, [self::Pendaftaran, self::DaftarUlang], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendaftaran => 'Pendaftaran',
            self::DaftarUlang => 'Daftar Ulang',
            self::Spp => 'SPP',
            self::Tahunan => 'Tahunan',
            self::Kegiatan => 'Kegiatan',
            self::Custom => 'Custom',
            self::Lainnya => 'Lainnya',
        };
    }
}
