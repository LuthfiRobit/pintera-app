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

    /**
     * Semantik bisnis "tingkat akhir/kelulusan" -- BERBEDA dari validTingkatValues().
     * validTingkatValues() adalah source of truth validitas nilai tingkat (tingkat
     * apa saja yang boleh diinput). isTingkatAkhir() adalah source of truth semantik
     * kelulusan, yang SENGAJA tidak selalu sama dengan elemen terakhir
     * validTingkatValues() -- KB/TPA/SPS berbagi nilai valid A/B dengan TK tapi TIDAK
     * berbagi aturan kelulusan TK (keputusan terkunci Priority #3, Kelulusan PAUD/SLB).
     * JANGAN PERNAH direfactor jadi `end($this->validTingkatValues())` -- itu akan
     * membuat KB/TPA/SPS tingkat B salah dianggap tingkat akhir.
     */
    public function isTingkatAkhir(?string $tingkat): bool
    {
        if ($tingkat === null) {
            return false;
        }

        return match ($this) {
            self::Kb, self::Tpa, self::Sps => false,
            self::Tk => $tingkat === 'B',
            self::Sd, self::Slb => $tingkat === '6',
            self::Smp => $tingkat === '9',
            self::Sma, self::Smk => $tingkat === '12',
        };
    }
}
