<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPelajaran extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'jadwal_pelajaran';

    protected $fillable = ['kelas_id', 'jam_pelajaran_id', 'mata_pelajaran_id', 'guru_id', 'semester_id', 'lembaga_id'];

    protected static function booted(): void
    {
        static::creating(function (self $jadwalPelajaran) {
            if (empty($jadwalPelajaran->lembaga_id)) {
                $jadwalPelajaran->lembaga_id = Kelas::withoutGlobalScopes()
                    ->findOrFail($jadwalPelajaran->kelas_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function jamPelajaran(): BelongsTo
    {
        return $this->belongsTo(JamPelajaran::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
