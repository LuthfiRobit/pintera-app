<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenPenilaian extends Model
{
    use HasFactory;

    protected $table = 'komponen_penilaian';

    protected $fillable = ['mata_pelajaran_id', 'semester_id', 'kode', 'deskripsi', 'kktp'];

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
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
