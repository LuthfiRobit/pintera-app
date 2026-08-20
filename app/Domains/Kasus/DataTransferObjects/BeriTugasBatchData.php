<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class BeriTugasBatchData
{
    public function __construct(
        public string $judul,
        public string $instruksi,
        public string $frekuensi,
        public string $tanggalMulai,
        public string $tanggalSelesai,
        public mixed $tanggalPengumpulanBulananRaw,
    ) {}
}
