<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CalonMurid extends Model
{
    use HasFactory;

    protected $table = 'calon_murid';

    protected $fillable = [
        'yayasan_id',
        'nik',
        'no_kk',
        'nisn',
        'nama_lengkap',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'golongan_darah',
        'no_telepon',
        'email_kontak',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'no_kk' => 'encrypted',
            'tanggal_lahir' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CalonMurid $calonMurid) {
            $calonMurid->nik_hash = hash('sha256', $calonMurid->nik);
        });
    }

    public static function findByNik(string $nik): ?self
    {
        return static::where('nik_hash', hash('sha256', $nik))->first();
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function alamat(): HasOne
    {
        return $this->hasOne(AlamatCalonMurid::class);
    }

    public function keluarga(): HasMany
    {
        return $this->hasMany(KeluargaCalonMurid::class);
    }

    public function dataPeriodik(): HasOne
    {
        return $this->hasOne(DataPeriodikCalonMurid::class);
    }

    public function dataKhusus(): HasOne
    {
        return $this->hasOne(DataKhususCalonMurid::class);
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class);
    }
}
