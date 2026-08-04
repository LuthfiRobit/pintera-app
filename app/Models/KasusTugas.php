<?php
// app/Models/KasusTugas.php

namespace App\Models;

use App\Enums\StatusKasusTugas;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KasusTugas extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'kasus_tugas';

    protected $fillable = [
        'kasus_id', 'judul', 'instruksi', 'frekuensi',
        'mulai_pada', 'batas_selesai_pada', 'status',
    ];

    protected $attributes = [
        'status' => 'ditugaskan',
    ];

    protected function casts(): array
    {
        return [
            'mulai_pada' => 'date',
            'batas_selesai_pada' => 'date',
            'status' => StatusKasusTugas::class,
        ];
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(Kasus::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(KasusTugasSubmission::class, 'tugas_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->useLogName('kasus_tugas');
    }
}
