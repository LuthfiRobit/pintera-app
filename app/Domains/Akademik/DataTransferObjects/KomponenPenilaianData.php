<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KomponenPenilaianData
{
    public function __construct(
        public string $subjekType,
        public int $subjekId,
        public int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subjekType: (string) $data['subjek_type'],
            subjekId: (int) $data['subjek_id'],
            semesterId: (int) $data['semester_id'],
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
        );
    }
}
