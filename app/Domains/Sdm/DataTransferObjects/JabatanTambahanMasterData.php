<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

final readonly class JabatanTambahanMasterData
{
    public function __construct(
        public string $nama,
        public string $kelompok,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nama: $data['nama'],
            kelompok: $data['kelompok'],
        );
    }
}
