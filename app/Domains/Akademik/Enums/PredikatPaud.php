<?php

namespace App\Domains\Akademik\Enums;

enum PredikatPaud: string
{
    case BB = 'BB';
    case MB = 'MB';
    case BSH = 'BSH';
    case BSB = 'BSB';

    public function label(): string
    {
        return match ($this) {
            self::BB => 'Belum Berkembang',
            self::MB => 'Mulai Berkembang',
            self::BSH => 'Berkembang Sesuai Harapan',
            self::BSB => 'Berkembang Sangat Baik',
        };
    }
}
