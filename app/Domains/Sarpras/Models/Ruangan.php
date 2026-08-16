<?php

namespace App\Domains\Sarpras\Models;

use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Ruangan extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'ruangan';

    protected $fillable = [
        'yayasan_id',
        'lembaga_id',
        'gedung_id',
        'kode_ruangan',
        'nama_ruangan',
        'lantai',
        'jenis_ruangan',
        'kapasitas_siswa',
        'luas_m2',
        'penanggung_jawab_guru_id',
        'is_shared',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'lantai' => 'integer',
            'kapasitas_siswa' => 'integer',
            'luas_m2' => 'decimal:2',
            'is_shared' => 'boolean',
            'is_aktif' => 'boolean',
            'jenis_ruangan' => JenisRuangan::class,
        ];
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function gedung(): BelongsTo
    {
        return $this->belongsTo(Gedung::class);
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'penanggung_jawab_guru_id');
    }

    public function aset(): HasMany
    {
        return $this->hasMany(AsetBarang::class);
    }

    public function kelasHome(): HasMany
    {
        return $this->hasMany(Kelas::class, 'ruangan_id');
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'ruangan_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_ruangan', 'kode_ruangan', 'jenis_ruangan', 'kapasitas_siswa', 'is_shared', 'is_aktif'])
            ->logOnlyDirty()
            ->useLogName('sarpras_ruangan');
    }
}
