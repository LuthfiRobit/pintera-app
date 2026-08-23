<?php

namespace App\Domains\Keuangan\DataTransferObjects;

final readonly class JenisTagihanData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $billing
     */
    public function __construct(
        public string $nama,
        public string $kategori,
        public bool $bisaDicicil,
        public ?int $maksCicilan,
        public array $attributes,
        public ?array $billing = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $billing
     */
    public static function fromArray(array $data, ?array $billing = null): self
    {
        return new self(
            nama: (string) $data['nama'],
            kategori: (string) $data['kategori'],
            bisaDicicil: ! empty($data['bisa_dicicil']),
            maksCicilan: ! empty($data['bisa_dicicil']) ? (int) ($data['maks_cicilan'] ?? 1) : null,
            attributes: $data,
            billing: $billing,
        );
    }
}
