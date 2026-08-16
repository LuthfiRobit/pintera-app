<?php

namespace App\Domains\Sarpras\Enums;

enum KondisiAset: string
{
    case Baik = 'baik';
    case RusakRingan = 'rusak_ringan';
    case RusakBerat = 'rusak_berat';
    case Hilang = 'hilang';

    public function label(): string
    {
        return match ($this) {
            self::Baik => 'Baik / Layak Pakai',
            self::RusakRingan => 'Rusak Ringan (Bisa Diperbaiki)',
            self::RusakBerat => 'Rusak Berat (Tidak Layak)',
            self::Hilang => 'Hilang / Dihapuskan',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Baik => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::RusakRingan => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::RusakBerat => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            self::Hilang => 'bg-gray-50 text-gray-700 ring-gray-600/20',
        };
    }
}
