<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Database\Factories\AsesmenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asesmen extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'asesmen';

    protected $fillable = [
        'guru_id',
        'kelas_id',
        'mata_pelajaran_id',
        'semester_id',
        'lembaga_id',
        'jenis',
        'judul',
        'tanggal',
    ];

    protected $casts = [
        'jenis' => JenisAsesmen::class,
        'tanggal' => 'date',
    ];

    protected static function newFactory(): AsesmenFactory
    {
        return AsesmenFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $asesmen) {
            if (empty($asesmen->lembaga_id)) {
                $asesmen->lembaga_id = Kelas::withoutGlobalScopes()
                    ->findOrFail($asesmen->kelas_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function komponenPenilaian(): BelongsToMany
    {
        return $this->belongsToMany(KomponenPenilaian::class, 'asesmen_komponen_penilaian', 'asesmen_id', 'komponen_penilaian_id');
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }
}
