<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttendanceRecord extends Model
{
    use BelongsToTenant;

    protected $table = 'attendance_records';

    protected $fillable = [
        'lembaga_id', 'pegawai_type', 'pegawai_id', 'tanggal', 'status', 'waktu_masuk', 'waktu_pulang',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'tanggal' => 'date',
            'waktu_masuk' => 'datetime',
            'waktu_pulang' => 'datetime',
        ];
    }

    public function pegawai(): MorphTo
    {
        return $this->morphTo();
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
