<?php

namespace App\Domains\Pengadaan\Enums;

enum StatusItemPengajuan: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Review',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak / Dicoret',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::Approved => 'green',
            self::Rejected => 'rose',
        };
    }
}
