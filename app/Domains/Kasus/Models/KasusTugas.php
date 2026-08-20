<?php

namespace App\Domains\Kasus\Models;

use App\Domains\Kasus\Enums\StatusKasusTugas;
use Database\Factories\KasusTugasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KasusTugas extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function newFactory(): KasusTugasFactory
    {
        return KasusTugasFactory::new();
    }

    protected $table = 'kasus_tugas';

    protected $fillable = [
        'kasus_id', 'judul', 'instruksi', 'frekuensi',
        'batch_id', 'batch_urutan', 'batch_total',
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
