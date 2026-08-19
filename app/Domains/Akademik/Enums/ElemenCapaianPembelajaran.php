<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Enums;

enum ElemenCapaianPembelajaran: string
{
    case NilaiAgamaMoral = 'nilai_agama_moral';
    case JatiDiri = 'jati_diri';
    case LiterasiSteam = 'literasi_steam';

    public function label(): string
    {
        return match ($this) {
            self::NilaiAgamaMoral => 'Nilai Agama dan Budi Pekerti',
            self::JatiDiri => 'Jati Diri',
            self::LiterasiSteam => 'Literasi, STEAM, Seni, dan Budaya',
        };
    }
}
