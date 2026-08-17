<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Enums;

enum StatusRpp: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case PerluRevisi = 'perlu_revisi';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Menunggu Verifikasi',
            self::Disetujui => 'Disetujui',
            self::PerluRevisi => 'Perlu Revisi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Diajukan => 'warning',
            self::Disetujui => 'success',
            self::PerluRevisi => 'error',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 border-gray-200',
            self::Diajukan => 'bg-amber-50 text-amber-700 border-amber-200/80',
            self::Disetujui => 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
            self::PerluRevisi => 'bg-rose-50 text-rose-700 border-rose-200/80',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'edit_note',
            self::Diajukan => 'hourglass_top',
            self::Disetujui => 'verified',
            self::PerluRevisi => 'warning',
        };
    }
}
