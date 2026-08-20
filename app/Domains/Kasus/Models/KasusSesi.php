<?php

namespace App\Domains\Kasus\Models;

use App\Enums\StatusKasusSesi;
use Database\Factories\KasusSesiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KasusSesi extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function newFactory(): KasusSesiFactory
    {
        return KasusSesiFactory::new();
    }

    protected $table = 'kasus_sesi';

    protected $fillable = [
        'kasus_id', 'dijadwalkan_pada', 'peserta', 'lokasi_mode',
        'status', 'alasan_batal', 'catatan_internal',
    ];

    protected $attributes = [
        'status' => 'terjadwal',
    ];

    protected function casts(): array
    {
        return [
            'dijadwalkan_pada' => 'datetime',
            'status' => StatusKasusSesi::class,
        ];
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(Kasus::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->useLogName('kasus_sesi');
    }
}
