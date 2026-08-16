<?php

namespace App\Domains\Pengadaan\DataTransferObjects;

readonly class LpjPengadaanData
{
    public function __construct(
        public array $items,
        public ?string $buktiKembaliSisaDanaPath = null,
    ) {
    }

    public static function fromArray(array $data, ?string $buktiKembaliSisaDanaPath = null): self
    {
        return new self(
            items: $data['items'] ?? [],
            buktiKembaliSisaDanaPath: $buktiKembaliSisaDanaPath ?? ($data['bukti_kembali_sisa_dana_path'] ?? null),
        );
    }
}
