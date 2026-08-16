<?php

namespace App\Domains\Sarpras\Enums;

enum TipePencatatanAset: string
{
    case Unit = 'unit';
    case Batch = 'batch';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Individual / Barcode Unik',
            self::Batch => 'Batch / Kuantitas Massal',
        };
    }
}
