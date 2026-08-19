<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class NilaiSiswaBatchData
{
    /**
     * @param  array<int|string, array<int|string, array{nilai_angka?: int|string|null, catatan?: string|null}>>  $nilai  siswa_id => komponen_penilaian_id => [nilai_angka, catatan]
     */
    public function __construct(
        public array $nilai,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            nilai: $data['nilai'] ?? [],
        );
    }
}
