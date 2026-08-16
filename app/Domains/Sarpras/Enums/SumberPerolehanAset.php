<?php

namespace App\Domains\Sarpras\Enums;

enum SumberPerolehanAset: string
{
    case BeliYayasan = 'beli_yayasan';
    case BeliLembaga = 'beli_lembaga';
    case Hibah = 'hibah';
    case BantuanPemerintah = 'bantuan_pemerintah';

    public function label(): string
    {
        return match ($this) {
            self::BeliYayasan => 'Pembelian Yayasan',
            self::BeliLembaga => 'Pembelian Mandiri Lembaga',
            self::Hibah => 'Hibah / Sumbangan Wali Murid',
            self::BantuanPemerintah => 'Bantuan Pemerintah (BOS / DAK)',
        };
    }
}
