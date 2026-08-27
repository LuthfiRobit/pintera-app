<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Enums;

enum BentukPendidikan: string
{
    case Kb = 'KB';
    case Tpa = 'TPA';
    case Sps = 'SPS';
    case Tk = 'TK';
    case Sd = 'SD';
    case Smp = 'SMP';
    case Sma = 'SMA';
    case Smk = 'SMK';
    case Slb = 'SLB';

    /**
     * @return array<int, string> Tingkat valid untuk bentuk pendidikan ini.
     */
    public function validTingkatValues(): array
    {
        return match ($this) {
            self::Kb, self::Tpa, self::Sps, self::Tk => ['A', 'B'],
            self::Sd, self::Slb => ['1', '2', '3', '4', '5', '6'],
            self::Smp => ['7', '8', '9'],
            self::Sma, self::Smk => ['10', '11', '12'],
        };
    }
}
