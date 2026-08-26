<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KelasData
{
    public function __construct(
        public int $tahunAjaranId,
        public string $nama,
        public ?string $tingkat,
        public ?int $faseId,
        public ?int $waliKelasGuruId,
        public ?int $polaJamId,
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new self(
            tahunAjaranId: (int) $validated['tahun_ajaran_id'],
            nama: $validated['nama'],
            tingkat: $validated['tingkat'] ?? null,
            faseId: isset($validated['fase_id']) ? (int) $validated['fase_id'] : null,
            waliKelasGuruId: isset($validated['wali_kelas_guru_id']) && $validated['wali_kelas_guru_id'] !== '' ? (int) $validated['wali_kelas_guru_id'] : null,
            polaJamId: isset($validated['pola_jam_id']) && $validated['pola_jam_id'] !== '' ? (int) $validated['pola_jam_id'] : null,
        );
    }
}
