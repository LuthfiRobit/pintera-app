<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class CatatanWaliKelasData
{
    /**
     * @param  array<int, array<string, mixed>>  $ekstrakurikuler
     * @param  array<int, array<string, mixed>>  $prestasi
     * @param  array<int, array<string, mixed>>  $pklInfo
     */
    public function __construct(
        public int $siswaId,
        public int $semesterId,
        public ?string $catatanSikap,
        public ?string $catatanPerkembangan,
        public ?float $tinggiBadanCm,
        public ?float $beratBadanKg,
        public ?float $lingkarKepalaCm,
        public array $ekstrakurikuler,
        public array $prestasi,
        public array $pklInfo,
        public ?string $keteranganKenaikan,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            siswaId: (int) $data['siswa_id'],
            semesterId: (int) $data['semester_id'],
            catatanSikap: $data['catatan_sikap'] ?? null,
            catatanPerkembangan: $data['catatan_perkembangan'] ?? null,
            tinggiBadanCm: isset($data['tinggi_badan_cm']) ? (float) $data['tinggi_badan_cm'] : null,
            beratBadanKg: isset($data['berat_badan_kg']) ? (float) $data['berat_badan_kg'] : null,
            lingkarKepalaCm: isset($data['lingkar_kepala_cm']) ? (float) $data['lingkar_kepala_cm'] : null,
            ekstrakurikuler: $data['ekstrakurikuler'] ?? [],
            prestasi: $data['prestasi'] ?? [],
            pklInfo: $data['pkl_info'] ?? [],
            keteranganKenaikan: $data['keterangan_kenaikan'] ?? null,
        );
    }
}
