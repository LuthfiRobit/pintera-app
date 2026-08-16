<?php

namespace App\Domains\Pengadaan\DataTransferObjects;

use App\Domains\Pengadaan\Enums\TingkatUrgensi;

readonly class PengajuanPengadaanData
{
    public function __construct(
        public ?int $lembagaId,
        public ?int $yayasanId,
        public string $judulPengajuan,
        public ?string $latarBelakang,
        public TingkatUrgensi $tingkatUrgensi,
        public array $items,
    ) {
    }

    public static function fromArray(array $data, ?int $yayasanId = null, ?int $lembagaId = null): self
    {
        return new self(
            lembagaId: $data['lembaga_id'] ?? $lembagaId,
            yayasanId: $data['yayasan_id'] ?? $yayasanId,
            judulPengajuan: $data['judul_pengajuan'],
            latarBelakang: $data['latar_belakang'] ?? null,
            tingkatUrgensi: is_string($data['tingkat_urgensi']) ? TingkatUrgensi::from($data['tingkat_urgensi']) : $data['tingkat_urgensi'],
            items: $data['items'] ?? [],
        );
    }
}
