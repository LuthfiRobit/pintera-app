<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisTesMaster extends Model
{
    use BelongsToTenant;

    protected $table = 'jenis_tes_master';

    protected $fillable = ['lembaga_id', 'nama', 'deskripsi'];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function seleksi(): HasMany
    {
        return $this->hasMany(SeleksiPpdb::class, 'jenis_tes_master_id');
    }
}
