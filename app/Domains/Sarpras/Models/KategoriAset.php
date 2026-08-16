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

class KategoriAset extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'kategori_aset';

    protected $fillable = [
        'yayasan_id',
        'lembaga_id',
        'kode_kategori',
        'nama_kategori',
        'deskripsi',
    ];

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function aset(): HasMany
    {
        return $this->hasMany(AsetBarang::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_kategori', 'kode_kategori'])
            ->logOnlyDirty()
            ->useLogName('sarpras_kategori');
    }
}
