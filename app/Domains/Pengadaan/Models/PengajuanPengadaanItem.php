<?php

namespace App\Domains\Pengadaan\Models;

use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanPengadaanItem extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_pengadaan_item';

    protected $fillable = [
        'pengajuan_pengadaan_id',
        'kategori_aset_id',
        'target_ruangan_id',
        'nama_barang',
        'merk',
        'spesifikasi',
        'qty',
        'satuan',
        'estimasi_harga_satuan',
        'total_estimasi',
        'tipe_pencatatan',
        'foto_referensi_path',
        'status_item',
        'catatan_reviewer',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'estimasi_harga_satuan' => 'decimal:2',
            'total_estimasi' => 'decimal:2',
            'tipe_pencatatan' => TipePencatatanAset::class,
            'status_item' => StatusItemPengajuan::class,
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanPengadaan::class, 'pengajuan_pengadaan_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'kategori_aset_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'target_ruangan_id');
    }
}
