<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataKhususCalonMurid extends Model
{
    protected $table = 'data_khusus_calon_murid';

    protected $fillable = [
        'calon_murid_id',
        'kepemilikan_kip',
        'nomor_kip',
        'riwayat_beasiswa',
        'kebutuhan_khusus',
    ];

    protected function casts(): array
    {
        return [
            'kepemilikan_kip' => 'boolean',
        ];
    }

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }
}
