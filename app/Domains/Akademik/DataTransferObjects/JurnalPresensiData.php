<?php

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class JurnalPresensiData
{
    /**
     * @param  array<int, string>  $presensi  siswa_id (key) => status value (mis. 'hadir', 'izin')
     */
    public function __construct(
        public ?string $materi,
        public array $presensi,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            materi: $data['materi'] ?? null,
            presensi: $data['presensi'] ?? [],
        );
    }
}
