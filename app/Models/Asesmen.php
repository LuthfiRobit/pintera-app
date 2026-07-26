<?php

namespace App\Models;

use App\Enums\JenisAsesmen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asesmen extends Model
{
    use HasFactory;

    protected $table = 'asesmen';

    protected $fillable = [
        'guru_id',
        'kelas_id',
        'mata_pelajaran_id',
        'semester_id',
        'jenis',
        'judul',
        'tanggal',
    ];

    protected $casts = [
        'jenis' => JenisAsesmen::class,
        'tanggal' => 'date',
    ];

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
