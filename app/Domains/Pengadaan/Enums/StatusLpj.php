<?php

namespace App\Domains\Pengadaan\Enums;

enum StatusLpj: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case RevisionRequired = 'revision_required';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft LPJ',
            self::Submitted => 'Menunggu Verifikasi',
            self::RevisionRequired => 'Perlu Perbaikan Nota',
            self::Verified => 'Diverifikasi (Selesai)',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Submitted => 'purple',
            self::RevisionRequired => 'amber',
            self::Verified => 'green',
        };
    }
}
