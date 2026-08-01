<?php

namespace App\Enums;

enum KelompokMataPelajaran: string
{
    case Umum = 'umum';
    case AgamaKemenag = 'agama_kemenag';
    case Pilihan = 'pilihan';
    case Kejuruan = 'kejuruan';
    case Mulok = 'mulok';
    case ProjekP5Ppra = 'projek_p5_ppra';

    public function label(): string
    {
        return match ($this) {
            self::Umum => 'Kelompok Umum (Wajib)',
            self::AgamaKemenag => 'Agama Kemenag (KMA 450)',
            self::Pilihan => 'Kelompok Pilihan (Fase F)',
            self::Kejuruan => 'Kejuruan / Produktif (SMK)',
            self::Mulok => 'Muatan Lokal',
            self::ProjekP5Ppra => 'Projek P5 & PPRA',
        };
    }
}
