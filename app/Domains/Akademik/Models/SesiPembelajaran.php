<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\StatusSesiPembelajaran;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use Database\Factories\SesiPembelajaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesiPembelajaran extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sesi_pembelajaran';

    protected $fillable = [
        'jadwal_pelajaran_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id', 'lembaga_id',
        'tanggal', 'jam_mulai', 'jam_selesai', 'materi', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusSesiPembelajaran::class,
        ];
    }

    protected static function newFactory(): SesiPembelajaranFactory
    {
        return SesiPembelajaranFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $sesiPembelajaran) {
            if (empty($sesiPembelajaran->lembaga_id)) {
                $sesiPembelajaran->lembaga_id = Kelas::withoutGlobalScopes()
                    ->findOrFail($sesiPembelajaran->kelas_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }

    public function isTematik(): bool
    {
        return $this->jadwal_pelajaran_id === null;
    }
}

