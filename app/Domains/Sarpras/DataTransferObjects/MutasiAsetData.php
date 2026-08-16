<?php

namespace App\Domains\Sarpras\DataTransferObjects;

final readonly class MutasiAsetData
{
    public function __construct(
        public int $asetBarangId,
        public int $ruanganTujuanId,
        public int $qtyPindah,
        public string $tanggalMutasi,
        public string $alasanMutasi,
        public int $dilakukanOlehUserId,
    ) {
    }

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            asetBarangId: (int) $data['aset_barang_id'],
            ruanganTujuanId: (int) $data['ruangan_tujuan_id'],
            qtyPindah: (int) ($data['qty_pindah'] ?? 1),
            tanggalMutasi: ! empty($data['tanggal_mutasi']) ? $data['tanggal_mutasi'] : now()->toDateString(),
            alasanMutasi: trim($data['alasan_mutasi']),
            dilakukanOlehUserId: $userId,
        );
    }
}
