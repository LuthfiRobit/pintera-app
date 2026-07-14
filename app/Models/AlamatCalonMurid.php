<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlamatCalonMurid extends Model
{
    protected $table = 'alamat_calon_murid';

    protected $fillable = [
        'calon_murid_id',
        'alamat_jalan',
        'rt',
        'rw',
        'dusun',
        'desa_kelurahan',
        'kecamatan',
        'kabupaten_kota',
        'provinsi',
        'kode_pos',
    ];

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }
}
