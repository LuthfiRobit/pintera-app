<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominalTagihanJalur extends Model
{
    use HasFactory;

    protected $table = 'nominal_tagihan_jalur';

    protected $fillable = ['jenis_tagihan_id', 'jalur_ppdb_id', 'nominal'];

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class);
    }
}
