<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LembagaDataPeriodik extends Model
{
    use BelongsToTenant;

    protected $table = 'lembaga_data_periodik';

    protected $fillable = [
        'lembaga_id', 'semester_id', 'waktu_penyelenggaraan', 'sumber_listrik', 'daya_listrik',
        'akses_internet', 'status_bos', 'sertifikasi_iso', 'ketersediaan_air_bersih',
        'kecukupan_air_bersih', 'jumlah_tempat_cuci_tangan', 'jumlah_jamban',
        'stratifikasi_uks', 'media_kie_sanitasi',
    ];

    protected function casts(): array
    {
        return [
            'status_bos' => 'boolean',
            'ketersediaan_air_bersih' => 'boolean',
            'kecukupan_air_bersih' => 'boolean',
            'media_kie_sanitasi' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
