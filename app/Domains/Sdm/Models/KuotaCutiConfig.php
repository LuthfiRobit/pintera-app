<?php

namespace App\Domains\Sdm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuotaCutiConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'kuota_cuti_config';

    protected $fillable = [
        'yayasan_id', 'lembaga_id', 'jenis_ptk', 'jenis_karyawan_id', 'jatah_hari_per_tahun',
    ];

    protected function casts(): array
    {
        return [
            'jatah_hari_per_tahun' => 'integer',
        ];
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jenisKaryawan(): BelongsTo
    {
        return $this->belongsTo(JenisKaryawanMaster::class, 'jenis_karyawan_id');
    }
}
