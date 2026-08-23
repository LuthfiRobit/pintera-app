<?php

namespace App\Domains\Keuangan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisTagihanSasaranKriteria extends Model
{
    protected $table = 'jenis_tagihan_sasaran_kriteria';

    protected $fillable = ['jenis_tagihan_sasaran_grup_id', 'field', 'operator', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function grup(): BelongsTo
    {
        return $this->belongsTo(JenisTagihanSasaranGrup::class, 'jenis_tagihan_sasaran_grup_id');
    }
}
