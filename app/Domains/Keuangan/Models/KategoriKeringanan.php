<?php
// app/Domains/Keuangan/Models/KategoriKeringanan.php

namespace App\Domains\Keuangan\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriKeringanan extends Model
{
    use BelongsToTenant;

    protected $table = 'kategori_keringanan';

    protected $fillable = ['lembaga_id', 'nama', 'keterangan'];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jenisTagihanKeringanan(): HasMany
    {
        return $this->hasMany(JenisTagihanKeringanan::class);
    }

    public function siswaKeringanan(): HasMany
    {
        return $this->hasMany(SiswaKeringanan::class);
    }
}
