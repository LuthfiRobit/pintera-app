<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JalurPpdb extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'jalur_ppdb';

    protected $fillable = ['lembaga_id', 'tahun_ajaran_id', 'nama', 'deskripsi', 'status_aktif'];

    protected function casts(): array
    {
        return [
            'status_aktif' => 'boolean',
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

    public function gelombang(): BelongsToMany
    {
        return $this->belongsToMany(GelombangPpdb::class, 'gelombang_jalur', 'jalur_ppdb_id', 'gelombang_ppdb_id');
    }

    public function formulirField(): HasMany
    {
        return $this->hasMany(FormulirField::class, 'jalur_ppdb_id')->orderBy('urutan');
    }

    public function dokumenSyarat(): HasMany
    {
        return $this->hasMany(DokumenSyaratPpdb::class, 'jalur_ppdb_id')->orderBy('urutan');
    }

    public function seleksi(): HasMany
    {
        return $this->hasMany(SeleksiPpdb::class, 'jalur_ppdb_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama', 'deskripsi', 'status_aktif'])
            ->logOnlyDirty()
            ->useLogName('jalur_ppdb');
    }
}
