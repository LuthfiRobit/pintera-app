<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataPeriodikCalonMurid extends Model
{
    protected $table = 'data_periodik_calon_murid';

    protected $fillable = [
        'calon_murid_id',
        'tinggi_badan_cm',
        'berat_badan_kg',
        'jarak_tempuh_km',
        'waktu_tempuh_menit',
        'jumlah_saudara_kandung',
        'alat_transportasi',
    ];

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }
}
