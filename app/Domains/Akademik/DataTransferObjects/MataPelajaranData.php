<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class MataPelajaranData
{
    public function __construct(
        public int $lembagaId,
        public string $kode,
        public string $nama,
        public int $noUrut,
        public string $tipe,
        public ?string $kelompok,
        public string $status,
    ) {}

    public static function fromArray(array $data, int $lembagaId): self
    {
        return new self(
            lembagaId: $lembagaId,
            kode: $data['kode'],
            nama: $data['nama'],
            noUrut: (int) $data['no_urut'],
            tipe: $data['tipe'],
            kelompok: $data['kelompok'] ?? null,
            status: $data['status'],
        );
    }
}
