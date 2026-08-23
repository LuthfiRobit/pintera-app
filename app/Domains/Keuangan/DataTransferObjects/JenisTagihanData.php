<?php

namespace App\Domains\Keuangan\DataTransferObjects;

final readonly class JenisTagihanData
{
    /**
     * @param  array<string, mixed>  $rawBillingConfig  Semua field konfigurasi billing dari form/request
     */
    public function __construct(
        public string $nama,
        public string $kategori,
        public bool $bisaDicicil,
        public ?int $maksCicilan,
        public array $rawBillingConfig = [],
    ) {}

    /**
     * Factory dari request array tervalidasi.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            nama: (string) $validated['nama'],
            kategori: (string) $validated['kategori'],
            bisaDicicil: ! empty($validated['bisa_dicicil']),
            maksCicilan: ! empty($validated['bisa_dicicil']) ? (int) ($validated['maks_cicilan'] ?? 1) : null,
            rawBillingConfig: $validated,
        );
    }
}
