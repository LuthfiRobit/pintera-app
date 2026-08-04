<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Karyawan extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'karyawan';

    protected $fillable = [
        'user_id', 'yayasan_id', 'lembaga_id', 'jenis_karyawan_id',
        'nama', 'nik', 'no_hp', 'email', 'status_aktif', 'kapasitas_kasus_aktif',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Karyawan $karyawan) {
            $karyawan->nik_hash = hash('sha256', $karyawan->nik);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jenisKaryawan(): BelongsTo
    {
        return $this->belongsTo(JenisKaryawanMaster::class, 'jenis_karyawan_id');
    }
}
