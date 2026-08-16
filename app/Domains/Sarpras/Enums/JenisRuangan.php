<?php

namespace App\Domains\Sarpras\Enums;

enum JenisRuangan: string
{
    case KelasTeori = 'kelas_teori';
    case Laboratorium = 'laboratorium';
    case Perpustakaan = 'perpustakaan';
    case KantorGuru = 'kantor_guru';
    case Aula = 'aula';
    case Ibadah = 'ibadah';
    case Olahraga = 'olahraga';
    case Toilet = 'toilet';
    case Gudang = 'gudang';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::KelasTeori => 'Ruang Kelas Teori',
            self::Laboratorium => 'Laboratorium / Praktik',
            self::Perpustakaan => 'Perpustakaan',
            self::KantorGuru => 'Ruang Kantor / Guru',
            self::Aula => 'Aula / Gedung Pertemuan',
            self::Ibadah => 'Tempat Ibadah / Masjid',
            self::Olahraga => 'Fasilitas Olahraga / Lapangan',
            self::Toilet => 'Toilet / Sanitasi',
            self::Gudang => 'Gudang',
            self::Lainnya => 'Ruang Lainnya',
        };
    }
}
