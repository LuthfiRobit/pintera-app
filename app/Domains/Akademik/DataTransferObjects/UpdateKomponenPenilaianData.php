<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

use App\Domains\Akademik\Enums\ElemenCapaianPembelajaran;

final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?int $mataPelajaranId,
        public ?int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
        public ?ElemenCapaianPembelajaran $elemenCp,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: isset($data['mata_pelajaran_id']) ? (int) $data['mata_pelajaran_id'] : null,
            semesterId: isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
            elemenCp: isset($data['elemen_cp']) && $data['elemen_cp'] !== '' ? ElemenCapaianPembelajaran::from($data['elemen_cp']) : null,
        );
    }
}
