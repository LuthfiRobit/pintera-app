<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominalTagihanSiswa extends Model
{
    protected $table = 'nominal_tagihan_siswa';

    protected $fillable = ['jenis_tagihan_id', 'siswa_id', 'nominal'];

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

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
