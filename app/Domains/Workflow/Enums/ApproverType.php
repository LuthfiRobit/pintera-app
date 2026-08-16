<?php

namespace App\Domains\Workflow\Enums;

enum ApproverType: string
{
    case Role = 'ROLE';
    case DirectRelation = 'DIRECT_RELATION';
    case SpecificUser = 'SPECIFIC_USER';

    public function label(): string
    {
        return match ($this) {
            self::Role => 'Peran (Role)',
            self::DirectRelation => 'Relasi Langsung (Atasan/Wali)',
            self::SpecificUser => 'Pengguna Tertentu',
        };
    }
}
