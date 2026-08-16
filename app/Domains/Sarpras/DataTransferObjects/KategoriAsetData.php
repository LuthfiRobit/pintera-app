<?php

namespace App\Domains\Sarpras\DataTransferObjects;

final readonly class KategoriAsetData
{
    public function __construct(
        public int $yayasanId,
        public ?int $lembagaId,
        public string $kodeKategori,
        public string $namaKategori,
        public ?string $deskripsi = null,
    ) {
    }

    public static function fromArray(array $data, int $yayasanId, ?int $lembagaId): self
    {
        return new self(
            yayasanId: $yayasanId,
            lembagaId: $lembagaId,
            kodeKategori: trim($data['kode_kategori']),
            namaKategori: trim($data['nama_kategori']),
            deskripsi: ! empty($data['deskripsi']) ? trim($data['deskripsi']) : null,
        );
    }
}
