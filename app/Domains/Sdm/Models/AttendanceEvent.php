<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttendanceEvent extends Model
{
    use BelongsToTenant;

    const UPDATED_AT = null;

    protected $table = 'attendance_events';

    protected $fillable = [
        'lembaga_id', 'pegawai_type', 'pegawai_id', 'attendance_point_id',
        'method', 'arah', 'status', 'waktu', 'dicatat_oleh_user_id', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'method' => AttendanceMethod::class,
            'status' => AttendanceStatus::class,
            'waktu' => 'datetime',
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

    public function attendancePoint(): BelongsTo
    {
        return $this->belongsTo(AttendancePoint::class);
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh_user_id');
    }
}
