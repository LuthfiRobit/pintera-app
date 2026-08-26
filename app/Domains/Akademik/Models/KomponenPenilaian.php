<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Semester;
use Database\Factories\KomponenPenilaianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KomponenPenilaian extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'komponen_penilaian';

    protected $fillable = ['subjek_type', 'subjek_id', 'semester_id', 'lembaga_id', 'kode', 'deskripsi', 'bobot', 'kktp', 'kktp_minimal'];

    protected static function newFactory(): KomponenPenilaianFactory
    {
        return KomponenPenilaianFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $komponenPenilaian) {
            if (empty($komponenPenilaian->lembaga_id) && $komponenPenilaian->subjek_type === 'mata_pelajaran') {
                $komponenPenilaian->lembaga_id = MataPelajaran::withoutGlobalScopes()
                    ->findOrFail($komponenPenilaian->subjek_id)
                    ->lembaga_id;
            }
            // subjek_type === 'elemen_cp': ElemenCp global, tidak punya
            // lembaga_id sendiri -- lembaga_id WAJIB sudah di-set eksplisit
            // oleh caller (CreateKomponenPenilaianAction) dari Semester
            // sebelum sampai sini.
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo();
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function asesmen(): BelongsToMany
    {
        return $this->belongsToMany(Asesmen::class, 'asesmen_komponen_penilaian', 'komponen_penilaian_id', 'asesmen_id');
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }
}
