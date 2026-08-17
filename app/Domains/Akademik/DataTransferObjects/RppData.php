<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

use Illuminate\Http\UploadedFile;

final readonly class RppData
{
    public function __construct(
        public int $yayasanId,
        public int $lembagaId,
        public int $guruId,
        public int $tahunAjaranId,
        public int $semesterId,
        public int $kelasId,
        public string $judulTopik,
        public string $alokasiWaktu,
        public ?int $mataPelajaranId = null,
        public ?string $pertemuanKe = null,
        public ?UploadedFile $file = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            yayasanId: (int) $data['yayasan_id'],
            lembagaId: (int) $data['lembaga_id'],
            guruId: (int) $data['guru_id'],
            tahunAjaranId: (int) $data['tahun_ajaran_id'],
            semesterId: (int) $data['semester_id'],
            kelasId: (int) $data['kelas_id'],
            judulTopik: (string) $data['judul_topik'],
            alokasiWaktu: (string) $data['alokasi_waktu'],
            mataPelajaranId: ! empty($data['mata_pelajaran_id']) ? (int) $data['mata_pelajaran_id'] : null,
            pertemuanKe: ! empty($data['pertemuan_ke']) ? (string) $data['pertemuan_ke'] : null,
            file: $data['file'] ?? null,
        );
    }
}
