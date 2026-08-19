<?php

namespace App\Domains\Akademik\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use Database\Factories\CatatanWaliKelasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatatanWaliKelas extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'catatan_wali_kelas';

    protected $fillable = [
        'lembaga_id', 'siswa_id', 'semester_id',
        'catatan_sikap', 'catatan_perkembangan',
        'tinggi_badan_cm', 'berat_badan_kg', 'lingkar_kepala_cm',
        'ekstrakurikuler', 'prestasi', 'pkl_info', 'keterangan_kenaikan',
    ];

    protected function casts(): array
    {
        return [
            'tinggi_badan_cm' => 'decimal:1',
            'berat_badan_kg' => 'decimal:1',
            'lingkar_kepala_cm' => 'decimal:1',
            'ekstrakurikuler' => 'array',
            'prestasi' => 'array',
            'pkl_info' => 'array',
        ];
    }

    protected static function newFactory(): CatatanWaliKelasFactory
    {
        return CatatanWaliKelasFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $catatanWaliKelas) {
            if (empty($catatanWaliKelas->lembaga_id)) {
                $catatanWaliKelas->lembaga_id = Siswa::withoutGlobalScopes()
                    ->findOrFail($catatanWaliKelas->siswa_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
