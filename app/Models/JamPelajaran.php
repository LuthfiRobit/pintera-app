<?php

namespace App\Models;

use App\Enums\Hari;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JamPelajaran extends Model
{
    use HasFactory;

    protected $table = 'jam_pelajaran';

    protected $fillable = ['pola_jam_id', 'hari', 'urutan', 'label', 'jam_mulai', 'jam_selesai', 'is_pelajaran'];

    protected function casts(): array
    {
        return [
            'hari' => Hari::class,
            'is_pelajaran' => 'boolean',
        ];
    }

    public function polaJam(): BelongsTo
    {
        return $this->belongsTo(PolaJam::class);
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class);
    }

    public function scopeIsPelajaran($query)
    {
        return $query->where('is_pelajaran', true);
    }
}
