<?php

namespace App\Domains\Pengadaan\Actions;

use App\Domains\Pengadaan\DataTransferObjects\PengajuanPengadaanData;
use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaanItem;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePengajuanAction
{
    public function execute(PengajuanPengadaanData $data, int $userId): PengajuanPengadaan
    {
        return DB::transaction(function () use ($data, $userId) {
            $tahunBulan = date('Y/m');
            $random = strtoupper(Str::random(4));
            $nomorPengajuan = "PR/{$tahunBulan}/{$random}";

            $totalEstimasi = 0;
            foreach ($data->items as $item) {
                $qty = (int) ($item['qty'] ?? 1);
                $harga = (float) ($item['estimasi_harga_satuan'] ?? 0);
                $totalEstimasi += ($qty * $harga);
            }

            $proposal = PengajuanPengadaan::create([
                'yayasan_id' => $data->yayasanId,
                'lembaga_id' => $data->lembagaId,
                'nomor_pengajuan' => $nomorPengajuan,
                'judul_pengajuan' => $data->judulPengajuan,
                'latar_belakang' => $data->latarBelakang,
                'tingkat_urgensi' => $data->tingkatUrgensi,
                'total_estimasi' => $totalEstimasi,
                'status' => StatusPengajuan::Draft,
                'created_by_user_id' => $userId,
            ]);

            foreach ($data->items as $itemData) {
                $qty = (int) ($itemData['qty'] ?? 1);
                $harga = (float) ($itemData['estimasi_harga_satuan'] ?? 0);
                $total = $qty * $harga;

                PengajuanPengadaanItem::create([
                    'pengajuan_pengadaan_id' => $proposal->id,
                    'kategori_aset_id' => $itemData['kategori_aset_id'] ?? null,
                    'target_ruangan_id' => $itemData['target_ruangan_id'] ?? null,
                    'nama_barang' => $itemData['nama_barang'],
                    'merk' => $itemData['merk'] ?? null,
                    'spesifikasi' => $itemData['spesifikasi'] ?? null,
                    'qty' => $qty,
                    'satuan' => $itemData['satuan'] ?? 'unit',
                    'estimasi_harga_satuan' => $harga,
                    'total_estimasi' => $total,
                    'tipe_pencatatan' => isset($itemData['tipe_pencatatan']) && is_string($itemData['tipe_pencatatan'])
                        ? TipePencatatanAset::from($itemData['tipe_pencatatan'])
                        : ($itemData['tipe_pencatatan'] ?? TipePencatatanAset::Unit),
                    'foto_referensi_path' => $itemData['foto_referensi_path'] ?? null,
                    'status_item' => StatusItemPengajuan::Pending,
                ]);
            }

            return $proposal->load('items');
        });
    }
}
