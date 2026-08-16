<?php

namespace App\Domains\Pengadaan\DataTransferObjects;

readonly class DisbursementData
{
    public function __construct(
        public float $nominalCair,
        public string $tanggalCair,
        public ?string $catatanPencairan = null,
        public ?string $buktiTransferPath = null,
    ) {
    }

    public static function fromArray(array $data, ?string $buktiTransferPath = null): self
    {
        return new self(
            nominalCair: (float) $data['nominal_pencairan'],
            tanggalCair: $data['tanggal_pencairan'],
            catatanPencairan: $data['catatan_pencairan'] ?? null,
            buktiTransferPath: $buktiTransferPath ?? ($data['bukti_transfer_pencairan_path'] ?? null),
        );
    }
}
