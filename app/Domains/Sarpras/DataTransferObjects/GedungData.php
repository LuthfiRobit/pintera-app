<?php

namespace App\Domains\Sarpras\DataTransferObjects;

final readonly class GedungData
{
    public function __construct(
        public int $yayasanId,
        public ?int $lembagaId,
        public string $kodeGedung,
        public string $namaGedung,
        public int $jumlahLantai = 1,
        public ?string $deskripsi = null,
        public bool $isAktif = true,
    ) {
    }

    public static function fromArray(array $data, int $yayasanId, ?int $lembagaId): self
    {
        return new self(
            yayasanId: $yayasanId,
            lembagaId: $lembagaId,
            kodeGedung: trim($data['kode_gedung']),
            namaGedung: trim($data['nama_gedung']),
            jumlahLantai: (int) ($data['jumlah_lantai'] ?? 1),
            deskripsi: ! empty($data['deskripsi']) ? trim($data['deskripsi']) : null,
            isAktif: isset($data['is_aktif']) ? (bool) $data['is_aktif'] : true,
        );
    }
}
