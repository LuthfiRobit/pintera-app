<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KomponenPenilaianData
{
    public function __construct(
        public int $mataPelajaranId,
        public int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public int $bobot,
        public ?string $kktp,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: (int) $data['mata_pelajaran_id'],
            semesterId: (int) $data['semester_id'],
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
            kktp: $data['kktp'] ?? null,
        );
    }
}
