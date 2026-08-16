<?php

namespace App\Domains\Sarpras\DataTransferObjects;

use App\Domains\Sarpras\Enums\JenisRuangan;

final readonly class RuanganData
{
    public function __construct(
        public int $yayasanId,
        public ?int $lembagaId,
        public int $gedungId,
        public string $kodeRuangan,
        public string $namaRuangan,
        public int $lantai = 1,
        public JenisRuangan $jenisRuangan = JenisRuangan::KelasTeori,
        public ?int $kapasitasSiswa = null,
        public ?float $luasM2 = null,
        public ?int $penanggungJawabGuruId = null,
        public bool $isShared = false,
        public bool $isAktif = true,
    ) {
    }

    public static function fromArray(array $data, int $yayasanId, ?int $lembagaId): self
    {
        return new self(
            yayasanId: $yayasanId,
            lembagaId: $lembagaId,
            gedungId: (int) $data['gedung_id'],
            kodeRuangan: trim($data['kode_ruangan']),
            namaRuangan: trim($data['nama_ruangan']),
            lantai: (int) ($data['lantai'] ?? 1),
            jenisRuangan: $data['jenis_ruangan'] instanceof JenisRuangan
                ? $data['jenis_ruangan']
                : JenisRuangan::from($data['jenis_ruangan'] ?? 'kelas_teori'),
            kapasitasSiswa: ! empty($data['kapasitas_siswa']) ? (int) $data['kapasitas_siswa'] : null,
            luasM2: ! empty($data['luas_m2']) ? (float) $data['luas_m2'] : null,
            penanggungJawabGuruId: ! empty($data['penanggung_jawab_guru_id']) ? (int) $data['penanggung_jawab_guru_id'] : null,
            isShared: isset($data['is_shared']) ? (bool) $data['is_shared'] : false,
            isAktif: isset($data['is_aktif']) ? (bool) $data['is_aktif'] : true,
        );
    }
}
