<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class AsesmenData
{
    /**
     * @param  array<int, int>  $komponenId
     */
    public function __construct(
        public int $kelasId,
        public string $subjekType,
        public int $subjekId,
        public int $semesterId,
        public string $jenis,
        public string $judul,
        public string $tanggal,
        public array $komponenId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            kelasId: (int) $data['kelas_id'],
            subjekType: (string) $data['subjek_type'],
            subjekId: (int) $data['subjek_id'],
            semesterId: (int) $data['semester_id'],
            jenis: (string) $data['jenis'],
            judul: (string) $data['judul'],
            tanggal: (string) $data['tanggal'],
            komponenId: array_map('intval', $data['komponen_id'] ?? []),
        );
    }
}
