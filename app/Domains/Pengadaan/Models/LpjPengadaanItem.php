<?php

namespace App\Domains\Pengadaan\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LpjPengadaanItem extends Model
{
    use HasFactory;

    protected $table = 'lpj_pengadaan_item';

    protected $fillable = [
        'lpj_pengadaan_id',
        'pengajuan_item_id',
        'harga_satuan_riil',
        'total_riil',
        'foto_nota_path',
        'foto_fisik_barang_path',
        'status_konversi_sarpras',
    ];

    protected function casts(): array
    {
        return [
            'harga_satuan_riil' => 'decimal:2',
            'total_riil' => 'decimal:2',
        ];
    }

    public function lpj(): BelongsTo
    {
        return $this->belongsTo(LpjPengadaan::class, 'lpj_pengadaan_id');
    }

    public function pengajuanItem(): BelongsTo
    {
        return $this->belongsTo(PengajuanPengadaanItem::class, 'pengajuan_item_id');
    }
}
