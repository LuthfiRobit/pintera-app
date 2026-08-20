<?php

namespace App\Domains\Kasus\Enums;

enum StatusKasusTugas: string
{
    case Ditugaskan = 'ditugaskan';
    case Dikerjakan = 'dikerjakan';
    case Revisi = 'revisi';
    case Selesai = 'selesai';
    case Terlewat = 'terlewat';

    public function label(): string
    {
        return match ($this) {
            self::Ditugaskan => 'Ditugaskan',
            self::Dikerjakan => 'Dikerjakan',
            self::Revisi => 'Revisi',
            self::Selesai => 'Selesai',
            self::Terlewat => 'Terlewat',
        };
    }
}
