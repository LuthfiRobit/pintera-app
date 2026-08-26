<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?string $subjekType,
        public ?int $subjekId,
        public ?int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
        public ?string $assessmentType,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subjekType: $data['subjek_type'] ?? null,
            subjekId: isset($data['subjek_id']) ? (int) $data['subjek_id'] : null,
            semesterId: isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
            assessmentType: isset($data['assessment_type']) && $data['assessment_type'] !== ''
                ? (string) $data['assessment_type']
                : null,
        );
    }
}
