<?php

namespace App\Models;

use App\Enums\StatusSesiPembelajaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesiPembelajaran extends Model
{
    use HasFactory;

    protected $table = 'sesi_pembelajaran';

    protected $fillable = [
        'jadwal_pelajaran_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id',
        'tanggal', 'jam_mulai', 'jam_selesai', 'materi', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusSesiPembelajaran::class,
        ];
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
}
