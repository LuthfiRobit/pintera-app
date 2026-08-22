<?php

namespace App\Domains\Sdm\Enums;

enum AttendanceMethod: string
{
    case Admin = 'admin';
    case Qr = 'qr';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Input Manual Admin',
            self::Qr => 'Scan QR',
            self::System => 'Otomatis Sistem',
        };
    }
}
