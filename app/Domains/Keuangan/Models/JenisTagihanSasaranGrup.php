<?php

namespace App\Domains\Keuangan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisTagihanSasaranGrup extends Model
{
    protected $table = 'jenis_tagihan_sasaran_grup';

    protected $fillable = ['jenis_tagihan_id', 'tipe', 'nominal'];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function kriteria(): HasMany
    {
        return $this->hasMany(JenisTagihanSasaranKriteria::class, 'jenis_tagihan_sasaran_grup_id');
    }
}
