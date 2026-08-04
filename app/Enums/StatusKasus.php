<?php

namespace App\Enums;

enum StatusKasus: string
{
    case Diajukan = 'diajukan';
    case MenungguConsent = 'menunggu_consent';
    case Ditugaskan = 'ditugaskan';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::MenungguConsent => 'Menunggu Consent',
            self::Ditugaskan => 'Ditugaskan',
        };
    }
}
