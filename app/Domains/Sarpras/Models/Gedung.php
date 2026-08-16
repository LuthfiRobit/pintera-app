<?php

namespace App\Domains\Sarpras\Models;

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Gedung extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'gedung';

    protected $fillable = [
        'yayasan_id',
        'lembaga_id',
        'kode_gedung',
        'nama_gedung',
        'jumlah_lantai',
        'deskripsi',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_lantai' => 'integer',
            'is_aktif' => 'boolean',
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

    public function ruangan(): HasMany
    {
        return $this->hasMany(Ruangan::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_gedung', 'kode_gedung', 'jumlah_lantai', 'is_aktif'])
            ->logOnlyDirty()
            ->useLogName('sarpras_gedung');
    }
}
