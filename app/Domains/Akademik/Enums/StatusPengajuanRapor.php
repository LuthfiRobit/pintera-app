<?php

namespace App\Domains\Akademik\Enums;

enum StatusPengajuanRapor: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Diverifikasi = 'diverifikasi';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Diajukan',
            self::Diverifikasi => 'Diverifikasi',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }
}
