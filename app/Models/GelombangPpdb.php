<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GelombangPpdb extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'gelombang_ppdb';

    protected $fillable = ['lembaga_id', 'tahun_ajaran_id', 'nama', 'tanggal_buka', 'tanggal_tutup', 'kuota'];

    protected function casts(): array
    {
        return [
            'tanggal_buka' => 'date',
            'tanggal_tutup' => 'date',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function seleksi(): HasMany
    {
        return $this->hasMany(SeleksiPpdb::class, 'gelombang_ppdb_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama', 'tanggal_buka', 'tanggal_tutup', 'kuota'])
            ->logOnlyDirty()
            ->useLogName('gelombang_ppdb');
    }
}
