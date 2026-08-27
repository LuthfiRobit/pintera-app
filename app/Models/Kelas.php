<?php

namespace App\Models;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'kelas';

    protected $fillable = ['lembaga_id', 'tahun_ajaran_id', 'nama', 'tingkat', 'fase_id', 'kurikulum', 'wali_kelas_guru_id', 'pola_jam_id', 'ruangan_id'];

    protected function casts(): array
    {
        return [
            'kurikulum' => KurikulumFramework::class,
        ];
    }

    public function fase(): BelongsTo
    {
        return $this->belongsTo(Fase::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function polaJam(): BelongsTo
    {
        return $this->belongsTo(PolaJam::class);
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'wali_kelas_guru_id');
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class);
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class);
    }
}
