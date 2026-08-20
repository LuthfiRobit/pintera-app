<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KalenderAkademikData
{
    public function __construct(
        public string $tanggal,
        public ?string $tanggalSelesai,
        public string $nama,
        public string $tipe,
        public ?string $keterangan,
        public bool $berlakuNasional,
    ) {}
}
