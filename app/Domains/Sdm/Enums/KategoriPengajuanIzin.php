<?php

namespace App\Domains\Sdm\Enums;

enum KategoriPengajuanIzin: string
{
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';

    public function label(): string
    {
        return match ($this) {
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Cuti => 'Cuti',
        };
    }

    public function toAttendanceStatus(): AttendanceStatus
    {
        return match ($this) {
            self::Izin => AttendanceStatus::Izin,
            self::Sakit => AttendanceStatus::Sakit,
            self::Cuti => AttendanceStatus::Cuti,
        };
    }
}
