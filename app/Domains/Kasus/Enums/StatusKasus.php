<?php

namespace App\Domains\Kasus\Enums;

enum StatusKasus: string
{
    case Diajukan = 'diajukan';
    case MenungguConsent = 'menunggu_consent';
    case Ditugaskan = 'ditugaskan';
    case Berjalan = 'berjalan';
    case Eskalasi = 'eskalasi';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::MenungguConsent => 'Menunggu Consent',
            self::Ditugaskan => 'Ditugaskan',
            self::Berjalan => 'Berjalan',
            self::Eskalasi => 'Eskalasi',
            self::Selesai => 'Selesai',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Diajukan => 'amber',
            self::MenungguConsent, self::Ditugaskan => 'blue',
            self::Berjalan => 'green',
            self::Eskalasi => 'red',
            self::Selesai => 'slate',
        };
    }
}
