<?php
// app/Domains/Keuangan/Models/JenisTagihanKeringanan.php

namespace App\Domains\Keuangan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisTagihanKeringanan extends Model
{
    protected $table = 'jenis_tagihan_keringanan';

    protected $fillable = ['jenis_tagihan_id', 'kategori_keringanan_id', 'tipe_potongan', 'nilai', 'keterangan'];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function kategoriKeringanan(): BelongsTo
    {
        return $this->belongsTo(KategoriKeringanan::class);
    }
}
