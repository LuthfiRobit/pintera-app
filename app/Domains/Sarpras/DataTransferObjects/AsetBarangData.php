<?php

namespace App\Domains\Sarpras\DataTransferObjects;

use App\Domains\Sarpras\Enums\KondisiAset;
use App\Domains\Sarpras\Enums\SumberPerolehanAset;
use App\Domains\Sarpras\Enums\TipePencatatanAset;

final readonly class AsetBarangData
{
    public function __construct(
        public int $yayasanId,
        public ?int $lembagaId,
        public int $kategoriAsetId,
        public int $ruanganId,
        public string $kodeInventaris,
        public string $namaBarang,
        public ?string $merk = null,
        public ?string $spesifikasi = null,
        public TipePencatatanAset $tipePencatatan = TipePencatatanAset::Unit,
        public int $qty = 1,
        public string $satuan = 'unit',
        public KondisiAset $kondisi = KondisiAset::Baik,
        public SumberPerolehanAset $sumberPerolehan = SumberPerolehanAset::BeliLembaga,
        public ?string $tanggalPerolehan = null,
        public ?float $hargaPerolehan = null,
        public ?string $fotoBarangPath = null,
    ) {
    }

    public static function fromArray(array $data, int $yayasanId, ?int $lembagaId): self
    {
        return new self(
            yayasanId: $yayasanId,
            lembagaId: $lembagaId,
            kategoriAsetId: (int) $data['kategori_aset_id'],
            ruanganId: (int) $data['ruangan_id'],
            kodeInventaris: trim($data['kode_inventaris']),
            namaBarang: trim($data['nama_barang']),
            merk: ! empty($data['merk']) ? trim($data['merk']) : null,
            spesifikasi: ! empty($data['spesifikasi']) ? trim($data['spesifikasi']) : null,
            tipePencatatan: $data['tipe_pencatatan'] instanceof TipePencatatanAset
                ? $data['tipe_pencatatan']
                : TipePencatatanAset::from($data['tipe_pencatatan'] ?? 'unit'),
            qty: (int) ($data['qty'] ?? 1),
            satuan: ! empty($data['satuan']) ? trim($data['satuan']) : 'unit',
            kondisi: $data['kondisi'] instanceof KondisiAset
                ? $data['kondisi']
                : KondisiAset::from($data['kondisi'] ?? 'baik'),
            sumberPerolehan: $data['sumber_perolehan'] instanceof SumberPerolehanAset
                ? $data['sumber_perolehan']
                : SumberPerolehanAset::from($data['sumber_perolehan'] ?? 'beli_lembaga'),
            tanggalPerolehan: ! empty($data['tanggal_perolehan']) ? $data['tanggal_perolehan'] : null,
            hargaPerolehan: ! empty($data['harga_perolehan']) ? (float) $data['harga_perolehan'] : null,
            fotoBarangPath: ! empty($data['foto_barang_path']) ? $data['foto_barang_path'] : null,
        );
    }
}
