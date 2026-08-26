<?php

namespace App\Domains\Akademik\Enums;

enum AssessmentType: string
{
    case Numeric = 'numeric';
    case Narrative = 'narrative';
    case Predicate = 'predicate';

    public function label(): string
    {
        return match ($this) {
            self::Numeric => 'Nilai Angka',
            self::Narrative => 'Naratif/Deskriptif',
            self::Predicate => 'Predikat Capaian',
        };
    }
}
