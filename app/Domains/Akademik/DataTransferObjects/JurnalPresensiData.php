<?php

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class JurnalPresensiData
{
    /**
     * @param  array<int, string>  $presensi  siswa_id (key) => status value (mis. 'hadir', 'izin')
     * @param  array<int, ?string>  $keterangan  siswa_id (key) => keterangan bebas (mis. alasan izin/sakit)
     */
    public function __construct(
        public ?string $materi,
        public array $presensi,
        public array $keterangan,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            materi: $data['materi'] ?? null,
            presensi: $data['presensi'] ?? [],
            keterangan: $data['keterangan'] ?? [],
        );
    }
}
