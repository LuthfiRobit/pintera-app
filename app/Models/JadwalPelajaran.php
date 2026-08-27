<?php

namespace App\Models;

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPelajaran extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'jadwal_pelajaran';

    protected $fillable = ['kelas_id', 'jam_pelajaran_id', 'mata_pelajaran_id', 'guru_id', 'semester_id', 'lembaga_id', 'ruangan_id'];

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

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
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

    /**
     * Filter ke jadwal yang semester-nya berstatus aktif. Semua consumer BARU
     * yang menampilkan jadwal "saat ini" (bukan laporan histori) WAJIB
     * memakai scope ini -- lihat riwayat bug widget "Jadwal Hari Ini" guru
     * yang bocor lintas tahun ajaran (audit 27 Agustus 2026).
     */
    public function scopeSemesterAktif(Builder $query): Builder
    {
        return $query->whereHas('semester', fn (Builder $q) => $q->where('status_aktif', true));
    }
}
