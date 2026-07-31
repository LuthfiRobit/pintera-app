<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiSiswa extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'nilai_siswa';

    protected $fillable = [
        'asesmen_id',
        'siswa_id',
        'komponen_penilaian_id',
        'lembaga_id',
        'nilai_angka',
        'predikat',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'nilai_angka' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $nilaiSiswa) {
            if (empty($nilaiSiswa->lembaga_id)) {
                $nilaiSiswa->lembaga_id = Siswa::withoutGlobalScopes()
                    ->findOrFail($nilaiSiswa->siswa_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function asesmen(): BelongsTo
    {
        return $this->belongsTo(Asesmen::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function komponenPenilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class);
    }
}
