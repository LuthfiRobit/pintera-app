<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Enums;

enum KurikulumFramework: string
{
    case K13 = 'k13';
    case Merdeka = 'merdeka';

    public function label(): string
    {
        return match ($this) {
            self::K13 => 'Kurikulum 2013 (K13)',
            self::Merdeka => 'Kurikulum Merdeka',
        };
    }
}
