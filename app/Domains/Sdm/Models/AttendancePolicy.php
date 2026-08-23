<?php

namespace App\Domains\Sdm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePolicy extends Model
{
    use BelongsToTenant;

    protected $table = 'attendance_policies';

    protected $fillable = [
        'yayasan_id', 'lembaga_id', 'jenis_ptk', 'jenis_karyawan_id',
        'jam_masuk', 'jam_pulang', 'toleransi_menit', 'hari_kerja',
    ];

    protected function casts(): array
    {
        return [
            'toleransi_menit' => 'integer',
            'hari_kerja' => 'array',
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
