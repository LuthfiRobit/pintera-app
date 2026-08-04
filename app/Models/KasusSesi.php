<?php
// app/Models/KasusSesi.php

namespace App\Models;

use App\Enums\StatusKasusSesi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KasusSesi extends Model
{
    use HasFactory, LogsActivity;

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
